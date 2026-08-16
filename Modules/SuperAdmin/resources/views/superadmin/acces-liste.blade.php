<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use function Livewire\Volt\{state, computed};

state([
    'recherche' => '',
    'motDePasseGenerePour' => null,
    'motDePasseGenere' => null,
    'message' => null,
    // Écrasement manuel : l'identifiant de la personne dont on saisit le mot de passe.
    'cibleId' => null,
    'nouveauMotDePasse' => '',
    'nouveauMotDePasseConfirmation' => '',
    'exigerChangement' => true,
    'pageUtilisateurs' => 1,
]);

$utilisateurs = computed(function () {
    $recherche = trim($this->recherche);

    return User::with('entreprise')
        ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
            ->where('name', 'like', "%$recherche%")
            ->orWhere('email', 'like', "%$recherche%")))
        ->orderByDesc('created_at')->get();
});

$roles = computed(fn () => User::nomsRolesParUtilisateur($this->utilisateurs->pluck('id')));

/** La personne dont on est en train d'écraser le mot de passe, ou null. */
$cible = computed(fn () => $this->cibleId ? User::find($this->cibleId) : null);

$updatedRecherche = function () {
    $this->pageUtilisateurs = 1;
};

$basculerActif = function (int $id) {
    if ($id === auth()->id()) {
        return;
    }

    $utilisateur = User::findOrFail($id);
    $utilisateur->update(['est_actif' => ! $utilisateur->est_actif]);
    $this->message = $utilisateur->est_actif
        ? "Accès de {$utilisateur->name} réactivé."
        : "Accès de {$utilisateur->name} révoqué.";
};

$forcerReinitialisation = function (int $id) {
    $utilisateur = User::findOrFail($id);
    $motDePasse = Str::password(12);

    $utilisateur->forceFill([
        'password' => Hash::make($motDePasse),
        'doit_changer_mot_de_passe' => true,
    ])->save();

    $this->journaliser($utilisateur, 'mot de passe réinitialisé (généré)');

    $this->motDePasseGenerePour = $utilisateur->name;
    $this->motDePasseGenere = $motDePasse;
    $this->fermerEcrasement();
};

/*
|--------------------------------------------------------------------------
| Écrasement manuel du mot de passe
|--------------------------------------------------------------------------
| Quand quelqu'un a perdu son mot de passe et qu'aucun courriel ne lui parvient,
| le super administrateur en fixe un nouveau de vive voix. L'opération est
| journalisée, et le compte est marqué comme devant changer ce mot de passe à la
| connexion suivante : celui saisi ici a transité par la voix ou par un message,
| il ne doit pas rester le mot de passe définitif.
*/
$ouvrirEcrasement = function (int $id) {
    $this->cibleId = $id;
    $this->nouveauMotDePasse = '';
    $this->nouveauMotDePasseConfirmation = '';
    $this->exigerChangement = true;
    $this->motDePasseGenere = null;
    $this->message = null;
    $this->resetValidation();
};

$fermerEcrasement = function () {
    $this->reset(['cibleId', 'nouveauMotDePasse', 'nouveauMotDePasseConfirmation']);
    $this->resetValidation();
};

$enregistrerMotDePasse = function () {
    // Le formulaire n'apparaît qu'une cible désignée ; un appel sans cible ne peut venir
    // que d'une requête forgée, on l'ignore sans rien changer ni rien révéler.
    $utilisateur = $this->cibleId ? User::find($this->cibleId) : null;

    if (! $utilisateur) {
        $this->fermerEcrasement();

        return;
    }

    $this->validate([
        'nouveauMotDePasse' => ['required', 'confirmed:nouveauMotDePasseConfirmation', Password::min(8)->letters()->numbers()],
    ], [
        'nouveauMotDePasse.confirmed' => 'Les deux mots de passe saisis ne correspondent pas.',
    ], [
        'nouveauMotDePasse' => 'mot de passe',
    ]);

    $utilisateur->forceFill([
        'password' => Hash::make($this->nouveauMotDePasse),
        'doit_changer_mot_de_passe' => (bool) $this->exigerChangement,
    ])->save();

    $this->journaliser($utilisateur, 'mot de passe redéfini manuellement');

    $this->message = "Mot de passe de {$utilisateur->name} remplacé."
        .($this->exigerChangement ? ' Il devra en choisir un autre à sa prochaine connexion.' : '');

    $this->fermerEcrasement();
};

/** Toute intervention sur un mot de passe laisse une trace nominative dans le journal. */
$journaliser = function (User $utilisateur, string $action) {
    activity()
        ->causedBy(auth()->user())
        ->performedOn($utilisateur)
        ->withProperties(['email' => $utilisateur->email])
        ->log($action);
};

?>

<x-a-venir titre="Gestion des accès"
        description="Créer, révoquer et redéfinir le mot de passe de toute personne, toutes entreprises confondues.">
        <a href="{{ route('super-admin.acces.creer') }}" wire:navigate
           style="display:inline-block; background:#C8102E; color:#fff; border-radius:8px; padding:9px 16px; font-weight:700; font-size:14.5px; text-decoration:none; margin-bottom:16px;">
            + Créer un accès
        </a>

        <div style="margin-bottom:16px; max-width:340px;">
            <input type="search" wire:model.live.debounce.300ms="recherche" class="champ"
                placeholder="Rechercher un nom ou un e-mail…">
        </div>

        @if ($message)
            <div class="encart encart-succes">{{ $message }}</div>
        @endif

        @if ($motDePasseGenere)
            <div style="background:#FFFBEA; border:1px solid #D97706; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:14px;">
                Nouveau mot de passe provisoire pour <b>{{ $motDePasseGenerePour }}</b> :
                <code style="background:#fff; border:1px solid #D9770655; border-radius:6px; padding:2px 8px; font-size:14.5px; margin:0 4px;">{{ $motDePasseGenere }}</code>
                — à transmettre à la personne concernée. Elle devra le changer à sa prochaine connexion.
            </div>
        @endif

        @if ($this->cible)
            <div class="carte" style="margin-bottom:16px; border:1px solid #C8102E44;">
                <h3 class="titre-section">Nouveau mot de passe — {{ $this->cible->name }}</h3>
                <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px; line-height:1.55;">
                    {{ $this->cible->email }} · à utiliser lorsque la personne a perdu son mot de passe.
                    L'opération est enregistrée dans le journal.
                </p>

                <div class="bloc-saisie">
                    <x-champ label="Nouveau mot de passe" model="nouveauMotDePasse" type="password" width="220" />
                    <x-champ label="Confirmer" model="nouveauMotDePasseConfirmation" type="password" width="220" />
                    <x-champ label="Devra le changer à la prochaine connexion" model="exigerChangement" type="checkbox" />
                </div>

                <p style="font-size:12px; color:var(--th-gris,#6B6E76); margin:10px 0 14px;">
                    8 caractères minimum, avec au moins une lettre et un chiffre.
                </p>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" wire:click="enregistrerMotDePasse" class="bouton bouton-sombre">
                        Enregistrer le mot de passe
                    </button>
                    <button type="button" wire:click="fermerEcrasement" class="bouton bouton-secondaire">Annuler</button>
                </div>
            </div>
        @endif

        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Entreprise</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->utilisateurs->forPage($pageUtilisateurs, 10) as $utilisateur)
                        <tr wire:key="acces-{{ $utilisateur->id }}" style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td>
                                <div style="font-weight:600;">{{ $utilisateur->name }}</div>
                                <div style="color:#6B6E76; font-size:13.5px;">{{ $utilisateur->email }}</div>
                            </td>
                            <td>{{ $utilisateur->entreprise?->nom ?? '— Plateforme —' }}</td>
                            <td>{{ $this->roles[$utilisateur->id] ?? '—' }}</td>
                            <td>
                                @if ($utilisateur->est_actif)
                                    <span style="color:#0E9F6E; font-weight:600;">Actif</span>
                                @else
                                    <span style="color:#C8102E; font-weight:600;">Révoqué</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if ($utilisateur->id !== auth()->id())
                                    <button type="button" wire:click="basculerActif({{ $utilisateur->id }})"
                                        wire:confirm="{{ $utilisateur->est_actif ? 'Révoquer l\'accès de '.$utilisateur->name.' ?' : 'Réactiver l\'accès de '.$utilisateur->name.' ?' }}"
                                        style="background:transparent; border:1px solid {{ $utilisateur->est_actif ? '#C8102E55' : '#0E9F6E55' }}; color:{{ $utilisateur->est_actif ? '#C8102E' : '#0E9F6E' }}; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer; margin-right:6px;">
                                        {{ $utilisateur->est_actif ? 'Révoquer' : 'Réactiver' }}
                                    </button>
                                    <button type="button" wire:click="ouvrirEcrasement({{ $utilisateur->id }})"
                                        style="background:transparent; border:1px solid #C8102E55; color:#C8102E; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer; margin-right:6px;">
                                        Définir un mot de passe
                                    </button>
                                    <button type="button" wire:click="forcerReinitialisation({{ $utilisateur->id }})"
                                        wire:confirm="Générer un nouveau mot de passe provisoire pour {{ $utilisateur->name }} ?"
                                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer;">
                                        Générer
                                    </button>
                                @else
                                    <span style="color:#9A9DA5; font-size:12.5px;">— vous-même —</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="5" texte="Aucun compte ne correspond à cette recherche." />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageUtilisateurs" :total="$this->utilisateurs->count()" prop="pageUtilisateurs" />
    </x-a-venir>
