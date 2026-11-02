<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Orders\OrderCreator;
use App\Domain\Tracking\TrackingToken;
use App\Models\AddOn;
use App\Models\BusinessSetting;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\LaundryOrder;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaundryController extends Controller
{
    private const SERVICE_STATUS_LABELS = [
        'received' => 'On Queue',
        'washing' => 'Washing',
        'drying' => 'Drying',
        'folding' => 'Folding',
        'ready_for_pickup' => 'Ready for Pick up',
        'released' => 'Released',
    ];

    private const QR_SCAN_STATUSES = [
        'received',
        'washing',
        'drying',
        'folding',
        'ready_for_pickup',
    ];

    public function dashboard()
    {
        $salesTrend = collect(range(6, 0))->mapWithKeys(function (int $days): array {
            $date = today()->subDays($days);

            return [$date->format('M d') => (float) LaundryOrder::whereDate('received_at', $date)->sum('total_paid')];
        });

        return view('dashboard', [
            'todaySales' => LaundryOrder::whereDate('received_at', today())->sum('total_paid'),
            'todayOrders' => LaundryOrder::whereDate('received_at', today())->count(),
            'processingOrders' => LaundryOrder::whereIn('laundry_status', ['washing', 'drying', 'ironing', 'folding'])->count(),
            'readyOrders' => LaundryOrder::where('laundry_status', 'ready_for_pickup')->count(),
            'outstanding' => LaundryOrder::sum('outstanding_balance'),
            'overdueOrders' => LaundryOrder::whereNotIn('laundry_status', ['released', 'cancelled'])->where('expected_completion_at', '<', now())->count(),
            'unclaimedOrders' => LaundryOrder::where('laundry_status', 'ready_for_pickup')->where('expected_completion_at', '<', now()->subDays(3))->count(),
            'salesTrend' => $salesTrend,
        ]);
    }

    public function pos()
    {
        return view('laundry.pos', [
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'addOns' => AddOn::where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'code', 'name', 'contact_number']),
        ]);
    }

    public function storeOrder(Request $request, OrderCreator $creator)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:50'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['required', 'numeric', 'gt:0'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', 'distinct', 'exists:add_ons,id'],
            'add_ons.*.quantity' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'additional_charges' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'payment_method' => ['nullable', 'required_if:payment_status,paid', Rule::in(['cash', 'gcash', 'other'])],
            'cash_tendered' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn (): bool => $request->input('payment_status') === 'paid' && $request->input('payment_method') === 'cash'),
            ],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);
        if (empty($data['services']) && empty($data['add_ons'])) {
            throw ValidationException::withMessages([
                'services' => 'Select at least one service or add-on before creating the order.',
            ]);
        }

        [$order, $token] = $creator->create($data, $request->user()->id);

        $receiptRoute = route('orders.show', $order, false);
        if (($data['payment_status'] ?? null) === 'paid') {
            $receiptRoute .= '?print=1';
        }

        return redirect($receiptRoute)
            ->with('tracking_token', $token)
            ->with('clear_pos_draft', true);
    }

    public function orders(Request $request)
    {
        $search = $request->string('search')->toString();
        $claimStatus = $request->input('claim_status') === 'claimed' ? 'claimed' : 'unclaimed';
        $releasedDate = $claimStatus === 'claimed'
            ? $request->validate(['released_date' => ['nullable', 'date_format:Y-m-d']])['released_date'] ?? null
            : null;
        $orders = LaundryOrder::with('customer')
            ->when(
                $claimStatus === 'claimed',
                fn ($query) => $query->where('laundry_status', 'released'),
                fn ($query) => $query->where('laundry_status', '!=', 'released'),
            )
            ->when($releasedDate !== null, fn ($query) => $query->whereDate('released_at', $releasedDate))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('order_code', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString();

        return view('laundry.orders', [
            'orders' => $orders,
            'canUpdateStatus' => $request->user()->hasRole('administrator', 'manager', 'laundry_staff'),
            'availableStatuses' => self::SERVICE_STATUS_LABELS,
            'claimStatus' => $claimStatus,
            'releasedDate' => $releasedDate,
        ]);
    }

    public function show(LaundryOrder $order)
    {
        return view('laundry.receipt', [
            'order' => $order->load(['customer', 'items', 'addOns', 'payments', 'statusHistory.user']),
            'token' => session('tracking_token'),
            'availableStatuses' => self::SERVICE_STATUS_LABELS,
        ]);
    }

    public function updateStatus(Request $request, LaundryOrder $order, AuditLogger $audit)
    {
        $requiresPayment = $order->payment_status === 'unpaid'
            && $request->input('laundry_status') === 'released';
        $data = $request->validate([
            'laundry_status' => ['required', Rule::in(array_keys(self::SERVICE_STATUS_LABELS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'payment_method' => [
                Rule::requiredIf($requiresPayment),
                'nullable',
                Rule::in(['cash', 'gcash', 'other']),
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'cash_tendered' => [
                Rule::requiredIf($requiresPayment && $request->input('payment_method') === 'cash'),
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);
        $paymentAmount = null;
        DB::transaction(function () use ($order, $data, $request, &$paymentAmount): void {
            $lockedOrder = LaundryOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $requiresPayment = $lockedOrder->payment_status === 'unpaid'
                && $data['laundry_status'] === 'released';

            if ($requiresPayment) {
                if (empty($data['payment_method'])) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Select a payment method before releasing this unpaid order.',
                    ]);
                }

                $paymentAmount = (float) $lockedOrder->outstanding_balance;
                if ($paymentAmount <= 0) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'This order has no outstanding balance to pay.',
                    ]);
                }

                $cashTendered = $data['payment_method'] === 'cash'
                    ? (float) ($data['cash_tendered'] ?? 0)
                    : null;
                if ($cashTendered !== null && $cashTendered < $paymentAmount) {
                    throw ValidationException::withMessages([
                        'cash_tendered' => 'The amount received must be at least the order balance.',
                    ]);
                }

                Payment::create([
                    'laundry_order_id' => $lockedOrder->id,
                    'method' => $data['payment_method'],
                    'amount' => $paymentAmount,
                    'cash_tendered' => $cashTendered,
                    'reference_number' => $data['reference_number'] ?? null,
                    'verification_status' => $data['payment_method'] === 'gcash' ? 'pending' : 'not_required',
                    'received_by' => $request->user()->id,
                    'paid_at' => now(),
                ]);
            }

            $previous = $lockedOrder->laundry_status;
            $lockedOrder->update([
                'laundry_status' => $data['laundry_status'],
                'released_at' => $data['laundry_status'] === 'released' ? now() : null,
                ...($requiresPayment ? [
                    'total_paid' => (float) $lockedOrder->total_amount,
                    'outstanding_balance' => 0,
                    'payment_status' => 'paid',
                ] : []),
            ]);
            $lockedOrder->statusHistory()->create(['previous_status' => $previous, 'new_status' => $data['laundry_status'], 'changed_by' => $request->user()->id, 'remarks' => $data['remarks'] ?? null, 'changed_at' => now()]);
        });
        if ($paymentAmount !== null) {
            $audit->record($request, 'payment.created', $order, [
                'amount' => $paymentAmount,
                'method' => $data['payment_method'],
            ]);
        }
        $audit->record($request, 'order.status_updated', $order, $data);

        return back()->with('success', 'Laundry status updated.');
    }

    public function scanStatus(Request $request, TrackingToken $tokens, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'qr_code' => ['required', 'string', 'max:2048'],
        ]);
        $token = $this->tokenFromQrCode($data['qr_code']);
        abort_if($token === null, 422, 'This QR code is not a valid laundry tracking code.');

        $result = DB::transaction(function () use ($token, $tokens, $request): array {
            $order = LaundryOrder::where('tracking_token_hash', $tokens->hash($token))
                ->lockForUpdate()
                ->firstOrFail();
            $storedStatus = $order->laundry_status;
            $currentStatus = match ($order->laundry_status) {
                'ironing' => 'drying',
                'released' => 'ready_for_pickup',
                default => $order->laundry_status,
            };
            $statuses = self::QR_SCAN_STATUSES;
            $currentIndex = array_search($currentStatus, $statuses, true);
            abort_if($currentIndex === false, 422, 'This order cannot advance from its current status.');

            if ($currentIndex === count($statuses) - 1) {
                return [
                    'order' => $order,
                    'previous_status' => $currentStatus,
                    'status' => $currentStatus,
                    'changed' => false,
                ];
            }

            $nextStatus = $statuses[$currentIndex + 1];
            $order->update([
                'laundry_status' => $nextStatus,
                'released_at' => null,
            ]);
            $order->statusHistory()->create([
                'previous_status' => $storedStatus,
                'new_status' => $nextStatus,
                'changed_by' => $request->user()->id,
                'remarks' => 'Advanced by QR scan.',
                'changed_at' => now(),
            ]);

            return [
                'order' => $order,
                'previous_status' => $currentStatus,
                'status' => $nextStatus,
                'changed' => true,
            ];
        });

        if ($result['changed']) {
            $audit->record($request, 'order.status_advanced_by_qr', $result['order'], [
                'previous_status' => $result['previous_status'],
                'new_status' => $result['status'],
            ]);
        }

        return response()->json([
            'order_id' => $result['order']->id,
            'order_code' => $result['order']->order_code,
            'previous_status' => $result['previous_status'],
            'previous_status_label' => self::SERVICE_STATUS_LABELS[$result['previous_status']],
            'laundry_status' => $result['status'],
            'status_label' => self::SERVICE_STATUS_LABELS[$result['status']],
            'changed' => $result['changed'],
        ]);
    }

    public function addPayment(Request $request, LaundryOrder $order, AuditLogger $audit)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'method' => ['required', Rule::in(['cash', 'gcash', 'other'])], 'reference_number' => ['nullable', 'string', 'max:100']]);
        $amount = min((float) $data['amount'], (float) $order->outstanding_balance);
        abort_if($amount <= 0, 422, 'This order has no outstanding balance.');
        DB::transaction(function () use ($order, $data, $amount, $request): void {
            Payment::create(['laundry_order_id' => $order->id, 'method' => $data['method'], 'amount' => $amount, 'reference_number' => $data['reference_number'] ?? null, 'verification_status' => $data['method'] === 'gcash' ? 'pending' : 'not_required', 'received_by' => $request->user()->id, 'paid_at' => now()]);
            $paid = (float) $order->total_paid + $amount;
            $order->update(['total_paid' => $paid, 'outstanding_balance' => max(0, (float) $order->total_amount - $paid), 'payment_status' => $paid >= (float) $order->total_amount ? 'paid' : 'partially_paid']);
        });
        $audit->record($request, 'payment.created', $order, ['amount' => $amount, 'method' => $data['method']]);

        return back()->with('success', 'Payment recorded.');
    }

    public function reversePayment(Request $request, Payment $payment, AuditLogger $audit)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $alreadyReversed = (float) $payment->reversals()->sum('amount');
        $amount = min((float) $data['amount'], (float) $payment->amount - $alreadyReversed);
        abort_if($amount <= 0, 422, 'This payment has no reversible balance.');
        DB::transaction(function () use ($payment, $amount, $data, $request): void {
            PaymentReversal::create(['payment_id' => $payment->id, 'amount' => $amount, 'reason' => $data['reason'], 'created_by' => $request->user()->id, 'reversed_at' => now()]);
            $order = $payment->order()->lockForUpdate()->first();
            $paid = (float) $order->total_paid - $amount;
            $order->update(['total_paid' => $paid, 'outstanding_balance' => (float) $order->total_amount - $paid, 'payment_status' => $paid <= 0 ? 'unpaid' : 'partially_paid']);
        });
        $audit->record($request, 'payment.reversed', $payment, ['amount' => $amount, 'reason' => $data['reason']]);

        return back()->with('success', 'Payment reversal recorded.');
    }

    public function verifyPayment(Request $request, Payment $payment, AuditLogger $audit)
    {
        abort_unless($payment->method === 'gcash' && $payment->verification_status === 'pending', 422, 'This payment is not awaiting GCash verification.');
        $payment->update(['verification_status' => 'verified']);
        $audit->record($request, 'payment.verified', $payment);

        return back()->with('success', 'GCash payment verified.');
    }

    private function tokenFromQrCode(string $qrCode): ?string
    {
        $qrCode = trim($qrCode);
        $path = parse_url($qrCode, PHP_URL_PATH);

        if (is_string($path) && preg_match('~(?:^|/)track/([A-Za-z0-9]{16}|[A-Za-z0-9]{64})/?$~', $path, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^(?:LND-)?[A-Za-z0-9]{4}(?:-?[A-Za-z0-9]{4}){3}$/i', $qrCode) === 1) {
            return str_replace(['LND-', '-'], '', strtoupper($qrCode));
        }

        if (preg_match('/^[A-Za-z0-9]{16}$|^[A-Fa-f0-9]{64}$/', $qrCode) === 1) {
            return $qrCode;
        }

        return null;
    }

    public function services()
    {
        return view('admin.services', [
            'services' => Service::latest()->paginate(20),
            'addOns' => AddOn::latest()->paginate(20, ['*'], 'add_ons_page'),
        ]);
    }

    public function storeService(Request $request)
    {
        $data = $this->validatedServiceData($request);
        Service::create([...$data, 'slug' => str()->slug($data['name']).'-'.str()->random(6)]);

        return back()->with('success', 'Service created.');
    }

    public function updateService(Request $request, Service $service)
    {
        $service->update($this->validatedServiceData($request));

        return back()->with('success', 'Service updated.');
    }

    public function deleteService(Service $service)
    {
        $service->delete();

        return back()->with('success', 'Service deleted.');
    }

    public function storeAddOn(Request $request): RedirectResponse
    {
        AddOn::create($this->validatedAddOnData($request));

        return back()->with('success', 'Add-on created.');
    }

    public function updateAddOn(Request $request, AddOn $addOn): RedirectResponse
    {
        $addOn->update($this->validatedAddOnData($request));

        return back()->with('success', 'Add-on updated.');
    }

    public function toggleAddOn(AddOn $addOn): RedirectResponse
    {
        $addOn->update(['is_active' => ! $addOn->is_active]);

        return back()->with('success', 'Add-on status updated.');
    }

    public function deleteAddOn(AddOn $addOn): RedirectResponse
    {
        $addOn->delete();

        return back()->with('success', 'Add-on deleted.');
    }

    /**
     * @return array{name: string, price: numeric-string|int|float, is_active?: bool}
     */
    private function validatedAddOnData(Request $request): array
    {
        $validated = $request->validate([
            'add_on_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        return [
            'name' => $validated['add_on_name'],
            'price' => $validated['price'],
        ];
    }

    private function validatedServiceData(Request $request): array
    {
        $minimumQuantityRules = $request->input('pricing_method') === 'per_load'
            ? ['required', 'numeric', 'gt:0']
            : ['nullable', 'numeric', 'min:0'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pricing_method' => ['required', Rule::in(['per_kg', 'per_load', 'per_piece', 'fixed'])],
            'rate' => ['required', 'numeric', 'min:0'],
            'minimum_weight' => $minimumQuantityRules,
            'minimum_charge' => ['nullable', 'numeric', 'min:0'],
            'additional_charge' => ['nullable', 'numeric', 'min:0'],
            'estimated_processing_hours' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['additional_charge'] = $data['additional_charge'] ?? 0;

        return $data;
    }

    public function toggleService(Service $service)
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('success', 'Service status updated.');
    }

    public function users()
    {
        return view('admin.users', ['users' => User::latest()->paginate(20)]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'role' => ['required', Rule::in(['administrator', 'manager', 'cashier', 'laundry_staff'])], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        User::create($data + ['email_verified_at' => now()]);

        return back()->with('success', 'User created.');
    }

    public function exportReport(Request $request)
    {
        $filters = $this->validatedReportFilters($request);
        $query = $this->reportOrderQuery($filters['from'], $filters['to'], $filters['payment_status'], $filters['service_id'], $filters['add_on_id']);
        $selectedService = $filters['service_id'] === null ? null : Service::find($filters['service_id']);
        $selectedAddOn = $filters['add_on_id'] === null ? null : AddOn::withTrashed()->find($filters['add_on_id']);
        $summary = [
            'orders' => (clone $query)->count(),
            'paid_orders' => (clone $query)->where('payment_status', 'paid')->count(),
            'unpaid_orders' => (clone $query)->where('payment_status', '!=', 'paid')->count(),
            'order_value' => (clone $query)->sum('total_amount'),
            'collected' => (clone $query)->sum('total_paid'),
            'outstanding' => (clone $query)->sum('outstanding_balance'),
            'expenses' => Expense::whereBetween('expense_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])->sum('amount'),
        ];
        $summary['net_collected'] = $summary['collected'] - $summary['expenses'];
        $orders = $query->orderBy('received_at')->cursor();

        return response()->streamDownload(function () use ($orders, $summary, $filters, $selectedService, $selectedAddOn): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Laundry sales report']);
            fputcsv($handle, ['From', $filters['from']->toDateString(), 'To', $filters['to']->toDateString(), 'Payment status', $filters['payment_status'] ?? 'all', 'Service', $selectedService?->name ?? 'all', 'Add-on', $selectedAddOn?->name ?? 'all']);
            fputcsv($handle, []);
            fputcsv($handle, ['Analysis']);
            fputcsv($handle, ['Orders', $summary['orders']]);
            fputcsv($handle, ['Paid orders', $summary['paid_orders']]);
            fputcsv($handle, ['Unpaid orders', $summary['unpaid_orders']]);
            fputcsv($handle, ['Order value', $summary['order_value']]);
            fputcsv($handle, ['Collected', $summary['collected']]);
            fputcsv($handle, ['Outstanding', $summary['outstanding']]);
            fputcsv($handle, ['Expenses', $summary['expenses']]);
            fputcsv($handle, ['Net collected after expenses', $summary['net_collected']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Order code', 'Received at', 'Total', 'Collected', 'Balance', 'Payment status', 'Laundry status', 'Staff assisted / booked by']);
            foreach ($orders as $order) {
                fputcsv($handle, [$order->order_code, $order->received_at, $order->total_amount, $order->total_paid, $order->outstanding_balance, $order->payment_status, $order->laundry_status, $order->cashier?->name ?? 'Unassigned']);
            }
            fclose($handle);
        }, 'laundry-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function report(Request $request)
    {
        $filters = $this->validatedReportFilters($request);
        $orders = $this->reportOrderQuery($filters['from'], $filters['to'], $filters['payment_status'], $filters['service_id'], $filters['add_on_id'])
            ->with(['items', 'addOns'])
            ->orderBy('received_at')
            ->get();
        $expenses = Expense::whereBetween('expense_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])->sum('amount');
        $serviceBreakdown = [];
        $addOnBreakdown = [];
        $reportServices = Service::withTrashed()
            ->when($filters['service_id'] !== null, fn (Builder $query): Builder => $query->whereKey($filters['service_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'pricing_method']);
        $reportAddOns = AddOn::withTrashed()
            ->when($filters['add_on_id'] !== null, fn (Builder $query): Builder => $query->whereKey($filters['add_on_id']))
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($reportServices as $service) {
            $serviceBreakdown['service:'.$service->id] = [
                'name' => $service->name,
                'unit' => $this->serviceQuantityUnit($service->pricing_method),
                'orders' => 0,
                'quantity' => 0,
                'amount' => 0,
            ];
        }

        foreach ($reportAddOns as $addOn) {
            $addOnBreakdown['add-on:'.$addOn->id] = [
                'name' => $addOn->name,
                'orders' => 0,
                'quantity' => 0,
                'amount' => 0,
            ];
        }

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = $item->service_id === null ? 'snapshot:'.$item->service_name : 'service:'.$item->service_id;
                $serviceBreakdown[$key] ??= [
                    'name' => $item->service_name,
                    'unit' => $this->serviceQuantityUnit($item->pricing_method),
                    'orders' => [],
                    'quantity' => 0,
                    'amount' => 0,
                ];
                $serviceBreakdown[$key]['unit'] = $this->serviceQuantityUnit($item->pricing_method);
                if (! is_array($serviceBreakdown[$key]['orders'])) {
                    $serviceBreakdown[$key]['orders'] = [];
                }
                $serviceBreakdown[$key]['orders'][$order->id] = true;
                $serviceBreakdown[$key]['quantity'] += (float) $item->quantity;
                $serviceBreakdown[$key]['amount'] += (float) $item->line_total;
            }

            foreach ($order->addOns as $addOn) {
                $key = $addOn->add_on_id === null ? 'snapshot:'.$addOn->name : 'add-on:'.$addOn->add_on_id;
                $addOnBreakdown[$key] ??= [
                    'name' => $addOn->name,
                    'orders' => [],
                    'quantity' => 0,
                    'amount' => 0,
                ];
                if (! is_array($addOnBreakdown[$key]['orders'])) {
                    $addOnBreakdown[$key]['orders'] = [];
                }
                $addOnBreakdown[$key]['orders'][$order->id] = true;
                $addOnBreakdown[$key]['quantity'] += $addOn->quantity;
                $addOnBreakdown[$key]['amount'] += (float) $addOn->line_total;
            }
        }

        foreach ($serviceBreakdown as &$serviceSummary) {
            if (is_array($serviceSummary['orders'])) {
                $serviceSummary['orders'] = count($serviceSummary['orders']);
            }
        }
        unset($serviceSummary);

        foreach ($addOnBreakdown as &$addOnSummary) {
            if (is_array($addOnSummary['orders'])) {
                $addOnSummary['orders'] = count($addOnSummary['orders']);
            }
        }
        unset($addOnSummary);

        uasort($serviceBreakdown, fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']));
        uasort($addOnBreakdown, fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']));
        $summary = [
            'orders' => $orders->count(),
            'paid_orders' => $orders->where('payment_status', 'paid')->count(),
            'unpaid_orders' => $orders->where('payment_status', '!=', 'paid')->count(),
            'order_value' => $orders->sum('total_amount'),
            'collected' => $orders->sum('total_paid'),
            'outstanding' => $orders->sum('outstanding_balance'),
            'expenses' => $expenses,
            'net_collected' => $orders->sum('total_paid') - $expenses,
        ];

        return view('laundry.report', [
            'orders' => $orders,
            'from' => $filters['from'],
            'to' => $filters['to'],
            'paymentStatus' => $filters['payment_status'],
            'serviceId' => $filters['service_id'],
            'services' => Service::orderBy('name')->get(['id', 'name']),
            'addOnId' => $filters['add_on_id'],
            'addOns' => AddOn::withTrashed()->orderBy('name')->get(['id', 'name']),
            'serviceBreakdown' => $serviceBreakdown,
            'addOnBreakdown' => $addOnBreakdown,
            'summary' => $summary,
        ]);
    }

    /**
     * @return array{from: Carbon, to: Carbon, payment_status: ?string, service_id: ?int, add_on_id: ?int}
     */
    private function validatedReportFilters(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'add_on_id' => ['nullable', 'integer', Rule::exists('add_ons', 'id')],
        ]);

        return [
            'from' => isset($filters['from']) ? Carbon::parse($filters['from'], config('app.timezone'))->startOfDay() : now()->startOfMonth(),
            'to' => isset($filters['to']) ? Carbon::parse($filters['to'], config('app.timezone'))->endOfDay() : now()->endOfDay(),
            'payment_status' => $filters['payment_status'] ?? null,
            'service_id' => isset($filters['service_id']) ? (int) $filters['service_id'] : null,
            'add_on_id' => isset($filters['add_on_id']) ? (int) $filters['add_on_id'] : null,
        ];
    }

    private function reportOrderQuery(Carbon $from, Carbon $to, ?string $paymentStatus, ?int $serviceId, ?int $addOnId): Builder
    {
        $query = LaundryOrder::with('cashier')->whereBetween('received_at', [$from, $to]);

        if ($paymentStatus === 'paid') {
            $query->where('payment_status', $paymentStatus);
        } elseif ($paymentStatus === 'unpaid') {
            $query->where('payment_status', '!=', 'paid');
        }

        if ($serviceId !== null) {
            $query->whereHas('items', fn (Builder $itemQuery): Builder => $itemQuery->where('service_id', $serviceId));
        }

        if ($addOnId !== null) {
            $query->whereHas('addOns', fn (Builder $addOnQuery): Builder => $addOnQuery->where('add_on_id', $addOnId));
        }

        return $query;
    }

    private function serviceQuantityUnit(?string $pricingMethod): string
    {
        return match ($pricingMethod) {
            'per_kg' => 'kg',
            'per_piece' => 'pieces',
            'per_load' => 'loads',
            'fixed' => 'units',
            default => 'units',
        };
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate(['category' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:1000'], 'amount' => ['required', 'numeric', 'gt:0'], 'expense_date' => ['required', 'date']]);
        Expense::create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Expense recorded.');
    }

    public function openShift(Request $request)
    {
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        abort_if(CashierShift::where('user_id', $request->user()->id)->whereNull('closed_at')->exists(), 422, 'You already have an open shift.');
        CashierShift::create(['user_id' => $request->user()->id, 'opened_at' => now(), 'opening_cash' => $data['opening_cash'], 'notes' => $data['notes'] ?? null]);

        return back()->with('success', 'Cashier shift opened.');
    }

    public function closeShift(Request $request, CashierShift $shift)
    {
        abort_unless($shift->user_id === $request->user()->id && $shift->closed_at === null, 403);
        $data = $request->validate(['closing_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $cashCollected = Payment::where('received_by', $shift->user_id)->whereBetween('paid_at', [$shift->opened_at, now()])->sum('amount');
        $expenses = Expense::where('created_by', $shift->user_id)->whereBetween('created_at', [$shift->opened_at, now()])->sum('amount');
        $shift->update(['closed_at' => now(), 'closing_cash' => $data['closing_cash'], 'expected_cash' => (float) $shift->opening_cash + $cashCollected - $expenses, 'notes' => $data['notes'] ?? $shift->notes]);

        return back()->with('success', 'Cashier shift closed.');
    }

    public function shifts(Request $request)
    {
        return view('laundry.shifts', [
            'openShift' => CashierShift::where('user_id', $request->user()->id)->whereNull('closed_at')->latest()->first(),
            'shifts' => CashierShift::with('user')->latest('opened_at')->paginate(20),
        ]);
    }

    public function settings()
    {
        return view('admin.settings', ['settings' => BusinessSetting::orderBy('key')->get()]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'receipt_terms' => ['nullable', 'string', 'max:1000'],
            'business_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $logo = $request->file('business_logo');
        unset($data['business_logo']);

        foreach ($data as $key => $value) {
            BusinessSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if ($logo) {
            $oldLogo = BusinessSetting::where('key', 'business_logo')->value('value');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }

            BusinessSetting::updateOrCreate(
                ['key' => 'business_logo'],
                ['value' => $logo->store('business', 'public')],
            );
        }

        return back()->with('success', 'Business settings updated.');
    }
}
