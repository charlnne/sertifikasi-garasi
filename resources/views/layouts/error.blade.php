<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') - @yield('title')</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen items-center justify-center bg-stone-50 px-4 text-stone-800 antialiased">
<div class="w-full max-w-md overflow-hidden rounded-2xl bg-white text-center shadow-lg">
    <div class="h-1.5 bg-yellow-400"></div>
    <div class="p-10">
        <p class="text-7xl font-bold text-maroon-700">@yield('code')</p>
        <h1 class="mt-4 text-2xl font-semibold text-maroon-900">@yield('title')</h1>
        <p class="mt-2 text-stone-600">@yield('message')</p>
        <a href="{{ url('/') }}" class="mt-6 inline-block rounded-lg bg-maroon-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-maroon-800 transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 active:scale-[0.97]">
            Back to home
        </a>
    </div>
</div>
</body>
</html>
