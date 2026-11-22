@extends('layouts.guest')

@section('title', 'Renter Registration')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data">
        @csrf
        <x-input name="nik" label="NIK" inputmode="numeric" maxlength="16" placeholder="16 digits" required autofocus />
        <x-input name="name" label="Full Name" required />
        <x-input name="phone" label="Phone Number" placeholder="08xxxxxxxxxx" required />
        <x-input name="email" label="Email (used for login)" type="email" required />
        <x-input name="password" label="Password" type="password" required />
        <x-input name="password_confirmation" label="Confirm Password" type="password" required />
        <x-input name="ktp_photo" label="KTP Photo (jpg/png, max 2 MB)" type="file" accept="image/jpeg,image/png" required />

        <x-button class="w-full">Register</x-button>
    </form>

    <p class="mt-4 text-center text-sm">
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-maroon-700 underline">Login</a>
    </p>
@endsection
