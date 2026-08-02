@props(['titre'])

<div class="carte" style="margin-bottom:20px;">
    <h2 class="titre-section">{{ $titre }}</h2>
    {{ $slot }}
</div>
