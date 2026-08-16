<?php

use Modules\Noyau\Commun\Modeles\AbonnementPush;
use Modules\Noyau\Commun\Services\WebPush\EnvoyeurPush;
use Illuminate\Support\Facades\Validator;
use function Livewire\Volt\{computed, state};

state(['message' => null]);

$pushConfigure = computed(fn () => EnvoyeurPush::estConfigure());

$appareils = computed(fn () => AbonnementPush::where('user_id', auth()->id())
    ->orderByDesc('id')->get());

/** Enregistre l'abonnement obtenu par le navigateur pour cet appareil. */
$enregistrerAbonnement = function (string $endpoint, string $p256dh, string $auth, ?string $appareil = null) {
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

    unset($this->appareils);
    $this->message = 'Cet appareil recevra désormais les alertes, même application fermée.';
};

$oublierAppareil = function (int $id) {
    AbonnementPush::where('user_id', auth()->id())->where('id', $id)->delete();
    unset($this->appareils);
    $this->message = 'Appareil retiré : il ne recevra plus d’alertes.';
};

/** Envoie une notification d'essai à tous les appareils enregistrés. */
$envoyerUnEssai = function () {
    if ($this->appareils->isEmpty()) {
        $this->message = 'Activez d’abord les alertes sur au moins un appareil.';

        return;
    }

    EnvoyeurPush::diffuser(
        [auth()->id()],
        'Essai de notification',
        'Si vous lisez ceci hors de l’application, tout fonctionne.',
        route('mes-notifications'),
    );

    $this->message = 'Essai envoyé. Il doit apparaître dans quelques secondes, même écran verrouillé.';
};

?>

{{-- Le x-data est porté par un élément INTERNE : Livewire reconstruit la racine de
     ses composants, et Alpine y perdrait son contexte à chaque rafraîchissement. --}}
<div>
<div x-data="{
        secure: window.isSecureContext,
        sw: 'serviceWorker' in navigator,
        pm: 'PushManager' in window,
        notif: 'Notification' in window,
        permission: 'Notification' in window ? Notification.permission : 'indisponible',
        ios: /iPad|iPhone|iPod/.test(navigator.userAgent),
        installee: window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
        swActif: null,
        abonnementLocal: null,
        occupe: false,
        echec: null,

        async init() {
            if (! this.sw) return;
            const regs = await navigator.serviceWorker.getRegistrations();
            this.swActif = regs.length > 0;

            // Sans agent de service il n'y a pas d'abonnement possible : on répond « non »
            // plutôt que de laisser un tiret, qui laisserait croire à un état inconnu.
            this.abonnementLocal = regs.length && this.pm
                ? !! (await regs[0].pushManager.getSubscription())
                : false;
        },

        get compatible() { return this.secure && this.sw && this.pm && this.notif; },

        async activer() {
            if (! window.activerPush) {
                this.echec = 'Rechargez la page : les scripts ne sont pas encore chargés.';
                return;
            }
            this.occupe = true;
            this.echec = null;
            const issue = await window.activerPush(@js(config('webpush.cle_publique')), $wire);
            if (issue.echec) this.echec = issue.echec;
            if (issue.etat) this.permission = issue.etat;
            await this.init();
            this.occupe = false;
        },
    }">

    <div style="margin-bottom:14px;">
        <h1 style="font-family:'Barlow Condensed',sans-serif; font-size:23px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin:0;">
            Mes notifications
        </h1>
        <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:4px 0 0;">
            Les alertes s'affichent déjà dans l'application. Activez-les ici pour les recevoir
            aussi sur cet appareil quand l'application est fermée.
        </p>
    </div>

    @if ($message)
        <div class="encart encart-succes">{{ $message }}</div>
    @endif

    @if (! $this->pushConfigure)
        <div class="encart encart-alerte">
            Les clés VAPID ne sont pas configurées sur le serveur. Contactez l'administrateur :
            la commande <strong>php artisan push:cles</strong> les génère.
        </div>
    @else

    {{-- ------------------------------------------------ Activation --}}
    <x-carte-section titre="Cet appareil" icone="commercial" couleur="#C8102E">

        <template x-if="compatible && permission !== 'denied' && ! abonnementLocal">
            <div>
                <p style="font-size:14px; margin:0 0 12px;">
                    Cet appareil peut recevoir les alertes. Le navigateur vous demandera
                    l'autorisation après le clic.
                </p>
                <button type="button" @click="activer()" :disabled="occupe" class="bouton">
                    <span x-show="!occupe">Activer les alertes sur cet appareil</span>
                    <span x-show="occupe" x-cloak>Activation en cours…</span>
                </button>
                <p x-show="echec" x-cloak x-text="echec" class="champ-erreur" style="margin-top:10px;"></p>
            </div>
        </template>

        <template x-if="abonnementLocal">
            <div class="encart encart-succes" style="margin:0;">
                ✓ Les alertes sont actives sur cet appareil.
            </div>
        </template>

        <template x-if="permission === 'denied'">
            <div class="encart encart-alerte" style="margin:0;">
                <strong>Vous avez refusé les notifications pour ce site.</strong><br>
                Le navigateur ne redemandera plus. Cliquez sur le cadenas à gauche de l'adresse,
                puis remettez « Notifications » sur « Autoriser », et rechargez la page.
            </div>
        </template>

        <template x-if="ios && ! installee">
            <div class="encart encart-info" style="margin:12px 0 0;">
                <strong>Sur iPhone et iPad, une étape est obligatoire.</strong><br>
                Safari n'autorise les notifications que si le site est ajouté à l'écran d'accueil.
                Touchez le bouton <em>Partager</em> puis « Sur l'écran d'accueil », ouvrez
                l'application depuis l'icône ainsi créée, et revenez sur cette page.
            </div>
        </template>

        <template x-if="! secure">
            <div class="encart encart-alerte" style="margin:12px 0 0;">
                <strong>Adresse non sécurisée.</strong> Les notifications exigent une adresse
                en https://. Ouvrez le site en https:// puis revenez ici.
            </div>
        </template>

        {{-- Constat technique, à recopier tel quel en cas de difficulté. --}}
        <div style="margin-top:16px; padding-top:14px; border-top:1px solid var(--th-ligne,#E2E0D8);">
            <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--th-gris,#6B6E76); margin-bottom:8px;">
                Ce que dit ce navigateur
            </div>
            <div class="tableau-conteneur">
                <table class="tableau">
                    <tbody>
                        <tr><td style="font-weight:600;">Adresse sécurisée (https)</td>
                            <td><span x-text="secure ? 'oui' : 'non'" :style="secure ? 'color:var(--th-vert)' : 'color:var(--th-accent);font-weight:700'"></span></td></tr>
                        <tr><td style="font-weight:600;">Agents de service pris en charge</td>
                            <td><span x-text="sw ? 'oui' : 'non'" :style="sw ? 'color:var(--th-vert)' : 'color:var(--th-accent);font-weight:700'"></span></td></tr>
                        <tr><td style="font-weight:600;">Notifications poussées prises en charge</td>
                            <td><span x-text="pm ? 'oui' : 'non'" :style="pm ? 'color:var(--th-vert)' : 'color:var(--th-accent);font-weight:700'"></span></td></tr>
                        <tr><td style="font-weight:600;">Autorisation accordée</td>
                            <td><span x-text="permission"></span></td></tr>
                        <tr><td style="font-weight:600;">Agent de service installé</td>
                            <td><span x-text="swActif === null ? '—' : (swActif ? 'oui' : 'non')"></span></td></tr>
                        <tr><td style="font-weight:600;">Abonnement présent sur cet appareil</td>
                            <td><span x-text="abonnementLocal === null ? '—' : (abonnementLocal ? 'oui' : 'non')"></span></td></tr>
                        <tr><td style="font-weight:600;">Ouvert depuis l'écran d'accueil</td>
                            <td><span x-text="installee ? 'oui' : 'non'"></span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-carte-section>

    {{-- ------------------------------------------- Appareils connus --}}
    <x-carte-section titre="Mes appareils enregistrés" icone="liste" couleur="#2563EB">
        @forelse ($this->appareils as $appareil)
            <div wire:key="appareil-{{ $appareil->id }}"
                 style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--th-ligne,#E2E0D8); flex-wrap:wrap;">
                <span style="flex:1; min-width:200px;">
                    <span style="font-weight:600; font-size:14px;">{{ $appareil->appareil ?? 'Appareil sans nom' }}</span>
                    <span style="display:block; font-size:11.5px; color:var(--th-gris,#6B6E76);">
                        Enregistré le {{ $appareil->created_at->format('d/m/Y à H:i') }}
                    </span>
                </span>
                <button type="button" wire:click="oublierAppareil({{ $appareil->id }})"
                    wire:confirm="Cet appareil ne recevra plus d’alertes. Confirmer ?"
                    class="bouton bouton-secondaire bouton-petit">Retirer</button>
            </div>
        @empty
            <p class="legende-vide">Aucun appareil enregistré pour l'instant.</p>
        @endforelse

        @if ($this->appareils->isNotEmpty())
            <div style="margin-top:14px;">
                <button type="button" wire:click="envoyerUnEssai" class="bouton bouton-sombre">
                    Envoyer une notification d'essai
                </button>
                <p style="font-size:12.5px; color:var(--th-gris,#6B6E76); margin:8px 0 0;">
                    Fermez l'application ou verrouillez l'écran avant de cliquer, pour vérifier
                    que l'alerte arrive bien hors navigation.
                </p>
            </div>
        @endif
    </x-carte-section>

    @endif
</div>
</div>
