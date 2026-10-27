<?php

namespace App\Domain\Orders;

use App\Models\Service;
use InvalidArgumentException;

class PricingCalculator
{
    /**
     * @return array{quantity: float, rate: float, line_total: float, additional_charge: float}
     */
    public function calculate(Service $service, float $quantity): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $billableQuantity = $service->minimum_weight !== null
            ? max($quantity, (float) $service->minimum_weight)
            : $quantity;

        $lineTotal = match ($service->pricing_method) {
            'per_kg', 'per_load', 'per_piece' => $billableQuantity * (float) $service->rate,
            'fixed' => (float) $service->rate,
            default => throw new InvalidArgumentException('Unsupported service pricing method.'),
        };

        if ($service->minimum_charge !== null) {
            $lineTotal = max($lineTotal, (float) $service->minimum_charge);
        }

        $additionalCharge = (float) $service->additional_charge;

        return [
            'quantity' => $billableQuantity,
            'rate' => (float) $service->rate,
            'line_total' => round($lineTotal + $additionalCharge, 2),
            'additional_charge' => $additionalCharge,
        ];
    }
}
