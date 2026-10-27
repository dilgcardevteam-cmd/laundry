<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Cashier shifts</h2></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 p-6">
        @if(session('success'))<div class="rounded bg-green-100 p-3 text-green-800">{{ session('success') }}</div>@endif
        @if(!$openShift)
            <form method="POST" action="{{ route('shifts.open') }}" class="grid gap-3 rounded bg-white p-5 shadow sm:grid-cols-3">@csrf<input name="opening_cash" required type="number" step="0.01" min="0" placeholder="Opening cash" class="rounded border-gray-300"><input name="notes" placeholder="Notes" class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-4 py-2 text-white">Open shift</button></form>
        @else
            <form method="POST" action="{{ route('shifts.close', $openShift) }}" class="grid gap-3 rounded bg-white p-5 shadow sm:grid-cols-3">@csrf @method('PATCH')<p class="rounded bg-indigo-50 p-3">Opened {{ $openShift->opened_at->format('M d, Y h:i A') }} with ₱{{ number_format($openShift->opening_cash, 2) }}</p><input name="closing_cash" required type="number" step="0.01" min="0" placeholder="Closing cash" class="rounded border-gray-300"><button class="rounded bg-red-700 px-4 py-2 text-white">Close shift</button></form>
        @endif
        <div class="overflow-x-auto rounded bg-white shadow"><table class="w-full text-left"><thead><tr class="border-b"><th class="p-3">Cashier</th><th class="p-3">Opened</th><th class="p-3">Closed</th><th class="p-3">Opening</th><th class="p-3">Closing</th><th class="p-3">Expected</th></tr></thead><tbody>@foreach($shifts as $shift)<tr class="border-b"><td class="p-3">{{ $shift->user->name }}</td><td class="p-3">{{ $shift->opened_at->format('Y-m-d H:i') }}</td><td class="p-3">{{ $shift->closed_at?->format('Y-m-d H:i') ?? 'Open' }}</td><td class="p-3">₱{{ number_format($shift->opening_cash, 2) }}</td><td class="p-3">{{ $shift->closing_cash !== null ? '₱'.number_format($shift->closing_cash, 2) : '-' }}</td><td class="p-3">{{ $shift->expected_cash !== null ? '₱'.number_format($shift->expected_cash, 2) : '-' }}</td></tr>@endforeach</tbody></table></div>{{ $shifts->links() }}
    </div>
</x-app-layout>
