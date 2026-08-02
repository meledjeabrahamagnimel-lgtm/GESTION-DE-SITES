@props(['utilisateur', 'taille' => 34])

@php $url = $utilisateur?->photoUrl(); @endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $utilisateur->name }}"
        style="width:{{ $taille }}px; height:{{ $taille }}px; border-radius:99px; object-fit:cover; display:block; flex:0 0 auto;">
@else
    <span style="width:{{ $taille }}px; height:{{ $taille }}px; border-radius:99px; flex:0 0 auto;
                 background:var(--th-accent,#C8102E); color:#fff; display:inline-flex; align-items:center; justify-content:center;
                 font-weight:700; font-size:{{ round($taille * .4) }}px; font-family:'Barlow Condensed',sans-serif;">
        {{ $utilisateur?->initiales() ?: '?' }}
    </span>
@endif
