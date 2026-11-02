<?php

use App\Domain\Orders\PricingCalculator;
use App\Models\Service;
use InvalidArgumentException;

it('calculates a per kilogram service using the minimum weight', function () {
    $service = new Service([
        'pricing_method' => 'per_kg',
        'rate' => 40,
        'minimum_weight' => 5,
        'minimum_charge' => null,
        'additional_charge' => 0,
    ]);

    expect(app(PricingCalculator::class)->calculate($service, 3))
        ->toMatchArray(['quantity' => 5.0, 'line_total' => 200.0]);
});

it('applies a minimum charge and additional charge', function () {
    $service = new Service([
        'pricing_method' => 'per_piece',
        'rate' => 10,
        'minimum_charge' => 50,
        'additional_charge' => 5,
    ]);

    expect(app(PricingCalculator::class)->calculate($service, 2))
        ->toMatchArray(['line_total' => 55.0, 'additional_charge' => 5.0]);
});

it('converts kilograms to whole per-load charges using the configured capacity', function () {
    $service = new Service([
        'name' => 'Load Wash',
        'pricing_method' => 'per_load',
        'rate' => 40,
        'minimum_weight' => 8,
        'minimum_charge' => null,
        'additional_charge' => 0,
    ]);

    expect(app(PricingCalculator::class)->calculate($service, 1))
        ->toMatchArray(['quantity' => 1.0, 'line_total' => 40.0])
        ->and(app(PricingCalculator::class)->calculate($service, 10))
        ->toMatchArray(['quantity' => 2.0, 'line_total' => 80.0])
        ->and(app(PricingCalculator::class)->calculate($service, 16))
        ->toMatchArray(['quantity' => 2.0, 'line_total' => 80.0]);
});

it('applies a per-load minimum charge once rather than multiplying it by the load capacity', function () {
    $service = new Service([
        'name' => 'Load Wash',
        'pricing_method' => 'per_load',
        'rate' => 10,
        'minimum_weight' => 8,
        'minimum_charge' => 50,
        'additional_charge' => 0,
    ]);

    expect(app(PricingCalculator::class)->calculate($service, 10))
        ->toMatchArray(['quantity' => 2.0, 'line_total' => 50.0]);
});

it('rejects per-load services without a configured kg capacity', function () {
    $service = new Service([
        'name' => 'Load Wash',
        'pricing_method' => 'per_load',
        'rate' => 10,
        'minimum_weight' => null,
        'minimum_charge' => null,
        'additional_charge' => 0,
    ]);

    expect(fn () => app(PricingCalculator::class)->calculate($service, 1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects unsupported pricing methods', function () {
    $service = new Service(['pricing_method' => 'unknown', 'rate' => 10, 'additional_charge' => 0]);

    expect(fn () => app(PricingCalculator::class)->calculate($service, 1))
        ->toThrow(InvalidArgumentException::class);
});
