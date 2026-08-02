@props(['titre', 'description' => null])

<div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:24px; margin-bottom:16px;">
    <h1 style="font-size:18px; font-weight:800; margin:0 0 6px;">{{ $titre }}</h1>
    @if ($description)
        <p style="color:#6B6E76; font-size:13.5px; margin:0 0 14px; max-width:70ch;">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
