<?php

use App\Domain\Shared\Models\AbonnementPush;
use App\Domain\Shared\Models\NotificationApp;
use App\Domain\Shared\Services\WebPush\EnvoyeurPush;
use function Livewire\Volt\{computed, mount, on, state};

/*
 * Ce composant se rafraîchit tout seul par wire:poll. Livewire reconstruit alors
 * son DOM, et Alpine perd le contexte des x-data qui s'y trouvent : les expressions
 * échouent sur « ouvert is not defined ». L'ouverture du panneau est donc pilotée
 * par une propriété Livewire, sans la moindre directive Alpine ici.
 */
state(['ouvert' => false]);

// Dernier nombre de non-lues connu : sert à détecter une arrivée entre deux
// sondages, pour ne sonner qu'à ce moment-là.
state(['dernierCompte' => 0]);

mount(function () {
    $this->dernierCompte = $this->nombreNonLues;
});

// Rafraîchi par l'événement émis après lecture d'une conversation.
on(['notifications-actualisees' => function () {
    unset($this->notifications, $this->nombreNonLues);
    $this->dernierCompte = $this->nombreNonLues;
}]);

/** Appelé par wire:poll : c'est le serveur qui décide s'il faut sonner. */
$rafraichir = function () {
    unset($this->notifications, $this->nombreNonLues);

    $actuel = $this->nombreNonLues;

    if ($actuel > $this->dernierCompte) {
        $this->dispatch(
            'nouvelle-notification',
            titre: $this->notifications->first(fn ($n) => $n->lu_le === null)?->titre ?? 'Nouvelle notification',
        );
    }

    $this->dernierCompte = $actuel;
};

$basculer = function () {
    $this->ouvert = ! $this->ouvert;
};

$fermer = function () {
    $this->ouvert = false;
};

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
    $this->dernierCompte = $this->nombreNonLues;
};

$toutMarquerLu = function () {
    NotificationApp::where('user_id', auth()->id())->whereNull('lu_le')->update(['lu_le' => now()]);
    unset($this->notifications, $this->nombreNonLues);
    $this->dernierCompte = 0;
};

/** Rappel discret tant qu'aucun appareil n'est enregistré. */
$doitProposerActivation = computed(fn () => EnvoyeurPush::estConfigure()
    && ! AbonnementPush::where('user_id', auth()->id())->exists());

?>

{{-- « .visible » suspend le sondage quand l'onglet est masqué : aucune requête inutile
     sur un poste laissé ouvert toute la journée. --}}
<div wire:poll.30s.visible="rafraichir" style="position:relative;">

    <button type="button" wire:click="basculer" class="cloche-bouton" aria-label="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        </svg>
        @if ($this->nombreNonLues > 0)
            <span class="cloche-badge">{{ $this->nombreNonLues > 99 ? '99+' : $this->nombreNonLues }}</span>
        @endif
    </button>

    @if ($ouvert)
        {{-- Voile transparent : un clic n'importe où ailleurs referme le panneau. --}}
        <div wire:click="fermer" class="cloche-voile"></div>

        <div class="cloche-panneau">
            <div class="cloche-entete">
                <span>Notifications</span>
                @if ($this->nombreNonLues > 0)
                    <button type="button" wire:click="toutMarquerLu" class="cloche-lien">Tout marquer comme lu</button>
                @endif
            </div>

            @if ($this->doitProposerActivation)
                <a href="{{ route('mes-notifications') }}" wire:navigate class="cloche-appareil-rappel">
                    Activer les alertes sur cet appareil →
                </a>
            @endif

            <a href="{{ route('mes-notifications') }}" wire:navigate class="cloche-reglages">
                Réglages des notifications sur mes appareils →
            </a>

            <div class="cloche-liste">
                @forelse ($this->notifications as $notif)
                    <a wire:key="notif-{{ $notif->id }}"
                       href="{{ $notif->lien ?: '#' }}"
                       @if ($notif->lien) wire:navigate @endif
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
    @endif
</div>
