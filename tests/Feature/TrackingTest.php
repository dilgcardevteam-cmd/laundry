<?php

use App\Models\Customer;
use App\Models\LaundryOrder;
use App\Models\User;

it('shows service quantities, amounts, and payment status through a random tracking code', function () {
    $customer = Customer::create([
        'code' => 'CUS-TRACKING',
        'name' => 'Tracking Customer',
    ]);
    $trackingCode = 'AB12CD34EF56GH78';
    $order = LaundryOrder::create([
        'order_code' => 'LND-20261102-000001',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', $trackingCode),
        'subtotal' => 80,
        'total_amount' => 100,
        'total_paid' => 25,
        'outstanding_balance' => 75,
        'payment_status' => 'partially_paid',
        'laundry_status' => 'washing',
        'received_at' => now(),
    ]);
    $order->items()->create([
        'service_name' => 'Wash and Fold',
        'pricing_method' => 'per_kg',
        'quantity' => 2,
        'rate' => 40,
        'line_total' => 80,
    ]);

    $this->post('/track', ['tracking_code' => 'LND-AB12-CD34-EF56-GH78'])
        ->assertOk()
        ->assertSee('Wash and Fold')
        ->assertSee('2 kg')
        ->assertSee('₱80.00')
        ->assertSee('₱100.00')
        ->assertSee('₱25.00')
        ->assertSee('₱75.00')
        ->assertSee('Partially paid');

    $this->get("/track/{$trackingCode}")
        ->assertOk()
        ->assertSee('Wash and Fold')
        ->assertSee('Partially paid');
});

it('does not expose an order through its predictable order number', function () {
    $customer = Customer::create([
        'code' => 'CUS-PRIVATE',
        'name' => 'Private Customer',
    ]);
    LaundryOrder::create([
        'order_code' => 'LND-20261102-000001',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', 'AB12CD34EF56GH78'),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $this->get('/?order_code=LND-20261102-000001')
        ->assertOk()
        ->assertDontSee('LND-20261102-000001');

    $this->post('/track', ['tracking_code' => 'LND-20261102-000001'])
        ->assertSessionHasErrors('tracking_code');
});

it('continues to accept previously issued tracking QR tokens', function () {
    $customer = Customer::create([
        'code' => 'CUS-LEGACY-TRACKING',
        'name' => 'Legacy Tracking Customer',
    ]);
    $legacyToken = str_repeat('A', 64);
    LaundryOrder::create([
        'order_code' => 'LND-20261026-000001',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', $legacyToken),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);

    $this->get("/track/{$legacyToken}")
        ->assertOk()
        ->assertSee('LND-20261026-000001');
});

it('keeps older random tracking codes valid on reprinted receipts', function () {
    $customer = Customer::create([
        'code' => 'CUS-LEGACY-RECEIPT',
        'name' => 'Legacy Receipt Customer',
    ]);
    $legacyToken = 'AB12CD34EF56GH78';
    $order = LaundryOrder::create([
        'order_code' => 'LND-20261102-000001',
        'customer_id' => $customer->id,
        'tracking_token_hash' => hash('sha256', $legacyToken),
        'subtotal' => 100,
        'total_amount' => 100,
        'total_paid' => 0,
        'outstanding_balance' => 100,
        'payment_status' => 'unpaid',
        'laundry_status' => 'received',
        'received_at' => now(),
    ]);
    $cashier = User::factory()->create([
        'role' => 'cashier',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($cashier)
        ->withSession(['tracking_token' => $legacyToken])
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee($legacyToken)
        ->assertDontSee('Tracking code: <span class="font-bold tracking-wider">'.$order->order_code, false);
});
