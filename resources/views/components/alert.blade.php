@if (session('success'))
    <div class="mb-4 animate-fade-in rounded-lg border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm" role="alert">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 animate-fade-in rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm" role="alert">
        {{ session('error') }}
    </div>
@endif
