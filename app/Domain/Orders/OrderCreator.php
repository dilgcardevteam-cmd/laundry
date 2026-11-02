<?php

namespace App\Domain\Orders;

use App\Domain\Tracking\TrackingToken;
use App\Models\AddOn;
use App\Models\Customer;
use App\Models\LaundryOrder;
use App\Models\LaundryStatusHistory;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreator
{
    public function __construct(
        private readonly PricingCalculator $pricing,
        private readonly TrackingToken $tokens,
    ) {}

    public function create(array $data, ?int $cashierId): array
    {
        return DB::transaction(function () use ($data, $cashierId): array {
            if (! empty($data['customer_id'])) {
                $customer = Customer::findOrFail($data['customer_id']);
            } elseif (! empty($data['customer_name'])) {
                $name = trim($data['customer_name']);
                $customer = Customer::where('name', $name)->first();

                if (! $customer) {
                    $customer = Customer::create([
                        'code' => 'CUS-'.str()->upper(str()->random(10)),
                        'name' => $name,
                        'contact_number' => $data['customer_contact'] ?? null,
                    ]);
                } elseif (! empty($data['customer_contact']) && empty($customer->contact_number)) {
                    $customer->update(['contact_number' => $data['customer_contact']]);
                }
            } else {
                $customer = Customer::create([
                    'code' => 'CUS-'.str()->upper(str()->random(10)),
                    'name' => 'Walk-in customer',
                ]);
            }
            $token = $this->tokens->issue();
            $order = LaundryOrder::create([
                'order_code' => LaundryOrder::formatTrackingCode($token),
                'customer_id' => $customer->id,
                'tracking_token_hash' => $this->tokens->hash($token),
                'subtotal' => 0,
                'total_amount' => 0,
                'total_paid' => 0,
                'outstanding_balance' => 0,
                'payment_status' => 'unpaid',
                'laundry_status' => 'received',
                'received_at' => now(),
                'cashier_id' => $cashierId,
                'instructions' => $data['instructions'] ?? null,
            ]);

            $subtotal = 0;
            $latestHours = 0;
            foreach ($data['services'] ?? [] as $item) {
                $service = Service::whereKey($item['service_id'])->where('is_active', true)->firstOrFail();
                if ($service->pricing_method === 'per_load' && (float) $service->minimum_weight <= 0) {
                    throw ValidationException::withMessages([
                        'services' => "{$service->name} needs a maximum kg per load configured before it can be ordered.",
                    ]);
                }

                $calculation = $this->pricing->calculate($service, (float) $item['quantity']);
                $subtotal += $calculation['line_total'];
                $latestHours = max($latestHours, (int) $service->estimated_processing_hours);
                $order->items()->create([
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'pricing_method' => $service->pricing_method,
                    'quantity' => $calculation['quantity'],
                    'rate' => $calculation['rate'],
                    'minimum_charge' => $service->minimum_charge,
                    'additional_charge' => $calculation['additional_charge'],
                    'line_total' => $calculation['line_total'],
                ]);
            }

            $selectedAddOns = $data['add_ons'] ?? [];
            $selectedAddOnIds = array_values(array_unique(array_map(
                static fn (array $item): int => (int) $item['add_on_id'],
                $selectedAddOns,
            )));
            $addOnQuantities = [];
            foreach ($selectedAddOns as $item) {
                $addOnQuantities[(int) $item['add_on_id']] = (int) $item['quantity'];
            }
            $addOnTotal = 0;
            if ($selectedAddOnIds !== []) {
                $addOns = AddOn::whereIn('id', $selectedAddOnIds)
                    ->where('is_active', true)
                    ->get();

                if ($addOns->count() !== count($selectedAddOnIds)) {
                    throw ValidationException::withMessages([
                        'add_ons' => 'One or more selected add-ons are no longer available.',
                    ]);
                }

                foreach ($addOns as $addOn) {
                    $quantity = $addOnQuantities[$addOn->id];
                    $lineTotal = round((float) $addOn->price * $quantity, 2);
                    $addOnTotal += $lineTotal;
                    $order->addOns()->create([
                        'add_on_id' => $addOn->id,
                        'name' => $addOn->name,
                        'quantity' => $quantity,
                        'price' => $addOn->price,
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            $discount = max(0, (float) ($data['discount'] ?? 0));
            $additional = max(0, (float) ($data['additional_charges'] ?? 0)) + $addOnTotal;
            $total = max(0, round($subtotal - $discount + $additional, 2));
            $isPaid = ($data['payment_status'] ?? null) === 'paid';
            $cashTendered = $isPaid && ($data['payment_method'] ?? null) === 'cash'
                ? (float) ($data['cash_tendered'] ?? 0)
                : null;

            if ($cashTendered !== null && $cashTendered < $total) {
                throw ValidationException::withMessages([
                    'cash_tendered' => 'The amount received must be at least the order total.',
                ]);
            }

            $paid = $isPaid
                ? $total
                : (isset($data['payment_status']) ? 0 : min($total, max(0, (float) ($data['amount_paid'] ?? 0))));
            $order->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'additional_charges' => $additional,
                'total_amount' => $total,
                'total_paid' => $paid,
                'outstanding_balance' => round($total - $paid, 2),
                'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
                'expected_completion_at' => now()->addHours(max(1, $latestHours)),
            ]);

            if ($paid > 0) {
                Payment::create([
                    'laundry_order_id' => $order->id,
                    'method' => $data['payment_method'] ?? 'cash',
                    'amount' => $paid,
                    'cash_tendered' => $cashTendered,
                    'reference_number' => $data['payment_reference'] ?? null,
                    'verification_status' => ($data['payment_method'] ?? 'cash') === 'gcash' ? 'pending' : 'not_required',
                    'received_by' => $cashierId,
                    'paid_at' => now(),
                ]);
            }

            LaundryStatusHistory::create([
                'laundry_order_id' => $order->id,
                'new_status' => 'received',
                'changed_by' => $cashierId,
                'changed_at' => now(),
            ]);

            return [$order->fresh(['customer', 'items', 'addOns', 'payments']), $token];
        });
    }
}
