<?php

use App\Models\AddOn;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\LaundryOrder;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

function verifiedRoleUser(string $role): User
{
    return User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
}

it('filters and exports sales reports with staff attribution and analysis', function () {
    $manager = verifiedRoleUser('manager');
    $cashier = verifiedRoleUser('cashier');
    $customer = Customer::create(['code' => 'CUS-REPORT', 'name' => 'Report Customer']);
    $receivedAt = now()->subDay();
    $selectedService = Service::create([
        'name' => 'Report Wash',
        'slug' => 'report-wash',
        'pricing_method' => 'per_kg',
        'rate' => 30,
        'is_active' => true,
    ]);
    $otherService = Service::create([
        'name' => 'Report Dry',
        'slug' => 'report-dry',
        'pricing_method' => 'per_kg',
        'rate' => 25,
        'is_active' => true,
    ]);
    $selectedAddOn = AddOn::create(['name' => 'Report Detergent', 'price' => 10, 'is_active' => true]);
    $otherAddOn = AddOn::create(['name' => 'Report Softener', 'price' => 15, 'is_active' => true]);

    foreach ([
        ['code' => 'LND-REPORT-PAID', 'total' => 120, 'paid' => 120, 'balance' => 0, 'status' => 'paid', 'service' => $selectedService, 'add_on' => $selectedAddOn],
        ['code' => 'LND-REPORT-UNPAID', 'total' => 80, 'paid' => 20, 'balance' => 60, 'status' => 'partially_paid', 'service' => $selectedService, 'add_on' => $selectedAddOn],
        ['code' => 'LND-REPORT-OTHER-SERVICE', 'total' => 40, 'paid' => 40, 'balance' => 0, 'status' => 'paid', 'service' => $otherService, 'add_on' => $otherAddOn],
    ] as $index => $orderData) {
        $order = LaundryOrder::create([
            'order_code' => $orderData['code'],
            'customer_id' => $customer->id,
            'tracking_token_hash' => hash('sha256', $orderData['code']),
            'subtotal' => $orderData['total'],
            'total_amount' => $orderData['total'],
            'total_paid' => $orderData['paid'],
            'outstanding_balance' => $orderData['balance'],
            'payment_status' => $orderData['status'],
            'laundry_status' => 'received',
            'received_at' => $receivedAt->copy()->addMinutes($index),
            'cashier_id' => $cashier->id,
        ]);
        $order->items()->create([
            'service_id' => $orderData['service']->id,
            'service_name' => $orderData['service']->name,
            'pricing_method' => $orderData['service']->pricing_method,
            'quantity' => 1,
            'rate' => $orderData['total'],
            'line_total' => $orderData['total'],
        ]);
        $order->addOns()->create([
            'add_on_id' => $orderData['add_on']->id,
            'name' => $orderData['add_on']->name,
            'quantity' => 1,
            'price' => $orderData['add_on']->price,
            'line_total' => $orderData['add_on']->price,
        ]);
    }

    Expense::create([
        'category' => 'Supplies',
        'description' => 'Report period supplies',
        'amount' => 15,
        'expense_date' => $receivedAt->toDateString(),
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->get('/reports?from='.$receivedAt->toDateString().'&to='.$receivedAt->toDateString().'&payment_status=paid&service_id='.$selectedService->id.'&add_on_id='.$selectedAddOn->id)
        ->assertOk()
        ->assertSee('Paid orders')
        ->assertSee('Unpaid orders')
        ->assertSee('Order value')
        ->assertSee('Net collected after expenses')
        ->assertSee('Per-service report')
        ->assertSee('Per add-on report')
        ->assertSee('viewBox="0 0 24 24" fill="none" stroke="currentColor"', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertSee('Report Wash')
        ->assertSee('Report Detergent')
        ->assertSee('LND-REPORT-PAID')
        ->assertDontSee('LND-REPORT-UNPAID')
        ->assertDontSee('LND-REPORT-OTHER-SERVICE')
        ->assertSee($cashier->name)
        ->assertSee('₱105.00')
        ->assertViewHas('serviceBreakdown', fn (array $breakdown): bool => count($breakdown) === 1 && reset($breakdown)['amount'] === 120.0)
        ->assertViewHas('addOnBreakdown', fn (array $breakdown): bool => count($breakdown) === 1 && reset($breakdown)['amount'] === 10.0);

    $export = $this->actingAs($manager)
        ->get('/reports/export?from='.$receivedAt->toDateString().'&to='.$receivedAt->toDateString().'&payment_status=unpaid&service_id='.$selectedService->id.'&add_on_id='.$selectedAddOn->id)
        ->assertOk()
        ->assertDownload('laundry-report.csv');

    expect($export->streamedContent())
        ->toContain('Net collected after expenses')
        ->toContain('LND-REPORT-UNPAID')
        ->toContain('Report Wash')
        ->toContain('Report Detergent')
        ->toContain($cashier->name)
        ->not->toContain('LND-REPORT-PAID')
        ->not->toContain('LND-REPORT-OTHER-SERVICE');
});

it('shows add-on cards with zero sales when there are no matching orders in the report period', function () {
    $manager = verifiedRoleUser('manager');
    AddOn::create(['name' => 'Fabric Conditioner', 'price' => 20, 'is_active' => true]);
    AddOn::create(['name' => 'Extra Dry (10 Mins)', 'price' => 30, 'is_active' => true]);
    AddOn::create(['name' => 'Detergent', 'price' => 10, 'is_active' => true]);
    Service::create([
        'name' => 'No Sales Service',
        'slug' => 'no-sales-service',
        'pricing_method' => 'per_piece',
        'rate' => 40,
        'is_active' => true,
    ]);
    Service::create([
        'name' => 'Shoes',
        'slug' => 'no-sales-shoes',
        'pricing_method' => 'per_piece',
        'rate' => 100,
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->get('/reports?from=2026-11-01&to=2026-11-02')
        ->assertOk()
        ->assertSee('Fabric Conditioner')
        ->assertSee('data-report-icon="shoes"', false)
        ->assertSee('data-report-icon="extra-dry"', false)
        ->assertSee('data-report-icon="fabric-conditioner"', false)
        ->assertSee('data-report-icon="detergent"', false)
        ->assertSee('No Sales Service')
        ->assertViewHas('addOnBreakdown', function (array $breakdown): bool {
            $fabricConditioner = collect($breakdown)->firstWhere('name', 'Fabric Conditioner');

            return $fabricConditioner !== null
                && $fabricConditioner['orders'] === 0
                && $fabricConditioner['quantity'] === 0
                && $fabricConditioner['amount'] === 0;
        })
        ->assertViewHas('serviceBreakdown', function (array $breakdown): bool {
            $noSalesService = collect($breakdown)->firstWhere('name', 'No Sales Service');

            return $noSalesService !== null
                && $noSalesService['unit'] === 'pieces'
                && $noSalesService['orders'] === 0
                && $noSalesService['quantity'] === 0
                && $noSalesService['amount'] === 0;
        })
        ->assertSee('Pieces');
});

it('includes add-on sales from the current Philippine business day in the default report', function () {
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Manila');
    config(['app.timezone' => 'Asia/Manila']);
    Carbon::setTestNow(Carbon::parse('2026-11-03 00:30:00', 'Asia/Manila'));

    try {
        $manager = verifiedRoleUser('manager');
        $customer = Customer::create(['code' => 'CUS-REPORT-TODAY', 'name' => 'Report Today Customer']);
        $addOn = AddOn::create(['name' => 'Report Today Detergent', 'price' => 10, 'is_active' => true]);
        $order = LaundryOrder::create([
            'order_code' => 'LND-REPORT-TODAY',
            'customer_id' => $customer->id,
            'tracking_token_hash' => hash('sha256', 'report-today-token'),
            'subtotal' => 120,
            'total_amount' => 120,
            'total_paid' => 120,
            'outstanding_balance' => 0,
            'payment_status' => 'paid',
            'laundry_status' => 'received',
            'received_at' => Carbon::parse('2026-11-03 00:11:00', 'Asia/Manila'),
        ]);
        $order->addOns()->create([
            'add_on_id' => $addOn->id,
            'name' => $addOn->name,
            'quantity' => 2,
            'price' => $addOn->price,
            'line_total' => 20,
        ]);

        $this->actingAs($manager)
            ->get('/reports')
            ->assertOk()
            ->assertViewHas('from', fn (Carbon $from): bool => $from->toDateString() === '2026-11-01')
            ->assertViewHas('to', fn (Carbon $to): bool => $to->toDateString() === '2026-11-03')
            ->assertViewHas('addOnBreakdown', function (array $breakdown): bool {
                $detergent = collect($breakdown)->firstWhere('name', 'Report Today Detergent');

                return $detergent !== null
                    && $detergent['orders'] === 1
                    && $detergent['quantity'] === 2
                    && $detergent['amount'] === 20.0;
            });
    } finally {
        Carbon::setTestNow();
        date_default_timezone_set($originalTimezone);
    }
});

it('allows administrators to access service and settings management', function () {
    $user = verifiedRoleUser('administrator');
    Service::create([
        'name' => 'Accordion Wash',
        'slug' => 'accordion-wash',
        'pricing_method' => 'per_kg',
        'rate' => 30,
        'is_active' => true,
    ]);
    $pieceService = Service::create([
        'name' => 'Accordion Shoe Cleaning',
        'slug' => 'accordion-shoe-cleaning',
        'pricing_method' => 'per_piece',
        'rate' => 100,
        'minimum_weight' => 2,
        'is_active' => true,
    ]);
    $loadService = Service::create([
        'name' => 'Accordion Load Cleaning',
        'slug' => 'accordion-load-cleaning',
        'pricing_method' => 'per_load',
        'rate' => 80,
        'minimum_weight' => 4,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/admin/services')
        ->assertOk()
        ->assertSee('Add service')
        ->assertSee('open-modal', false)
        ->assertSee('add-laundry-service', false)
        ->assertSee('flex min-h-dvh items-center justify-center overflow-y-auto', false)
        ->assertSee('style="display: none;"', false)
        ->assertSee('my-auto max-h-[calc(100dvh-2rem)] w-full overflow-y-auto', false)
        ->assertSee('Minimum pieces')
        ->assertSee('id="service-minimum-'.$pieceService->id.'" name="minimum_weight"', false)
        ->assertSee("per_piece: 'Minimum pieces'", false)
        ->assertSee('Maximum kg per load')
        ->assertSee('service-minimum-'.$loadService->id)
        ->assertSee("per_load: 'Maximum kg per load'", false)
        ->assertDontSee('name="additional_charge"', false)
        ->assertDontSee('name="estimated_processing_hours"', false)
        ->assertSee('<details>', false)
        ->assertSee('<summary', false)
        ->assertSee('Save changes');
    $this->actingAs($user)->get('/admin/settings')->assertOk();
});

it('requires a positive kg-per-load capacity when saving a per-load service', function () {
    $user = verifiedRoleUser('administrator');

    $this->actingAs($user)->post('/admin/services', [
        'name' => 'Invalid Load Service',
        'pricing_method' => 'per_load',
        'rate' => 80,
    ])->assertSessionHasErrors('minimum_weight');
});

it('shows per-load services with their kilogram capacity in the POS', function () {
    $user = verifiedRoleUser('cashier');
    Service::create([
        'name' => 'Capacity Test Wash',
        'slug' => 'capacity-test-wash',
        'pricing_method' => 'per_load',
        'rate' => 80,
        'minimum_weight' => 8,
        'is_active' => true,
    ]);

    $this->actingAs($user)->get('/pos')
        ->assertOk()
        ->assertSee('8.00 kg per load')
        ->assertSee('money(Number(row.dataset.rate))} / load', false)
        ->assertSee("per_load: 'kg'", false);
});

it('defaults an omitted service additional charge to zero', function () {
    $user = verifiedRoleUser('administrator');

    $this->actingAs($user)->post('/admin/services', [
        'name' => 'Shoes',
        'pricing_method' => 'per_piece',
        'rate' => 100,
        'minimum_charge' => 100,
    ])->assertSessionHasNoErrors();

    expect((float) Service::where('name', 'Shoes')->first()->additional_charge)->toBe(0.0);
});

it('denies cashiers access to administrator routes', function () {
    $user = verifiedRoleUser('cashier');

    $this->actingAs($user)->get('/admin/services')->assertForbidden();
    $this->actingAs($user)->get('/reports')->assertForbidden();
});

it('explains that a random tracking code is assigned when an order is saved', function () {
    $user = verifiedRoleUser('cashier');
    Service::create([
        'name' => 'Quantity Modal Wash',
        'slug' => 'quantity-modal-wash',
        'pricing_method' => 'per_kg',
        'rate' => 40,
        'is_active' => true,
    ]);
    Service::create([
        'name' => 'Quantity Modal Ironing',
        'slug' => 'quantity-modal-ironing',
        'pricing_method' => 'per_piece',
        'rate' => 25,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get('/pos')
        ->assertOk();

    $response
        ->assertDontSee('Tracking code (laundry code):')
        ->assertDontSee('Assigned after saving')
        ->assertDontSee('A random code is generated when you save this order')
        ->assertSee('Customer details')
        ->assertSee('Customer name')
        ->assertSee('name="customer_name"', false)
        ->assertSee('for="instructions" class="block text-sm font-medium text-slate-700">Laundry instructions or remarks (optional)</label>', false)
        ->assertSee('id="instructions" name="instructions"', false)
        ->assertSee("const draftStorageKey = 'laundry-pos-order-draft'", false)
        ->assertSee('const clearSavedDraft = () =>', false)
        ->assertSee("document.addEventListener('click', event =>", false)
        ->assertSee('destination.pathname !== window.location.pathname', false)
        ->assertSee('sessionStorage.getItem(draftStorageKey) ?? localStorage.getItem(draftStorageKey)', false)
        ->assertSee('savedDraft.draftVersion !== 2', false)
        ->assertSee("paymentStatus: ''", false)
        ->assertSee('draftVersion: 2', false)
        ->assertSee('aria-controls="service-list" data-pos-section="services"', false)
        ->assertSee('aria-controls="addon-list" data-pos-section="addons"', false)
        ->assertSee('pos-section-toggle--services', false)
        ->assertSee('pos-section-toggle--addons', false)
        ->assertSee('class="pos-section-content border-t border-slate-200 bg-slate-50"', false)
        ->assertSee('const restoreDraft = () =>', false)
        ->assertSee('collapsedSections: {}', false)
        ->assertSee('savedDraft.collapsedSections ?? {}', false)
        ->assertSee("content.classList.toggle('is-collapsed', isCollapsed)", false)
        ->assertSee('content.inert = isCollapsed', false)
        ->assertSee("setSectionCollapsed('addons', true)", false)
        ->assertSee("setSectionCollapsed('services', false)", false)
        ->assertSee('const restoreOpenModal = () =>', false)
        ->assertSee('modalState = savedDraft?.modalState ?? null', false)
        ->assertSee("type: 'service-quantity'", false)
        ->assertSee("type: 'addon-quantity'", false)
        ->assertSee("type: 'cash-payment'", false)
        ->assertSee("type: 'order-confirmation'", false)
        ->assertSee('restoreOpenModal();', false)
        ->assertSee('localStorage.setItem(draftStorageKey, serializedDraft)', false)
        ->assertSee('type="text" inputmode="text"', false)
        ->assertSee('name="customer_contact" type="tel" inputmode="tel"', false)
        ->assertSee('required class="mt-1 w-full', false)
        ->assertSee('Payment status')
        ->assertSee('<option value="" disabled selected>Select payment status</option>', false)
        ->assertSee('id="payment_status" name="payment_status" required', false)
        ->assertSee('Enter amount received')
        ->assertSee('id="cash-modal-change" class="text-xl" aria-live="polite" aria-atomic="true"', false)
        ->assertSee("cashReceivedInput.addEventListener('input', () =>", false)
        ->assertSee('cashModalChange.textContent = money(tendered - total);', false)
        ->assertSee('id="floating-order-summary-button"', false)
        ->assertSee('class="floating-order-summary-button is-collapsed', false)
        ->assertSee('aria-label="Expand order summary button" aria-expanded="false"', false)
        ->assertSee('class="floating-order-summary-button-details" hidden', false)
        ->assertSee("floatingSummaryButton.classList.contains('is-collapsed')", false)
        ->assertSee('floatingSummaryButton.addEventListener(\'blur\', collapseFloatingSummaryButton)', false)
        ->assertSee('id="floating-order-summary-modal"', false)
        ->assertDontSee('id="floating-order-summary-modal" class="order-summary-dialog w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-3xl border-0 bg-white p-0 text-slate-800 shadow-2xl" aria-labelledby="floating-order-summary-title" open', false)
        ->assertSee('id="floating-order-summary-breakdown"', false)
        ->assertSee('class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4"', false)
        ->assertSee('class="shrink-0 border-t border-slate-200 bg-white p-4"', false)
        ->assertSee('class="order-summary-scroll-area min-h-0 flex-1 overflow-y-auto overscroll-contain p-5" tabindex="0" aria-label="Scrollable order summary and checkout"', false)
        ->assertSee('id="floating-order-summary-checkout" type="submit" form="order-form"', false)
        ->assertSee('id="floating-payment-status"', false)
        ->assertSee('id="floating-payment-method-field"', false)
        ->assertSee('id="floating-payment-reference-field"', false)
        ->assertSee('floatingPaymentStatus.addEventListener(\'change\'', false)
        ->assertSee('floatingPaymentMethod.addEventListener(\'change\'', false)
        ->assertSee('floatingPaymentReference.addEventListener(\'input\'', false)
        ->assertSee("document.querySelectorAll('.tracking-submit').forEach(button =>", false)
        ->assertSee("document.getElementById('floating-order-summary-checkout').addEventListener('click'", false)
        ->assertSee('floatingSummaryModal.showModal()', false)
        ->assertSee('const lockPageScroll = () =>', false)
        ->assertSee("document.body.style.position = 'fixed'", false)
        ->assertSee('const unlockPageScroll = () =>', false)
        ->assertSee('unlockPageScroll();', false)
        ->assertSee('orderSummaryScrollArea.scrollTop = 0', false)
        ->assertSee("orderSummaryScrollArea.addEventListener('pointerdown', event =>", false)
        ->assertSee("event.pointerType !== 'mouse'", false)
        ->assertSee('orderSummaryScrollArea.scrollTop = summaryDragStartScrollTop - dragDistance', false)
        ->assertSee('const refreshFloatingOrderSummary = () =>', false)
        ->assertSee('class="tracking-submit w-full rounded-xl px-5 py-3 font-semibold shadow-lg transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" disabled', false)
        ->assertSee('id="service-quantity-modal"', false)
        ->assertSee('id="addon-quantity-decrement"', false)
        ->assertSee('id="addon-quantity-increment"', false)
        ->assertSee("!['per_kg', 'per_load'].includes(row.dataset.method)", false)
        ->assertSee("classList.toggle('hidden', !hasWholeUnitQuantity)", false)
        ->assertSee('Confirm quantity')
        ->assertSee('service-quantity hidden', false)
        ->assertSee('service-quantity-trigger', false)
        ->assertSee('service-quantity-display', false)
        ->assertSee('/ min</span>', false)
        ->assertSee('1 piece minimum')
        ->assertSee('1.00 kg minimum')
        ->assertDontSee('>Enter quantity</button>', false)
        ->assertSee('--service-accent: #0284c7; --service-tint: #f0f9ff;', false)
        ->assertSee('--service-accent: #7c3aed; --service-tint: #f5f3ff;', false)
        ->assertSee('grid-cols-[auto_minmax(0,1fr)_auto]', false)
        ->assertSee('col-start-3 row-start-1', false)
        ->assertSee('class="pos-accordion relative z-10 mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"', false)
        ->assertSee('class="pos-section-toggle pos-section-toggle--services group flex w-full items-center justify-between gap-4 p-4 text-left', false)
        ->assertDontSee('target="_blank"', false);

    $content = $response->getContent();
    expect(strpos($content, 'Order summary'))->toBeLessThan(strpos($content, 'Payment status'))
        ->and(strpos($content, 'Payment status'))->toBeLessThan(strpos($content, 'Create order'))
        ->and($content)->toContain('id="order-confirmation-modal"')
        ->toContain('class="order-confirmation-list mt-5 max-h-48 space-y-3 overflow-y-auto rounded-2xl bg-slate-50 p-4 text-sm"')
        ->toContain('More items below — scroll to see the rest')
        ->toContain('orderConfirmationServices.scrollHeight > orderConfirmationServices.clientHeight')
        ->toContain("orderConfirmationServices.addEventListener('pointerdown', event =>")
        ->toContain('orderConfirmationServices.scrollTop = confirmationDragStartScrollTop - dragDistance')
        ->toContain('orderConfirmationServices.classList.add(\'is-dragging\')')
        ->toContain('orderConfirmationPayment.textContent = paymentStatus.options[paymentStatus.selectedIndex].text;'."\n".'                lockPageScroll();')
        ->toContain('orderConfirmationModal.classList.remove(\'flex\');'."\n".'                unlockPageScroll();')
        ->toContain('Confirm this order?')
        ->toContain('id="order-confirmation-confirm"')
        ->toContain('openOrderConfirmation()')
        ->toContain("description.className = 'min-w-0 truncate text-[#002c76]'")
        ->toContain("details.className = 'mt-1 text-xs text-slate-500'")
        ->toContain("['per_kg', 'per_load'].includes(method) ? 2 : 0");
});

it('creates an order with customer name and contact number', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Wash and Fold', 'slug' => 'wash-and-fold-custom', 'pricing_method' => 'per_kg', 'rate' => 30, 'minimum_weight' => 1, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Maria Santos',
        'customer_contact' => '09171234567',
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
        'amount_paid' => 60,
    ]);

    $order = LaundryOrder::latest('id')->first();
    $response->assertRedirect("/orders/{$order->id}");

    expect($order->customer->name)->toBe('Maria Santos')
        ->and($order->customer->contact_number)->toBe('09171234567');

    $this->actingAs($user)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertSee('09171234567');
});

it('allows laundry status changes in either direction', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-TEST', 'name' => 'Test Customer']);
    $order = LaundryOrder::create([
        'order_code' => 'LND-TEST',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', 'test-token'),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch("/orders/{$order->id}/status", ['laundry_status' => 'ready_for_pickup'])
        ->assertRedirect();

    expect($order->fresh()->laundry_status)->toBe('ready_for_pickup');

    $this->actingAs($user)
        ->patch("/orders/{$order->id}/status", [
            'laundry_status' => 'released',
            'payment_method' => 'cash',
            'cash_tendered' => 100,
        ])
        ->assertRedirect();

    expect($order->fresh()->laundry_status)->toBe('released')
        ->and($order->fresh()->released_at)->not->toBeNull()
        ->and($order->fresh()->payment_status)->toBe('paid')
        ->and((float) $order->fresh()->outstanding_balance)->toBe(0.0)
        ->and((float) $order->payments()->firstOrFail()->amount)->toBe(100.0)
        ->and((float) $order->payments()->firstOrFail()->cash_tendered)->toBe(100.0);

    $this->actingAs($user)
        ->patch("/orders/{$order->id}/status", ['laundry_status' => 'received'])
        ->assertRedirect();

    expect($order->fresh()->laundry_status)->toBe('received')
        ->and($order->fresh()->released_at)->toBeNull()
        ->and($order->statusHistory()->count())->toBe(3);
});

it('displays the service workflow statuses in order controls', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-STATUS', 'name' => 'Status Customer']);
    $order = LaundryOrder::create([
        'order_code' => 'LND-STATUS',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', 'status-token'),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $this->actingAs($user)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee('value="received"', false)
        ->assertSee('value="washing"', false)
        ->assertSee('value="drying"', false)
        ->assertSee('value="folding"', false)
        ->assertSee('value="ready_for_pickup"', false)
        ->assertSee('value="released"', false)
        ->assertSee('On Queue')
        ->assertSee('Ready for Pick up')
        ->assertSee('Released')
        ->assertDontSee('value="cancelled"', false)
        ->assertDontSee('value="ironing"', false);
});

it('requires full payment before releasing an unpaid order', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-UNPAID-RELEASE', 'name' => 'Unpaid Release Customer']);
    $order = LaundryOrder::create([
        'order_code' => 'LND-UNPAID-RELEASE',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', 'unpaid-release-token'),
        'subtotal' => 150,
        'total_amount' => 150,
        'total_paid' => 0,
        'outstanding_balance' => 150,
        'payment_status' => 'unpaid',
        'laundry_status' => 'ready_for_pickup',
        'received_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch("/orders/{$order->id}/status", [
            'laundry_status' => 'released',
            'payment_method' => 'cash',
            'cash_tendered' => 100,
        ])
        ->assertSessionHasErrors('cash_tendered');

    expect($order->fresh()->laundry_status)->toBe('ready_for_pickup')
        ->and($order->fresh()->payment_status)->toBe('unpaid')
        ->and($order->payments()->count())->toBe(0);
});

it('separates claimed and unclaimed orders and filters each list by search', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-BOARD', 'name' => 'Board Customer']);
    $statuses = [
        'received' => 'LND-BOARD-PENDING',
        'washing' => 'LND-BOARD-ONGOING',
        'ready_for_pickup' => 'LND-BOARD-READY',
        'released' => 'LND-BOARD-RELEASED',
        'cancelled' => 'LND-BOARD-CANCELLED',
    ];

    foreach ($statuses as $status => $orderCode) {
        LaundryOrder::create([
            'order_code' => $orderCode,
            'customer_id' => $customer->id,
            'tracking_token_hash' => hash('sha256', $orderCode),
            'subtotal' => 100,
            'total_amount' => 100,
            'total_paid' => 0,
            'outstanding_balance' => 100,
            'payment_status' => 'unpaid',
            'laundry_status' => $status,
            'received_at' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee('LND-BOARD-PENDING')
        ->assertSee('LND-BOARD-ONGOING')
        ->assertSee('LND-BOARD-READY')
        ->assertDontSee('LND-BOARD-RELEASED')
        ->assertSee('LND-BOARD-CANCELLED')
        ->assertSee('Unclaimed')
        ->assertSee('Claimed')
        ->assertSee('id="release-payment-modal"', false)
        ->assertSee('class="release-payment-modal w-[calc(100%-2rem)]', false)
        ->assertSee('Pay before release')
        ->assertSee('Pay and release')
        ->assertSee('class="mt-5 rounded-2xl bg-[#002c76] p-4 text-white"', false)
        ->assertSee('id="release-cash-change-panel"', false)
        ->assertSee('id="release-cash-change" class="text-xl" aria-live="polite" aria-atomic="true"', false)
        ->assertSee('Remaining due')
        ->assertSee("cashTendered.value = '';", false)
        ->assertSee("cashTendered.value === '' ? 0 : tendered - due", false)
        ->assertSee("cashTendered.addEventListener('input', updatePaymentFields)", false)
        ->assertSee("selectedStatus !== 'released'", false)
        ->assertSee("pendingForm.requestSubmit()", false)
        ->assertSee('Service status')
        ->assertSee('name="laundry_status"', false)
        ->assertSee('Board Customer')
        ->assertSee('Order code')
        ->assertSee('Customer name')
        ->assertDontSee('Swipe sideways to view all status columns.')
        ->assertDontSee('order-drop-column', false)
        ->assertSee('Scan order QR')
        ->assertSee('Flip camera')
        ->assertSee('orders/scan-status');

    $this->actingAs($user)
        ->get('/orders?search=Board%20Customer')
        ->assertOk()
        ->assertSee('LND-BOARD-PENDING')
        ->assertDontSee('LND-BOARD-RELEASED');

    $this->actingAs($user)
        ->get('/orders?claim_status=claimed')
        ->assertOk()
        ->assertSee('LND-BOARD-RELEASED')
        ->assertDontSee('LND-BOARD-PENDING')
        ->assertDontSee('LND-BOARD-READY');

    $this->actingAs($user)
        ->get('/orders?claim_status=claimed&search=Board%20Customer')
        ->assertOk()
        ->assertSee('LND-BOARD-RELEASED')
        ->assertDontSee('LND-BOARD-PENDING');

    $this->actingAs($user)
        ->get('/orders?search=not-found')
        ->assertOk()
        ->assertDontSee('LND-BOARD-PENDING')
        ->assertDontSee('LND-BOARD-ONGOING');
});

it('filters claimed orders by the date they were released', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-CLAIM-DATE', 'name' => 'Claim Date Customer']);
    $today = now()->toDateString();
    $orders = [
        ['code' => 'LND-CLAIM-TODAY', 'released_at' => now()],
        ['code' => 'LND-CLAIM-OLDER', 'released_at' => now()->subDay()],
    ];

    foreach ($orders as $orderData) {
        LaundryOrder::create([
            'order_code' => $orderData['code'],
            'customer_id' => $customer->id,
            'tracking_token_hash' => hash('sha256', $orderData['code']),
            'subtotal' => 100,
            'total_amount' => 100,
            'total_paid' => 0,
            'outstanding_balance' => 100,
            'payment_status' => 'unpaid',
            'laundry_status' => 'released',
            'released_at' => $orderData['released_at'],
            'received_at' => now()->subDays(2),
        ]);
    }

    $this->actingAs($user)
        ->get('/orders?claim_status=claimed&released_date='.$today)
        ->assertOk()
        ->assertSee('LND-CLAIM-TODAY')
        ->assertDontSee('LND-CLAIM-OLDER')
        ->assertSee('id="released-date-filter" type="date" name="released_date"', false);

    $this->actingAs($user)
        ->get('/orders?claim_status=claimed&released_date=not-a-date')
        ->assertSessionHasErrors('released_date');
});

it('uses business settings branding on public tracking pages', function () {
    BusinessSetting::updateOrCreate(['key' => 'business_name'], ['value' => 'Fresh Fold Laundry']);
    BusinessSetting::updateOrCreate(['key' => 'business_logo'], ['value' => 'business/fresh-fold.png']);
    BusinessSetting::updateOrCreate(['key' => 'business_phone'], ['value' => '09171234567']);
    BusinessSetting::updateOrCreate(['key' => 'business_address'], ['value' => '123 Clean Street']);

    $customer = Customer::create(['code' => 'CUS-BRAND', 'name' => 'Brand Test Customer']);
    $order = LaundryOrder::create([
        'order_code' => 'LND-BRAND-TEST',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', 'brand-token'),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Fresh Fold Laundry')
        ->assertSee('09171234567')
        ->assertSee('123 Clean Street')
        ->assertSee('/storage/business/fresh-fold.png');

    $this->get('/?order_code='.$order->order_code)
        ->assertOk()
        ->assertSee('Fresh Fold Laundry')
        ->assertSee('09171234567')
        ->assertSee('/storage/business/fresh-fold.png');
});

it('allows authorized staff to update order status from the order list', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-DRAG', 'name' => 'Drag Customer']);
    $order = LaundryOrder::create([
        'order_code' => 'LND-DRAG',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', 'drag-token'),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee('name="laundry_status"', false)
        ->assertSee('value="washing"', false)
        ->assertSee('>Update</button>', false)
        ->assertDontSee('draggable="true"', false);

    $this->actingAs($user)
        ->patch("/orders/{$order->id}/status", ['laundry_status' => 'washing'])
        ->assertRedirect();

    expect($order->fresh()->laundry_status)->toBe('washing');
});

it('advances laundry status in order when staff scan the order QR code', function () {
    $user = verifiedRoleUser('laundry_staff');
    $customer = Customer::create(['code' => 'CUS-SCAN', 'name' => 'Scan Customer']);
    $token = 'ABCD1234EFGH5678';
    $order = LaundryOrder::create([
        'order_code' => 'LND-ABCD-1234-EFGH-5678',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', $token),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $expectedStatuses = ['washing', 'drying', 'folding', 'ready_for_pickup', 'ready_for_pickup'];
    foreach ($expectedStatuses as $expectedStatus) {
        $response = $this->actingAs($user)
            ->postJson('/orders/scan-status', [
                'qr_code' => "https://laundry.test/track/{$token}",
            ])
            ->assertOk()
            ->assertJsonPath('laundry_status', $expectedStatus);
    }

    expect($order->fresh()->laundry_status)->toBe('ready_for_pickup')
        ->and($order->statusHistory()->count())->toBe(4);
    $response->assertJsonPath('changed', false)
        ->assertJsonPath('status_label', 'Ready for Pick up');
});

it('allows only laundry staff and managers to advance status by scanning', function () {
    $cashier = verifiedRoleUser('cashier');
    $response = $this->actingAs($cashier)->postJson('/orders/scan-status', [
        'qr_code' => 'https://laundry.test/track/ABCD1234EFGH5678',
    ]);

    $response->assertForbidden();
});

it('creates orders with server calculated totals', function () {
    $user = verifiedRoleUser('cashier');
    $customer = Customer::create(['code' => 'CUS-CALC', 'name' => 'Calculation Customer']);
    $service = Service::create(['name' => 'Test Wash', 'slug' => 'test-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'minimum_weight' => 5, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'customer_name' => 'Calculation Customer',
        'services' => [['service_id' => $service->id, 'quantity' => 1]],
        'amount_paid' => 0,
    ]);

    $order = LaundryOrder::latest('id')->first();
    $response->assertRedirect("/orders/{$order->id}");
    expect((float) $order->total_amount)->toBe(200.0);
});

it('converts the entered kilograms into billable per-load quantities', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create([
        'name' => 'Capped Load Wash',
        'slug' => 'capped-load-wash',
        'pricing_method' => 'per_load',
        'rate' => 40,
        'minimum_weight' => 8,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Load Customer',
        'services' => [['service_id' => $service->id, 'quantity' => 10]],
        'amount_paid' => 0,
    ]);

    $order = LaundryOrder::latest('id')->first();
    $response->assertRedirect("/orders/{$order->id}");
    expect((float) $order->total_amount)->toBe(80.0)
        ->and((float) $order->items()->first()->quantity)->toBe(2.0);
});

it('records paid cash orders and prints cash change on the receipt', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Cash Wash', 'slug' => 'cash-wash', 'pricing_method' => 'per_kg', 'rate' => 50, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Cash Customer',
        'payment_status' => 'paid',
        'payment_method' => 'cash',
        'cash_tendered' => 150,
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
    ]);

    $order = LaundryOrder::latest('id')->firstOrFail();
    $response->assertRedirect("/orders/{$order->id}?print=1");
    expect((float) $order->total_paid)->toBe(100.0)
        ->and((float) $order->outstanding_balance)->toBe(0.0)
        ->and((float) $order->payments()->firstOrFail()->cash_tendered)->toBe(150.0);

    $this->actingAs($user)
        ->get("/orders/{$order->id}?print=1")
        ->assertOk()
        ->assertSee('order-payment-status--paid', false)
        ->assertSee('Cash received: ₱150.00')
        ->assertSee('Change: ₱50.00')
        ->assertSee('Scan to track your laundry')
        ->assertDontSee('Record a payment');
});

it('rejects a paid cash order when the amount tendered is less than the total', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Cash Wash Insufficient', 'slug' => 'cash-wash-insufficient', 'pricing_method' => 'per_kg', 'rate' => 50, 'is_active' => true]);

    $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Insufficient Payment Customer',
        'payment_status' => 'paid',
        'payment_method' => 'cash',
        'cash_tendered' => 99,
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
    ])->assertSessionHasErrors('cash_tendered');

    expect(LaundryOrder::count())->toBe(0);
});

it('creates an unpaid order without recording a payment', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Unpaid Wash', 'slug' => 'unpaid-wash', 'pricing_method' => 'per_kg', 'rate' => 50, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Unpaid Customer',
        'payment_status' => 'unpaid',
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
    ]);

    $order = LaundryOrder::latest('id')->firstOrFail();
    $response->assertRedirect("/orders/{$order->id}");
    expect($order->payment_status)->toBe('unpaid')
        ->and((float) $order->total_paid)->toBe(0.0)
        ->and($order->payments)->toBeEmpty();

    $this->actingAs($user)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee('order-payment-status--unpaid', false)
        ->assertSee('Record a payment');
});

it('records paid gcash orders without requesting a cash amount', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'GCash Wash', 'slug' => 'gcash-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'GCash Customer',
        'payment_status' => 'paid',
        'payment_method' => 'gcash',
        'payment_reference' => 'GC-TEST-123',
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
    ]);

    $order = LaundryOrder::latest('id')->firstOrFail();
    $response->assertRedirect("/orders/{$order->id}?print=1");
    expect($order->payment_status)->toBe('paid')
        ->and((float) $order->total_paid)->toBe(80.0)
        ->and($order->payments()->firstOrFail()->verification_status)->toBe('pending');
});

it('creates an order with a required customer name and random printable tracking code', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Walk-in Wash', 'slug' => 'walk-in-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'minimum_weight' => 1, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Walk-in Customer',
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
        'amount_paid' => 0,
    ]);

    $order = LaundryOrder::latest('id')->firstOrFail();
    $response->assertRedirect("/orders/{$order->id}");

    $receipt = $this->actingAs($user)->get("/orders/{$order->id}")->assertOk();
    expect($order->order_code)->toMatch('/^LND-(?:[A-Z0-9]{4}-){3}[A-Z0-9]{4}$/');

    $this->post('/track', ['tracking_code' => $order->order_code])
        ->assertOk()
        ->assertSee($order->order_code);
});

it('requires a customer name when creating an order', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Name Required Wash', 'slug' => 'name-required-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'is_active' => true]);

    $this->actingAs($user)->post('/orders', [
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
    ])->assertSessionHasErrors('customer_name');

    expect(LaundryOrder::count())->toBe(0);
});

it('renders the order receipt with or without a session tracking token', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Ironing', 'slug' => 'ironing', 'pricing_method' => 'per_piece', 'rate' => 25, 'minimum_weight' => 0, 'is_active' => true]);
    BusinessSetting::updateOrCreate(['key' => 'business_phone'], ['value' => '09171234567']);
    BusinessSetting::updateOrCreate(['key' => 'business_address'], ['value' => '123 Laundry Street']);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Maria Santos',
        'services' => [['service_id' => $service->id, 'quantity' => 4]],
        'amount_paid' => 100,
    ]);

    $order = LaundryOrder::latest('id')->first();
    $response->assertRedirect("/orders/{$order->id}");

    // Access receipt right after redirect (with session tracking_token)
    $this->actingAs($user)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee($order->order_code)
        ->assertSee('09171234567')
        ->assertSee('123 Laundry Street')
        ->assertDontSee('CUS-')
        ->assertSee('Tracking code: <span class="font-bold tracking-wider">'.$order->order_code.'</span>', false)
        ->assertSee('class="customer-copy hidden text-center"', false)
        ->assertSee('Maria Santos')
        ->assertSee('Payment: <span class="font-bold">Paid</span>', false)
        ->assertSee('Received:')
        ->assertSee('Print receipt')
        ->assertSee('Print laundry tag')
        ->assertSee('printing-laundry-tag')
        ->assertSee("sessionStorage.removeItem('laundry-pos-order-draft')", false)
        ->assertSee("localStorage.removeItem('laundry-pos-order-draft')", false)
        ->assertSee('function printLaundryTag()')
        ->assertDontSee('customerCopyPrintScheduled')
        ->assertSee('Scan to track your laundry');

    // Access receipt directly without session tracking_token (e.g. reprint/view from orders list)
    $this->actingAs($user)
        ->flushSession()
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee($order->order_code)
        ->assertSee('Tracking code: <span class="font-bold tracking-wider">'.$order->order_code.'</span>', false)
        ->assertSee('<p class="mt-3 text-lg font-bold tracking-wider">'.$order->order_code.'</p>', false)
        ->assertSee('Scan to track your laundry');

    $this->get('/?order_code='.$order->order_code)
        ->assertOk()
        ->assertDontSee($order->order_code);
});

it('shows service and add-on quantities below their names on the receipt', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create([
        'name' => 'Self Service',
        'slug' => 'self-service-receipt',
        'pricing_method' => 'per_load',
        'rate' => 150,
        'minimum_weight' => 8,
        'is_active' => true,
    ]);
    $detergent = AddOn::create(['name' => 'Detergent', 'price' => 10, 'is_active' => true]);
    $extraDry = AddOn::create(['name' => 'Extra Dry', 'price' => 30, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_name' => 'Receipt Layout Customer',
        'services' => [['service_id' => $service->id, 'quantity' => 10]],
        'add_ons' => [
            ['add_on_id' => $detergent->id, 'quantity' => 1],
            ['add_on_id' => $extraDry->id, 'quantity' => 2],
        ],
        'amount_paid' => 0,
    ]);

    $order = LaundryOrder::latest('id')->firstOrFail();
    $response->assertRedirect("/orders/{$order->id}");

    $this->actingAs($user)->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Self Service')
        ->assertSee('x 2 load')
        ->assertSee('Detergent')
        ->assertSee('x 1 @ ₱10.00')
        ->assertSee('Extra Dry')
        ->assertSee('x 2 @ ₱30.00')
        ->assertSee('pl-1 text-xs text-slate-500', false);
});

it('allows administrators to manage optional add-ons', function () {
    $administrator = verifiedRoleUser('administrator');

    $this->actingAs($administrator)
        ->post('/admin/add-ons', ['add_on_name' => 'Fabric softener', 'price' => 12.50])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $addOn = AddOn::where('name', 'Fabric softener')->firstOrFail();
    expect((float) $addOn->price)->toBe(12.5);

    $this->actingAs($administrator)
        ->get('/admin/services')
        ->assertOk()
        ->assertSee('Manage add-ons')
        ->assertSee('Fabric softener');

    $this->actingAs($administrator)
        ->put("/admin/add-ons/{$addOn->id}", ['add_on_name' => 'Premium softener', 'price' => 18])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($addOn->fresh()->name)->toBe('Premium softener');

    $this->actingAs($administrator)->patch("/admin/add-ons/{$addOn->id}/toggle")->assertRedirect();
    expect($addOn->fresh()->is_active)->toBeFalse();

    $this->actingAs($administrator)->delete("/admin/add-ons/{$addOn->id}")->assertRedirect();
    $this->assertSoftDeleted($addOn);
});

it('adds selected optional add-ons to order totals and customer-facing details', function () {
    $cashier = verifiedRoleUser('cashier');
    $service = Service::create([
        'name' => 'Add-on test wash',
        'slug' => 'addon-test-wash',
        'pricing_method' => 'per_kg',
        'rate' => 30,
        'is_active' => true,
    ]);
    $addOn = AddOn::create(['name' => 'Fabric softener', 'price' => 12.50, 'is_active' => true]);

    $this->actingAs($cashier)
        ->get('/pos')
        ->assertOk()
        ->assertSee('Fabric softener')
        ->assertSee('name="add_ons[0][add_on_id]"', false)
        ->assertSee('addon-quantity-trigger', false)
        ->assertSee('openAddonQuantityModal', false);

    $response = $this->actingAs($cashier)->post('/orders', [
        'customer_name' => 'Addon Customer',
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
        'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 3]],
    ]);
    $order = LaundryOrder::latest('id')->firstOrFail();

    $response->assertRedirect("/orders/{$order->id}");
    expect((float) $order->subtotal)->toBe(60.0)
        ->and((float) $order->additional_charges)->toBe(37.5)
        ->and((float) $order->total_amount)->toBe(97.5)
        ->and($order->addOns)->toHaveCount(1)
        ->and($order->addOns->first()->name)->toBe('Fabric softener')
        ->and($order->addOns->first()->quantity)->toBe(3)
        ->and((float) $order->addOns->first()->line_total)->toBe(37.5);

    $this->actingAs($cashier)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Fabric softener x 3 @ ₱12.50')
        ->assertSee('Add-ons: ₱37.50');

    $this->post('/track', ['tracking_code' => $order->order_code])
        ->assertOk()
        ->assertSee('Fabric softener')
        ->assertSee('Optional add-on')
        ->assertSee('₱97.50');

    $addOn->delete();
    $this->post('/track', ['tracking_code' => $order->order_code])
        ->assertOk()
        ->assertSee('Fabric softener');
});

it('allows add-on-only orders but rejects an order with no selections', function () {
    $cashier = verifiedRoleUser('cashier');
    $addOn = AddOn::create(['name' => 'Laundry bag', 'price' => 15, 'is_active' => true]);
    $orderData = [
        'customer_name' => 'Add-on Only Customer',
        'services' => [],
        'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 2]],
    ];

    $response = $this->actingAs($cashier)->post('/orders', $orderData);
    $order = LaundryOrder::latest('id')->firstOrFail();

    $response->assertRedirect("/orders/{$order->id}");
    expect($order->items)->toBeEmpty()
        ->and($order->addOns)->toHaveCount(1)
        ->and((float) $order->total_amount)->toBe(30.0);

    $this->actingAs($cashier)
        ->post('/orders', ['customer_name' => 'Empty Customer'])
        ->assertSessionHasErrors('services');

    expect(LaundryOrder::count())->toBe(1);
});

it('rejects inactive and unknown add-ons during order creation', function () {
    $cashier = verifiedRoleUser('cashier');
    $service = Service::create([
        'name' => 'Inactive add-on test wash',
        'slug' => 'inactive-addon-test-wash',
        'pricing_method' => 'per_kg',
        'rate' => 30,
        'is_active' => true,
    ]);
    $addOn = AddOn::create(['name' => 'Inactive softener', 'price' => 10, 'is_active' => false]);

    $orderData = [
        'customer_name' => 'Invalid Add-on Customer',
        'services' => [['service_id' => $service->id, 'quantity' => 1]],
    ];

    $this->actingAs($cashier)
        ->post('/orders', [...$orderData, 'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('add_ons');

    $this->actingAs($cashier)
        ->post('/orders', [...$orderData, 'add_ons' => [['add_on_id' => 999999, 'quantity' => 1]]])
        ->assertSessionHasErrors('add_ons.0.add_on_id');

    $this->actingAs($cashier)
        ->post('/orders', [...$orderData, 'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 0]]])
        ->assertSessionHasErrors('add_ons.0.quantity');

    expect(LaundryOrder::count())->toBe(0);
});
