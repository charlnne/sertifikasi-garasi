<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
@php
    $menus = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
    ];

    if (auth()->user()->isRenter() && auth()->user()->isApproved()) {
        $menus[] = ['label' => 'My Vehicles', 'route' => 'vehicles.index', 'active' => 'vehicles.*'];
        $menus[] = ['label' => 'New Rental', 'route' => 'rentals.create', 'active' => 'rentals.create'];
        $menus[] = ['label' => 'Rental History', 'route' => 'rentals.index', 'active' => 'rentals.index'];
    }
    $adminMenus = [
        ['label' => 'Renter Verification', 'route' => 'admin.renters.index', 'active' => 'admin.renters.*'],
        ['label' => 'Garages', 'route' => 'admin.garages.index', 'active' => 'admin.garages.*'],
        ['label' => 'Payment Verification', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*'],
        ['label' => 'Extensions', 'route' => 'admin.extensions.index', 'active' => 'admin.extensions.*'],
        ['label' => 'Reports', 'route' => 'admin.reports.index', 'active' => 'admin.reports.*'],
    ];
    $menuClass = fn (array $menu) => request()->routeIs($menu['active'])
        ? 'border-yellow-400 bg-maroon-700 text-white'
        : 'border-transparent text-maroon-100 hover:bg-maroon-800 hover:text-white';
@endphp
<div class="flex min-h-screen">
    <aside class="hidden w-64 shrink-0 flex-col bg-maroon-900 text-white md:flex">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-6 py-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-yellow-400 text-sm font-bold text-maroon-900">SG</span>
            <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
        </a>

        <nav class="flex-1 space-y-1 px-3 py-2">
            @foreach ($menus as $menu)
                <a href="{{ route($menu['route']) }}"
                   class="block rounded-r-lg border-l-4 px-3 py-2 text-sm font-medium transition duration-200 hover:translate-x-1 {{ $menuClass($menu) }}">
                    {{ $menu['label'] }}
                </a>
            @endforeach

            @if (auth()->user()->isAdmin())
                <p class="px-4 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-yellow-300/80">Admin</p>
                @foreach ($adminMenus as $menu)
                    <a href="{{ route($menu['route']) }}"
                       class="block rounded-r-lg border-l-4 px-3 py-2 text-sm font-medium transition duration-200 hover:translate-x-1 {{ $menuClass($menu) }}">
                        {{ $menu['label'] }}
                    </a>
                @endforeach
            @endif
        </nav>

        <div class="border-t border-maroon-800 px-6 py-4 text-xs text-maroon-200">
            Signed in as <span class="font-medium text-white">{{ auth()->user()->email }}</span>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="border-b-4 border-yellow-400 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-4 px-6 py-3">
                <h1 class="truncate text-lg font-semibold text-maroon-900">@yield('title', 'Dashboard')</h1>
                <div class="flex shrink-0 items-center gap-3 text-sm">
                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                    <span class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-maroon-800">{{ auth()->user()->role->label() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-maroon-200 px-3 py-1.5 text-xs font-medium text-maroon-700 hover:-translate-y-0.5 hover:border-maroon-300 hover:bg-maroon-50 hover:shadow-sm active:translate-y-0 active:scale-[0.97]">Logout</button>
                    </form>
                </div>
            </div>
            <nav class="flex gap-2 overflow-x-auto bg-maroon-900 px-4 py-2 text-sm md:hidden">
                @foreach (auth()->user()->isAdmin() ? array_merge($menus, $adminMenus) : $menus as $menu)
                    <a href="{{ route($menu['route']) }}"
                       class="shrink-0 rounded-full px-3 py-1 {{ request()->routeIs($menu['active']) ? 'bg-yellow-400 font-semibold text-maroon-900' : 'text-maroon-100' }}">
                        {{ $menu['label'] }}
                    </a>
                @endforeach
            </nav>
        </header>

        <main class="flex-1 animate-fade-in p-4 sm:p-6">
            <x-alert />
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
