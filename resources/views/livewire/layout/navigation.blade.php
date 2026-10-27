<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="laundry-navigation border-b border-[#002c76] bg-[#002c76]">
    <!-- Primary Navigation Menu -->
    <div class="tablet-safe mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-white" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="tablet-nav hidden min-w-0 space-x-4 sm:-my-px sm:ms-6 sm:flex md:space-x-6 md:ms-8">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('pos')" :active="request()->routeIs('pos')" wire:navigate>POS</x-nav-link>
                    <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')" wire:navigate>Orders</x-nav-link>
                    @if(auth()->user()->hasRole('administrator', 'manager'))
                        <x-nav-link :href="route('reports')" :active="request()->routeIs('reports')" wire:navigate>Reports</x-nav-link>
                    @endif
                    @if(auth()->user()->hasRole('administrator', 'manager', 'cashier'))
                        <x-nav-link :href="route('shifts')" :active="request()->routeIs('shifts')" wire:navigate>Shifts</x-nav-link>
                    @endif
                    @if(auth()->user()->hasRole('administrator'))
                        <div x-data="{ settingsOpen: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }" class="relative flex items-center">
                            <button type="button" @click="settingsOpen = !settingsOpen" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-white transition hover:bg-white hover:text-[#002c76]">
                                Settings
                                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': settingsOpen }" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                            </button>
                            <div x-cloak x-show="settingsOpen" @click.outside="settingsOpen = false" class="absolute right-0 top-full z-50 mt-2 w-52 rounded-2xl border border-sky-100 bg-white p-2 shadow-xl">
                                <a href="{{ route('admin.settings') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm {{ request()->routeIs('admin.settings') ? 'bg-sky-50 font-semibold text-sky-700' : 'text-slate-600 hover:bg-sky-50 hover:text-sky-700' }}">Business settings</a>
                                <a href="{{ route('admin.services') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm {{ request()->routeIs('admin.services*') ? 'bg-sky-50 font-semibold text-sky-700' : 'text-slate-600 hover:bg-sky-50 hover:text-sky-700' }}">Laundry services</a>
                                <a href="{{ route('admin.users') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm {{ request()->routeIs('admin.users*') ? 'bg-sky-50 font-semibold text-sky-700' : 'text-slate-600 hover:bg-sky-50 hover:text-sky-700' }}">Users and roles</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:ms-4">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-[#002c76] hover:bg-white hover:text-[#002c76] focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-white hover:bg-white hover:text-[#002c76] focus:outline-none focus:bg-white focus:text-[#002c76] transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden bg-white md:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('pos')" :active="request()->routeIs('pos')" wire:navigate>POS</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')" wire:navigate>Orders</x-responsive-nav-link>
            @if(auth()->user()->hasRole('administrator'))
                <div class="border-t border-sky-100 px-3 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Settings</div>
                <x-responsive-nav-link :href="route('admin.settings')" :active="request()->routeIs('admin.settings')" wire:navigate>Business settings</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.services')" :active="request()->routeIs('admin.services*')" wire:navigate>Laundry services</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users*')" wire:navigate>Users and roles</x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
