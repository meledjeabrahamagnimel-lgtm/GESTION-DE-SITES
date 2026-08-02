@props(['label', 'value', 'sub' => null, 'accent' => false, 'couleur' => null])

<div style="background:#fff; border:{{ $accent ? '2px solid var(--th-accent,#C8102E)' : '1px solid var(--th-ligne,#E2E0D8)' }}; border-radius:10px; padding:18px 20px;">
    <div style="font-size:12.5px; text-transform:uppercase; letter-spacing:.04em; color:#6B6E76; font-weight:700; margin-bottom:8px;">{{ $label }}</div>
    <div style="font-size:30px; font-weight:800; line-height:1.1; font-variant-numeric:tabular-nums; color:{{ $couleur ?? ($accent ? 'var(--th-accent,#C8102E)' : 'inherit') }};">{{ $value }}</div>
    @if ($sub)
        <div style="font-size:13px; color:#6B6E76; margin-top:6px;">{{ $sub }}</div>
    @endif
</div>
