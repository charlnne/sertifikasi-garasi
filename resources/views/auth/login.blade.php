@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <x-input name="email" label="Email" type="email" required autofocus />
        <x-input name="password" label="Password" type="password" required />

        <label class="mb-4 flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>

        <x-button class="w-full">Login</x-button>
    </form>

    <p class="mt-4 text-center text-sm">
        Don't have an account? <a href="{{ route('register') }}" class="font-medium text-maroon-700 underline">Register</a>
    </p>
@endsection
