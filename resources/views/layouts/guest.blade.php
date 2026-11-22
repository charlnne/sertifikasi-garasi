<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-maroon-950 via-maroon-900 to-maroon-700 px-4 py-10 text-stone-800 antialiased">
<div class="w-full max-w-md">
    <div class="mb-6 flex items-center justify-center gap-3 text-white">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-yellow-400 text-base font-bold text-maroon-900">SG</span>
        <span class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</span>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="h-1.5 bg-yellow-400"></div>
        <div class="p-8">
            <h1 class="mb-6 text-center text-2xl font-semibold text-maroon-900">@yield('title')</h1>
            <x-alert />
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
