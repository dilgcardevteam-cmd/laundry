<?php

namespace App\Http\Controllers;

use App\Domain\Tracking\TrackingToken;
use App\Models\BusinessSetting;
use App\Models\LaundryOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrackingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('order_code')) {
            $order = LaundryOrder::with(['customer:id,code', 'items', 'statusHistory'])
                ->where('order_code', $request->query('order_code'))
                ->first();

            if ($order) {
                return view('tracking.show', [
                    'order' => $order,
                    'businessSettings' => $this->businessSettings(),
                ]);
            }
        }

        return view('tracking.home', [
            'businessSettings' => $this->businessSettings(),
        ]);
    }

    public function show(Request $request, string $token, TrackingToken $tokens)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]+$/', $token) === 1 && strlen($token) === 64, 404);
        $order = LaundryOrder::with(['customer:id,code', 'items', 'statusHistory'])->where('tracking_token_hash', $tokens->hash($token))->firstOrFail();

        return view('tracking.show', [
            'order' => $order,
            'businessSettings' => $this->businessSettings(),
        ]);
    }

    public function lookup(Request $request, TrackingToken $tokens)
    {
        $data = $request->validate(['order_code' => ['required', 'string', 'max:40']]);
        $order = LaundryOrder::with(['customer:id,code', 'items', 'statusHistory'])->where('order_code', $data['order_code'])->firstOrFail();

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
