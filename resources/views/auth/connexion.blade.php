@php
    $entreprise = \App\Domain\Tenants\Models\Entreprise::query()->where('est_active', true)->first();
@endphp
<!DOCTYPE html>
<html lang="fr" style="{{ collect($entreprise?->theme() ?? [])->map(fn ($v, $k) => "$k:$v")->implode(';') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ $entreprise?->nom ?? config('app.name') }}</title>
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
            <p style="color:#9A9DA5; font-size:12px; margin-top:10px;">Suivi d'activité multi-sites</p>
        </div>

        <div style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,.35);">
            <h2 style="font-size:15px; font-weight:700; margin:0 0 16px; color:var(--th-ink, #191B20);">Connexion à votre espace</h2>

            @if ($errors->any())
                <div style="background:#FDF2F4; border:1px solid #C8102E33; color:#C8102E; border-radius:8px; padding:10px 12px; font-size:13px; margin-bottom:14px;">
                    @foreach ($errors->all() as $erreur)
                        <div>{{ $erreur }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <label for="email" style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    placeholder="vous@entreprise.ci"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px; margin-bottom:14px;">

                <label for="password" style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Mot de passe</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="••••••••"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px; margin-bottom:16px;">

                <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; color:#4B4E55; margin-bottom:18px;">
                    <input type="checkbox" name="remember"> Se souvenir de moi
                </label>

                <button type="submit"
                    style="width:100%; background:var(--th-accent, #C8102E); color:#fff; border:0; border-radius:8px; padding:11px; font-weight:700; font-size:14px; cursor:pointer;">
                    Se connecter
                </button>
            </form>

            <div style="text-align:center; margin-top:16px;">
                <a href="{{ route('password.request') }}" style="font-size:12.5px; color:#6B6E76; text-decoration:none;">Mot de passe oublié ?</a>
            </div>
        </div>
    </div>

</body>
</html>
