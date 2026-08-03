<?php

use App\Domain\Shared\Models\NotificationApp;
use function Livewire\Volt\{computed, on};

// Rafraîchi par wire:poll et par l'événement émis après lecture d'une conversation.
on(['notifications-actualisees' => function () {
    unset($this->notifications, $this->nombreNonLues);
}]);

$notifications = computed(fn () => NotificationApp::query()
    ->where('user_id', auth()->id())
    ->orderByDesc('id')
    ->limit(15)
    ->get());

$nombreNonLues = computed(fn () => NotificationApp::query()
    ->where('user_id', auth()->id())
    ->nonLues()
    ->count());

$marquerLue = function (int $id) {
    NotificationApp::where('user_id', auth()->id())->where('id', $id)->update(['lu_le' => now()]);
    unset($this->notifications, $this->nombreNonLues);
};

$toutMarquerLu = function () {
    NotificationApp::where('user_id', auth()->id())->whereNull('lu_le')->update(['lu_le' => now()]);
    unset($this->notifications, $this->nombreNonLues);
};

?>

<div wire:poll.20s x-data="cloche({{ $this->nombreNonLues }})" style="position:relative;">

    <button type="button" @click="ouvert = ! ouvert" class="cloche-bouton" aria-label="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        </svg>
        @if ($this->nombreNonLues > 0)
            <span class="cloche-badge">{{ $this->nombreNonLues > 99 ? '99+' : $this->nombreNonLues }}</span>
        @endif
    </button>

    <div x-show="ouvert" x-cloak @click.outside="ouvert = false" x-transition.opacity class="cloche-panneau">
        <div class="cloche-entete">
            <span>Notifications</span>
            @if ($this->nombreNonLues > 0)
                <button type="button" wire:click="toutMarquerLu" class="cloche-lien">Tout marquer comme lu</button>
            @endif
        </div>

        <div class="cloche-liste">
            @forelse ($this->notifications as $notif)
                <a wire:key="notif-{{ $notif->id }}"
                   href="{{ $notif->lien ?: '#' }}"
                   @if ($notif->lien) wire:navigate @else @click.prevent="" @endif
                   wire:click="marquerLue({{ $notif->id }})"
                   class="cloche-item {{ $notif->lu_le ? '' : 'est-non-lu' }}">
                    <span class="cloche-puce" style="background:{{ $notif->couleur() }};"></span>
                    <span style="flex:1; min-width:0;">
                        <span class="cloche-titre">{{ $notif->titre }}</span>
                        @if ($notif->corps)
                            <span class="cloche-corps">{{ $notif->corps }}</span>
                        @endif
                        <span class="cloche-date">{{ $notif->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="legende-vide" style="padding:18px 14px;">Aucune notification.</p>
            @endforelse
        </div>
    </div>
</div>
