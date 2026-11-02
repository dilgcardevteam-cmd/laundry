<?php

namespace App\Http\Controllers;

use App\Domain\Tracking\TrackingToken;
use App\Models\BusinessSetting;
use App\Models\LaundryOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrackingController extends Controller
{
    public function index(): View
    {
        return view('tracking.home', [
            'businessSettings' => $this->businessSettings(),
        ]);
    }

    public function show(string $token, TrackingToken $tokens): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9]+$/', $token) === 1 && in_array(strlen($token), [16, 64], true), 404);
        $order = LaundryOrder::with(['customer:id,code', 'items', 'addOns', 'statusHistory'])->where('tracking_token_hash', $tokens->hash($token))->firstOrFail();

        return view('tracking.show', [
            'order' => $order,
            'businessSettings' => $this->businessSettings(),
        ]);
    }

    public function lookup(Request $request, TrackingToken $tokens): View
    {
        $data = $request->validate([
            'tracking_code' => ['required', 'string', 'regex:/^(?:LND-)?[A-Za-z0-9]{4}(?:-?[A-Za-z0-9]{4}){3}$/'],
        ]);
        $token = str_replace(['LND-', '-'], '', strtoupper($data['tracking_code']));
        $order = LaundryOrder::with(['items', 'addOns', 'statusHistory'])->where('tracking_token_hash', $tokens->hash($token))->firstOrFail();

        return view('tracking.show', [
            'order' => $order,
            'businessSettings' => $this->businessSettings(),
        ]);
    }

    /**
     * @return Collection<string, string|null>
     */
    private function businessSettings(): Collection
    {
        return BusinessSetting::whereIn('key', [
            'business_name',
            'business_logo',
            'business_phone',
            'business_address',
        ])->pluck('value', 'key');
    }
}
