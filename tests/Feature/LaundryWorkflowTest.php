<?php

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\LaundryOrder;
use App\Models\Service;
use App\Models\User;

function verifiedRoleUser(string $role): User
{
    return User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
}

it('allows administrators to access service and settings management', function () {
    $user = verifiedRoleUser('administrator');

    $this->actingAs($user)->get('/admin/services')->assertOk();
    $this->actingAs($user)->get('/admin/settings')->assertOk();
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

it('displays the predicted service laundry code on the pos create screen', function () {
    $user = verifiedRoleUser('cashier');
    $expectedCode = LaundryOrder::nextOrderCode();

    $this->actingAs($user)
        ->get('/pos')
        ->assertOk()
        ->assertSee('Service code (laundry code):')
        ->assertSee($expectedCode)
        ->assertSee('Customer details')
        ->assertSee('Customer name')
        ->assertSee('Payment status')
        ->assertSee('Enter amount received')
        ->assertSee('target="_blank"', false);
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
        ->patch("/orders/{$order->id}/status", ['laundry_status' => 'received'])
        ->assertRedirect();

    expect($order->fresh()->laundry_status)->toBe('received')
        ->and($order->fresh()->released_at)->toBeNull()
        ->and($order->statusHistory()->count())->toBe(2);
});

it('displays all laundry statuses for order updates', function () {
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
        ->assertSee('value="cancelled"', false)
        ->assertSee('value="drying"', false)
        ->assertSee('value="ready_for_pickup"', false)
        ->assertSee('value="released"', false);
});

it('groups laundry orders into workflow columns and filters the board by search', function () {
    $user = verifiedRoleUser('cashier');
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
        ->assertSee('Pending')
        ->assertSee('Ongoing')
        ->assertSee('Ready for Release')
        ->assertSee('Released')
        ->assertSee('LND-BOARD-PENDING')
        ->assertSee('LND-BOARD-ONGOING')
        ->assertSee('LND-BOARD-READY')
        ->assertSee('LND-BOARD-RELEASED')
        ->assertSee('LND-BOARD-CANCELLED')
        ->assertSee('Cancelled');

    $this->actingAs($user)
        ->get('/orders?search=Board%20Customer')
        ->assertOk()
        ->assertSee('LND-BOARD-PENDING')
        ->assertSee('LND-BOARD-RELEASED');

    $this->actingAs($user)
        ->get('/orders?search=not-found')
        ->assertOk()
        ->assertDontSee('LND-BOARD-PENDING')
        ->assertDontSee('LND-BOARD-ONGOING');
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

it('allows authorized staff to drag orders and updates status through valid transitions', function () {
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
        ->assertSee('draggable="true"', false)
        ->assertSee('order-drag-handle', false)
        ->assertSee('pointerdown', false)
        ->assertSee('pointermove', false)
        ->assertSee('data-drop-column="ongoing"', false);

    $this->actingAs($user)
        ->patch("/orders/{$order->id}/status", ['laundry_status' => 'washing'])
        ->assertRedirect();

    expect($order->fresh()->laundry_status)->toBe('washing');
});

it('creates orders with server calculated totals', function () {
    $user = verifiedRoleUser('cashier');
    $customer = Customer::create(['code' => 'CUS-CALC', 'name' => 'Calculation Customer']);
    $service = Service::create(['name' => 'Test Wash', 'slug' => 'test-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'minimum_weight' => 5, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'services' => [['service_id' => $service->id, 'quantity' => 1]],
        'amount_paid' => 0,
    ]);

    $order = LaundryOrder::latest('id')->first();
    $response->assertRedirect("/orders/{$order->id}");
    expect((float) $order->total_amount)->toBe(200.0);
});

it('records paid cash orders and prints cash change on the receipt', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Cash Wash', 'slug' => 'cash-wash', 'pricing_method' => 'per_kg', 'rate' => 50, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
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
        ->assertSee('Cash received: ₱150.00')
        ->assertSee('Change: ₱50.00')
        ->assertSee('Scan to track your laundry');
});

it('rejects a paid cash order when the amount tendered is less than the total', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Cash Wash Insufficient', 'slug' => 'cash-wash-insufficient', 'pricing_method' => 'per_kg', 'rate' => 50, 'is_active' => true]);

    $this->actingAs($user)->post('/orders', [
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
        'payment_status' => 'unpaid',
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
    ]);

    $order = LaundryOrder::latest('id')->firstOrFail();
    $response->assertRedirect("/orders/{$order->id}");
    expect($order->payment_status)->toBe('unpaid')
        ->and((float) $order->total_paid)->toBe(0.0)
        ->and($order->payments)->toBeEmpty();
});

it('records paid gcash orders without requesting a cash amount', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'GCash Wash', 'slug' => 'gcash-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
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

it('creates a walk-in order without customer registration and tracks by order code', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Walk-in Wash', 'slug' => 'walk-in-wash', 'pricing_method' => 'per_kg', 'rate' => 40, 'minimum_weight' => 1, 'is_active' => true]);

    $response = $this->actingAs($user)->post('/orders', [
        'services' => [['service_id' => $service->id, 'quantity' => 2]],
        'amount_paid' => 0,
    ]);

    $order = LaundryOrder::latest('id')->first();
    $response->assertRedirect("/orders/{$order->id}");
    $this->post('/track', ['order_code' => $order->order_code])->assertOk()->assertSee($order->order_code);
});

it('renders the order receipt with or without a session tracking token', function () {
    $user = verifiedRoleUser('cashier');
    $service = Service::create(['name' => 'Ironing', 'slug' => 'ironing', 'pricing_method' => 'per_piece', 'rate' => 25, 'minimum_weight' => 0, 'is_active' => true]);
    BusinessSetting::updateOrCreate(['key' => 'business_phone'], ['value' => '09171234567']);
    BusinessSetting::updateOrCreate(['key' => 'business_address'], ['value' => '123 Laundry Street']);

    $response = $this->actingAs($user)->post('/orders', [
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
        ->assertDontSee('Tracking code</p>')
        ->assertSee('Scan to track your laundry');

    // Access receipt directly without session tracking_token (e.g. reprint/view from orders list)
    $this->actingAs($user)
        ->flushSession()
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee($order->order_code)
        ->assertSee('Scan to track your laundry');

    // Track order via GET query parameter
    $this->get('/?order_code='.$order->order_code)
        ->assertOk()
        ->assertSee($order->order_code);
});
