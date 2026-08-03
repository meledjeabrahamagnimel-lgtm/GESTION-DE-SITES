@php
    $navigation = auth()->user() ? \App\Domain\Shared\Services\MenuNavigation::pour(auth()->user()) : [];
@endphp
<!DOCTYPE html>
<html lang="fr" style="{{ collect(auth()->user()?->entreprise?->theme() ?? [])->map(fn ($v, $k) => "$k:$v")->implode(';') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? auth()->user()?->entreprise?->nom ?? config('app.name') }}</title>
    @if (auth()->user()?->entreprise?->logoUrl())
        <link rel="icon" type="image/png" href="{{ auth()->user()->entreprise->logoUrl() }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased" style="background:var(--th-paper, #F4F3EF); color:var(--th-ink, #191B20); font-family:var(--font-sans); min-height:100vh; margin:0;">

    <header style="background:var(--th-ink, #191B20); color:#fff;">
        <div style="max-width:1680px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; min-height:56px;">
            <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                <a href="{{ route('redirection') }}" wire:navigate style="display:flex; align-items:center; gap:10px; text-decoration:none;">
                    @if (auth()->user()?->entreprise?->logoUrl())
                        <img src="{{ auth()->user()->entreprise->logoUrl() }}" alt="" style="height:34px; background:#fff; border-radius:5px; padding:3px 8px;">
                    @else
                        <span style="color:#fff; font-weight:800; font-size:17px;">{{ config('app.name') }}</span>
                    @endif
                </a>
                <nav style="display:flex; gap:4px; flex-wrap:wrap;">
                    @foreach ($navigation as $item)
                        <a href="{{ $item['route'] }}" wire:navigate
                           style="display:flex; align-items:center; gap:6px; padding:9px 14px; border-radius:7px; font-size:14.5px; font-weight:600; text-decoration:none; white-space:nowrap;
                                  color:{{ $item['actif'] ? '#fff' : '#C7C9CF' }};
                                  background:{{ $item['actif'] ? 'var(--th-accent, #C8102E)' : 'transparent' }};">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                <livewire:cloche-notifications />
                <a href="{{ route('mot-de-passe.modifier') }}" wire:navigate
                   style="font-size:13.5px; color:#C7C9CF; text-decoration:none; display:flex; align-items:center; gap:8px;">
                    <x-avatar :utilisateur="auth()->user()" :taille="28" />
                    {{ auth()->user()?->name }}
                    @if (auth()->user()?->entreprise)
                        — {{ auth()->user()->entreprise->nom }}
                    @endif
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="background:transparent; border:1px solid #4B4E55; color:#fff; border-radius:7px; padding:8px 16px; font-size:13.5px; font-weight:600; cursor:pointer;">
                        Quitter
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main style="max-width:1680px; margin:0 auto; padding:20px 16px;">
        <livewire:rappel-notifications />
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
