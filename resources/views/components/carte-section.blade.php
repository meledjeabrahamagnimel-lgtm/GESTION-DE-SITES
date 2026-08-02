@props(['titre'])

<div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:20px; margin-bottom:20px;">
    <h2 style="font-size:16.5px; font-weight:800; margin:0 0 16px;">{{ $titre }}</h2>
    {{ $slot }}
</div>
