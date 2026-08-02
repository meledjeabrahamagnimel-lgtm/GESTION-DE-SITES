@php
    $navigation = auth()->user() ? \App\Domain\Shared\Services\MenuNavigation::pour(auth()->user()) : [];
@endphp
<!DOCTYPE html>
<html lang="fr" style="{{ collect(auth()->user()?->entreprise?->theme() ?? [])->map(fn ($v, $k) => "$k:$v")->implode(';') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? auth()->user()?->entreprise?->nom ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased" style="background:var(--th-paper, #F4F3EF); color:var(--th-ink, #191B20); font-family:var(--font-sans); min-height:100vh; margin:0;">

    <header style="background:var(--th-ink, #191B20); color:#fff;">
        <div style="max-width:1600px; margin:0 auto; padding:0 24px; display:flex; align-items:center; justify-content:space-between; height:56px;">
            <div style="display:flex; align-items:center; gap:18px;">
                <a href="{{ route('redirection') }}" wire:navigate style="display:flex; align-items:center; gap:10px; text-decoration:none;">
                    @if (auth()->user()?->entreprise?->logoUrl())
                        <img src="{{ auth()->user()->entreprise->logoUrl() }}" alt="" style="height:28px; background:#fff; border-radius:4px; padding:2px 6px;">
                    @else
                        <span style="color:#fff; font-weight:800; font-size:15px;">{{ config('app.name') }}</span>
                    @endif
                </a>
                <nav style="display:flex; gap:4px;">
                    @foreach ($navigation as $item)
                        <a href="{{ $item['route'] }}" wire:navigate
                           style="display:flex; align-items:center; gap:6px; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none;
                                  color:{{ $item['actif'] ? '#fff' : '#B9BCC3' }};
                                  background:{{ $item['actif'] ? 'var(--th-accent, #C8102E)' : 'transparent' }};">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <div style="display:flex; align-items:center; gap:14px;">
                <span style="font-size:12.5px; color:#B9BCC3;">
                    {{ auth()->user()?->name }}
                    @if (auth()->user()?->entreprise)
                        — {{ auth()->user()->entreprise->nom }}
                    @endif
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="background:transparent; border:1px solid #4B4E55; color:#fff; border-radius:6px; padding:6px 12px; font-size:12.5px; cursor:pointer;">
                        Quitter
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main style="max-width:1600px; margin:0 auto; padding:24px;">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
