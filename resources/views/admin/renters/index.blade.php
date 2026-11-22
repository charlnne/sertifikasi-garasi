@extends('layouts.app')

@section('title', 'Renter Verification')

@section('content')
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (\App\Enums\UserStatus::cases() as $tab)
            <a href="{{ route('admin.renters.index', ['status' => $tab->value]) }}"
               class="flex items-center gap-2 rounded-lg px-3 py-1.5 transition hover:-translate-y-0.5 {{ $status === $tab ? 'bg-maroon-700 text-white shadow-sm' : 'bg-white text-maroon-800 ring-1 ring-maroon-100 hover:bg-maroon-50' }}">
                {{ $tab->tabLabel() }}
                <span class="rounded-full px-2 text-xs {{ $status === $tab ? 'bg-yellow-400 text-maroon-900' : 'bg-maroon-100 text-maroon-800' }}">{{ $counts[$tab->value] }}</span>
            </a>
        @endforeach
    </div>

    @if ($renters->isEmpty())
        <div class="rounded-xl bg-white p-6 text-center text-sm text-stone-500 shadow-sm ring-1 ring-maroon-100">
            No applicants in the {{ strtolower($status->tabLabel()) }} tab.
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($renters as $renter)
            <div class="grid gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100 md:grid-cols-[220px_1fr_auto] md:items-center">
                <button type="button" class="group block rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400" data-ktp-open="{{ route('admin.renters.ktp', $renter) }}"
                        data-ktp-name="{{ $renter->name }}">
                    <img src="{{ route('admin.renters.ktp', $renter) }}" alt="KTP photo {{ $renter->name }}"
                         class="h-36 w-full rounded-lg border border-maroon-100 bg-maroon-50/60 object-contain transition duration-200 group-hover:border-maroon-300 group-hover:shadow-md">
                    <span class="mt-1 block text-center text-xs text-stone-500 group-hover:text-maroon-700">Click to enlarge</span>
                </button>

                <div>
                    <dl class="grid grid-cols-[130px_1fr] gap-y-1 text-sm">
                        <dt class="text-stone-500">Name</dt>
                        <dd class="font-medium">{{ $renter->name }}</dd>
                        <dt class="text-stone-500">NIK</dt>
                        <dd class="font-mono">{{ $renter->nik }}</dd>
                        <dt class="text-stone-500">Phone</dt>
                        <dd>{{ $renter->phone }}</dd>
                        <dt class="text-stone-500">Email</dt>
                        <dd>{{ $renter->email }}</dd>
                        <dt class="text-stone-500">Registered</dt>
                        <dd>{{ $renter->created_at->translatedFormat('d F Y H:i') }}</dd>
                        <dt class="text-stone-500">Status</dt>
                        <dd>{{ $renter->status->label() }}</dd>
                    </dl>
                </div>

                @if ($renter->isPending())
                    <div class="flex gap-2 border-t border-maroon-100 pt-4 md:w-40 md:flex-col md:border-t-0 md:border-l md:pt-0 md:pl-5">
                        <form method="POST" action="{{ route('admin.renters.approve', $renter) }}" class="flex-1 md:flex-none"
                              onsubmit="return confirm(@js("Approve applicant {$renter->name}?"))">
                            @csrf
                            @method('PATCH')
                            <x-button variant="success" class="w-full">Approve</x-button>
                        </form>

                        <form method="POST" action="{{ route('admin.renters.reject', $renter) }}" class="flex-1 md:flex-none"
                              onsubmit="return confirm(@js("Reject applicant {$renter->name}? This decision cannot be changed."))">
                            @csrf
                            @method('PATCH')
                            <x-button variant="danger" class="w-full">Reject</x-button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $renters->links() }}</div>

    <dialog id="ktp-dialog" class="m-auto max-w-3xl rounded-lg p-0 backdrop:bg-black/70">
        <div class="flex items-center justify-between border-b border-maroon-100 px-4 py-2">
            <span id="ktp-dialog-title" class="text-sm font-medium"></span>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-xl leading-none text-stone-500 hover:bg-maroon-50 hover:text-maroon-800" data-ktp-close>&times;</button>
        </div>
        <img id="ktp-dialog-image" src="" alt="KTP photo" class="max-h-[80vh] w-full object-contain">
    </dialog>

    <script>
        const ktpDialog = document.getElementById('ktp-dialog');

        document.querySelectorAll('[data-ktp-open]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('ktp-dialog-image').src = button.dataset.ktpOpen;
                document.getElementById('ktp-dialog-title').textContent = 'KTP photo ' + button.dataset.ktpName;
                ktpDialog.showModal();
            });
        });

        ktpDialog.querySelector('[data-ktp-close]').addEventListener('click', () => ktpDialog.close());
        ktpDialog.addEventListener('click', (event) => {
            if (event.target === ktpDialog) {
                ktpDialog.close();
            }
        });
    </script>
@endsection
