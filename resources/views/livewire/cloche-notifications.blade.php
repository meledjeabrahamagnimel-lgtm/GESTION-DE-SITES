<?php

use App\Domain\Shared\Models\AbonnementPush;
use App\Domain\Shared\Models\NotificationApp;
use App\Domain\Shared\Services\WebPush\EnvoyeurPush;
use Illuminate\Support\Facades\Validator;
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

/** Les clés VAPID ne sont pas configurées : la section « appareil » reste masquée. */
$pushConfigure = computed(fn () => EnvoyeurPush::estConfigure());

$appareilAbonne = computed(fn () => $this->pushConfigure
    && AbonnementPush::where('user_id', auth()->id())->exists());

/** Enregistre l'abonnement obtenu par le navigateur pour cet appareil. */
$enregistrerAbonnement = function (string $endpoint, string $p256dh, string $auth, ?string $appareil = null) {
    // Ces valeurs viennent du navigateur, pas de propriétés du composant : on les
    // valide explicitement avant de les écrire.
    Validator::make(
        ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth],
        [
            'endpoint' => ['required', 'url', 'max:2000'],
            'p256dh' => ['required', 'string', 'max:255'],
            'auth' => ['required', 'string', 'max:255'],
        ],
    )->validate();

    AbonnementPush::updateOrCreate(
        ['user_id' => auth()->id(), 'empreinte' => AbonnementPush::empreinteDe($endpoint)],
        [
            'endpoint' => $endpoint,
            'cle_p256dh' => $p256dh,
            'cle_auth' => $auth,
            'appareil' => $appareil ? str($appareil)->limit(190)->value() : null,
        ],
    );

    unset($this->appareilAbonne);
};

?>

{{-- « .visible » suspend le sondage quand l'onglet est masqué : aucune requête inutile
     sur un poste laissé ouvert toute la journée. --}}
<div wire:poll.30s.visible x-data="cloche({{ $this->nombreNonLues }})" style="position:relative;">

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

        @if ($this->pushConfigure)
            <div x-data="abonnementPush(@js(config('webpush.cle_publique')))" x-show="disponible" class="cloche-appareil">
                @if ($this->appareilAbonne)
                    <span class="cloche-appareil-actif">✓ Notifications activées sur cet appareil</span>
                @else
                    <button type="button" @click="activer()" :disabled="occupe" class="cloche-appareil-bouton">
                        <span x-show="!occupe">Recevoir les alertes sur cet appareil</span>
                        <span x-show="occupe" x-cloak>Activation…</span>
                    </button>
                    <span x-show="etat === 'denied'" x-cloak class="cloche-appareil-refus">
                        Les notifications sont bloquées dans les réglages du navigateur.
                    </span>
                    <span x-show="echec" x-cloak x-text="echec" class="cloche-appareil-refus"></span>
                @endif
            </div>
        @endif

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
