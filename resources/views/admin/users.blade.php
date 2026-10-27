<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">User management</h2></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 p-6">
        @if(session('success'))<div class="rounded bg-green-100 p-3 text-green-800">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-3 rounded bg-white p-5 shadow sm:grid-cols-5">@csrf<input name="name" required placeholder="Name" class="rounded border-gray-300"><input name="email" required type="email" placeholder="Email" class="rounded border-gray-300"><select name="role" class="rounded border-gray-300"><option>administrator</option><option>manager</option><option>cashier</option><option>laundry_staff</option></select><input name="password" required type="password" minlength="12" placeholder="Password" class="rounded border-gray-300"><input name="password_confirmation" required type="password" placeholder="Confirm password" class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-4 py-2 text-white sm:col-span-5">Create user</button></form>
        <div class="overflow-x-auto rounded bg-white shadow"><table class="w-full text-left"><thead><tr class="border-b"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Role</th></tr></thead><tbody>@foreach($users as $user)<tr class="border-b"><td class="p-3">{{ $user->name }}</td><td class="p-3">{{ $user->email }}</td><td class="p-3">{{ $user->role }}</td></tr>@endforeach</tbody></table></div>{{ $users->links() }}
    </div>
</x-app-layout>
