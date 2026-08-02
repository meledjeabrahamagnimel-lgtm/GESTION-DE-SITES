@php
    $entreprise = \App\Domain\Tenants\Models\Entreprise::query()->where('est_active', true)->first();
@endphp
<!DOCTYPE html>
<html lang="fr" style="{{ collect($entreprise?->theme() ?? [])->map(fn ($v, $k) => "$k:$v")->implode(';') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ $entreprise?->nom ?? config('app.name') }}</title>
    @if ($entreprise?->logoUrl())
        <link rel="icon" type="image/png" href="{{ $entreprise->logoUrl() }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="background:var(--th-ink, #191B20); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; font-family:var(--font-sans);">

    <div style="width:100%; max-width:420px;">
        <div style="text-align:center; margin-bottom:20px;">
            @if ($entreprise?->logoUrl())
                <div style="background:#fff; border-radius:12px; padding:14px 24px; display:inline-block;">
                    <img src="{{ $entreprise->logoUrl() }}" alt="{{ $entreprise->nom }}" style="width:200px; display:block;">
                </div>
            @else
                <h1 style="color:#fff; font-size:22px; font-weight:800;">{{ config('app.name') }}</h1>
            @endif
            <p style="color:#9A9DA5; font-size:13.5px; margin-top:10px;">Suivi d'activité multi-sites</p>
        </div>

        <div style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,.35);">
            <h2 style="font-size:15px; font-weight:700; margin:0 0 16px; color:var(--th-ink, #191B20);">Connexion à votre espace</h2>

            @if ($errors->any())
                <div style="background:#FDF2F4; border:1px solid #C8102E33; color:#C8102E; border-radius:8px; padding:10px 12px; font-size:14.5px; margin-bottom:14px;">
                    @foreach ($errors->all() as $erreur)
                        <div>{{ $erreur }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <label for="email" style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    placeholder="vous@entreprise.ci"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:14px;">

                <label for="password" style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Mot de passe</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="••••••••"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:16px;">

                <label style="display:flex; align-items:center; gap:8px; font-size:14px; color:#4B4E55; margin-bottom:18px;">
                    <input type="checkbox" name="remember"> Se souvenir de moi
                </label>

                <button type="submit"
                    style="width:100%; background:var(--th-accent, #C8102E); color:#fff; border:0; border-radius:8px; padding:11px; font-weight:700; font-size:15.5px; cursor:pointer;">
                    Se connecter
                </button>
            </form>

            <div style="display:flex; align-items:center; gap:10px; margin:18px 0;">
                <div style="flex:1; height:1px; background:#E2E0D8;"></div>
                <span style="font-size:12.5px; color:#9A9DA5; text-transform:uppercase; letter-spacing:.04em;">ou</span>
                <div style="flex:1; height:1px; background:#E2E0D8;"></div>
            </div>

            <a href="{{ route('auth.google') }}"
               style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; box-sizing:border-box; background:#fff; color:#191B20; border:1px solid #E2E0D8; border-radius:8px; padding:10px; font-weight:600; font-size:15px; text-decoration:none;">
                <svg width="16" height="16" viewBox="0 0 18 18" aria-hidden="true">
                    <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 01-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
                    <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.81.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 009 18z"/>
                    <path fill="#FBBC05" d="M3.97 10.72A5.4 5.4 0 013.68 9c0-.6.1-1.18.29-1.72V4.95H.96A9 9 0 000 9c0 1.45.35 2.83.96 4.05l3.01-2.33z"/>
                    <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.59-2.59C13.46.89 11.43 0 9 0A9 9 0 00.96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
                </svg>
                Continuer avec Google
            </a>

            <div style="text-align:center; margin-top:16px;">
                <a href="{{ route('password.request') }}" style="font-size:14px; color:#6B6E76; text-decoration:none;">Mot de passe oublié ?</a>
            </div>
            <div style="text-align:center; margin-top:8px;">
                <a href="{{ route('inscription') }}" wire:navigate style="font-size:14px; color:#6B6E76; text-decoration:none;">Nouvelle entreprise ? <span style="color:var(--th-accent,#C8102E); font-weight:600;">Créer un compte</span></a>
            </div>
        </div>
    </div>

</body>
</html>
