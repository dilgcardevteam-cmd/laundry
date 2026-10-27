<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Orders\OrderCreator;
use App\Models\BusinessSetting;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\LaundryOrder;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LaundryController extends Controller
{
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
            'customers' => Customer::orderBy('name')->get(['id', 'code', 'name', 'contact_number']),
            'nextOrderCode' => LaundryOrder::nextOrderCode(),
        ]);
    }

    public function storeOrder(Request $request, OrderCreator $creator)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:50'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['required', 'numeric', 'gt:0'],
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
        [$order, $token] = $creator->create($data, $request->user()->id);

        $receiptRoute = route('orders.show', $order, false);
        if (($data['payment_status'] ?? null) === 'paid') {
            $receiptRoute .= '?print=1';
        }

        return redirect($receiptRoute)->with('tracking_token', $token);
    }

    public function orders(Request $request)
    {
        $search = $request->string('search')->toString();
        $columns = [
            'pending' => ['title' => 'Pending', 'statuses' => ['received', 'cancelled']],
            'ongoing' => ['title' => 'Ongoing', 'statuses' => ['washing', 'drying', 'ironing', 'folding']],
            'ready' => ['title' => 'Ready for Release', 'statuses' => ['ready_for_pickup']],
            'released' => ['title' => 'Released', 'statuses' => ['released']],
        ];

        foreach ($columns as $key => &$column) {
            $column['orders'] = LaundryOrder::with('customer')
                ->whereIn('laundry_status', $column['statuses'])
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
                ->paginate(10, ['*'], "{$key}_page")
                ->withQueryString();
        }
        unset($column);

        return view('laundry.orders', [
            'columns' => $columns,
            'canUpdateStatus' => $request->user()->hasRole('administrator', 'manager', 'laundry_staff'),
        ]);
    }

    public function show(LaundryOrder $order)
    {
        return view('laundry.receipt', [
            'order' => $order->load(['customer', 'items', 'payments', 'statusHistory.user']),
            'token' => session('tracking_token'),
            'availableStatuses' => ['received', 'washing', 'drying', 'ironing', 'folding', 'ready_for_pickup', 'released', 'cancelled'],
        ]);
    }

    public function updateStatus(Request $request, LaundryOrder $order, AuditLogger $audit)
    {
        $data = $request->validate(['laundry_status' => ['required', Rule::in(['received', 'washing', 'drying', 'ironing', 'folding', 'ready_for_pickup', 'released', 'cancelled'])], 'remarks' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($order, $data, $request): void {
            $previous = $order->laundry_status;
            $order->update([
                'laundry_status' => $data['laundry_status'],
                'released_at' => $data['laundry_status'] === 'released' ? now() : null,
            ]);
            $order->statusHistory()->create(['previous_status' => $previous, 'new_status' => $data['laundry_status'], 'changed_by' => $request->user()->id, 'remarks' => $data['remarks'] ?? null, 'changed_at' => now()]);
        });
        $audit->record($request, 'order.status_updated', $order, $data);

        return back()->with('success', 'Laundry status updated.');
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

    public function services()
    {
        return view('admin.services', ['services' => Service::latest()->paginate(20)]);
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

    private function validatedServiceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pricing_method' => ['required', Rule::in(['per_kg', 'per_load', 'per_piece', 'fixed'])],
            'rate' => ['required', 'numeric', 'min:0'],
            'minimum_weight' => ['nullable', 'numeric', 'min:0'],
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
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $orders = LaundryOrder::whereBetween('received_at', [$from, $to])->cursor();

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order code', 'Received at', 'Total', 'Paid', 'Balance', 'Payment status', 'Laundry status']);
            foreach ($orders as $order) {
                fputcsv($handle, [$order->order_code, $order->received_at, $order->total_amount, $order->total_paid, $order->outstanding_balance, $order->payment_status, $order->laundry_status]);
            }
            fclose($handle);
        }, 'laundry-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function report(Request $request)
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $orders = LaundryOrder::with('items')->whereBetween('received_at', [$from, $to])->get();

        return view('laundry.report', ['orders' => $orders, 'from' => $from, 'to' => $to, 'expenses' => Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->sum('amount')]);
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
