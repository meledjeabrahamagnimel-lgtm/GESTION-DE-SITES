@php
    $navigation = auth()->user() ? \App\Domain\Shared\Services\MenuNavigation::pour(auth()->user()) : [];
    $exerciceActuel = auth()->user()?->entreprise_id
        ? \App\Domain\Tenants\Models\Exercice::actuel(auth()->user()->entreprise_id)
        : null;
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
                        @if (isset($item['groupe']))
                            <details class="nav-groupe">
                                <summary class="{{ $item['actif'] ? 'nav-actif' : '' }}"
                                   style="display:flex; align-items:center; gap:6px; padding:9px 14px; border-radius:7px; font-size:14.5px; font-weight:600; white-space:nowrap; cursor:pointer; list-style:none;
                                          color:{{ $item['actif'] ? '#fff' : '#C7C9CF' }};
                                          background:{{ $item['actif'] ? 'var(--th-accent, #C8102E)' : 'transparent' }};">
                                    {{ $item['label'] }} <span style="font-size:10px;">▾</span>
                                </summary>
                                <div class="nav-groupe-panneau">
                                    @foreach ($item['groupe'] as $sousItem)
                                        <a href="{{ $sousItem['route'] }}" wire:navigate class="{{ $sousItem['actif'] ? 'nav-actif' : '' }}"
                                           style="display:block; padding:9px 14px; font-size:14px; font-weight:600; text-decoration:none; white-space:nowrap;
                                                  color:{{ $sousItem['actif'] ? 'var(--th-accent, #C8102E)' : 'var(--th-ink, #191B20)' }};">
                                            {{ $sousItem['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @else
                            <a href="{{ $item['route'] }}" wire:navigate class="{{ $item['actif'] ? 'nav-actif' : '' }}"
                               style="display:flex; align-items:center; gap:6px; padding:9px 14px; border-radius:7px; font-size:14.5px; font-weight:600; text-decoration:none; white-space:nowrap;
                                      color:{{ $item['actif'] ? '#fff' : '#C7C9CF' }};
                                      background:{{ $item['actif'] ? 'var(--th-accent, #C8102E)' : 'transparent' }};">
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                @if ($exerciceActuel)
                    @php
                        $couleurExercice = $exerciceActuel->statut === 'Clos' ? '#C8102E' : '#0E9F6E';
                    @endphp
                    @if (auth()->user()?->hasRole('gerant'))
                        <a href="{{ route('parametres') }}?onglet=exercices" wire:navigate
                           style="display:flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700; text-decoration:none;
                                  color:#fff; background:{{ $couleurExercice }}22; border:1px solid {{ $couleurExercice }}; border-radius:99px; padding:5px 12px;">
                            <span style="width:7px; height:7px; border-radius:99px; background:{{ $couleurExercice }};"></span>
                            Exercice {{ $exerciceActuel->annee }} — {{ $exerciceActuel->statut }}
                        </a>
                    @else
                        <span style="display:flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700;
                                     color:#C7C9CF; border:1px solid #4B4E55; border-radius:99px; padding:5px 12px;">
                            <span style="width:7px; height:7px; border-radius:99px; background:{{ $couleurExercice }};"></span>
                            Exercice {{ $exerciceActuel->annee }}
                        </span>
                    @endif
                @endif
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
                        Déconnexion
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
