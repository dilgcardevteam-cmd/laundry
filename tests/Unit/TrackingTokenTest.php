<?php

use App\Domain\Tracking\TrackingToken;

it('issues unguessable tokens that hash consistently', function () {
    $service = new TrackingToken;
    $token = $service->issue();

    expect($token)->toHaveLength(16)
        ->and($token)->toMatch('/^[A-Z0-9]{16}$/')
        ->and($service->hash($token))->toBe(hash('sha256', $token))
        ->and($service->hash($token))->not->toBe($token);
});
