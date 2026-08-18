<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\Noyau\Entreprises\Actions\CreerAcces;
use Modules\Noyau\Entreprises\Actions\SupprimerAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
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

    // Accès cochés en vue d'une activation groupée. Préparer quatorze accès puis les
    // ouvrir un par un, c'est quatorze allers-retours pour un seul geste réel.
    'selection' => [],

    // Restreint l'annuaire téléchargé à une entreprise ; vide, il les couvre toutes.
    'annuaireEntrepriseId' => '',
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

$entreprises = computed(fn () => Entreprise::orderBy('nom')->pluck('nom', 'id'));

/** Accès inactifs de la recherche courante : ce sont eux que l'activation groupée vise. */
$inactifs = computed(fn () => $this->utilisateurs->where('est_actif', false)->pluck('id')->all());

$updatedRecherche = function () {
    $this->pageUtilisateurs = 1;
    // La recherche change : garder des cases cochées sur des lignes devenues invisibles
    // ferait activer des accès qu'on ne voit plus au moment de valider.
    $this->selection = [];
};

$basculerActif = function (int $id) {
    if ($id === auth()->id()) {
        return;
    }

    $utilisateur = User::findOrFail($id);

    if ($utilisateur->est_actif) {
        $utilisateur->update(['est_actif' => false]);
        $this->message = "Accès de {$utilisateur->name} révoqué.";

        return;
    }

    // C'est l'action qui ouvre l'accès, car c'est elle qui sait souhaiter la bienvenue :
    // un accès préparé inactif n'a reçu aucun courriel, il le reçoit maintenant.
    app(CreerAcces::class)->activer($utilisateur);
    $this->message = "Accès de {$utilisateur->name} activé — courriel de bienvenue envoyé.";
};

/*
|--------------------------------------------------------------------------
| Activation groupée
|--------------------------------------------------------------------------
| Quatorze accès préparés le même jour s'ouvrent le même jour. Les cocher puis
| valider en une fois évite quatorze confirmations identiques — et surtout évite
| d'en oublier un, ce qui ne se voit qu'au moment où la personne ne peut pas entrer.
|
| La sélection ne porte que sur des accès inactifs : cocher un accès déjà ouvert
| n'aurait aucun effet, et lui renverrait au mieux un second courriel de bienvenue.
*/
$toutSelectionner = function () {
    $this->selection = array_map('strval', $this->inactifs);
};

$viderSelection = function () {
    $this->selection = [];
};

$activerSelection = function (CreerAcces $action) {
    // On repart de la base plutôt que de la liste reçue : les identifiants viennent du
    // navigateur, et seuls comptent ceux qui existent, sont bien inactifs, et ne sont
    // pas le compte de celui qui clique.
    $comptes = User::whereIn('id', array_map('intval', $this->selection))
        ->where('est_actif', false)
        ->where('id', '!=', auth()->id())
        ->get();

    if ($comptes->isEmpty()) {
        $this->message = 'Aucun accès inactif dans la sélection : rien à activer.';
        $this->selection = [];

        return;
    }

    $ouverts = $comptes->filter(fn (User $compte) => $action->activer($compte));

    activity()
        ->causedBy(auth()->user())
        ->withProperties(['comptes' => $ouverts->pluck('email')->all()])
        ->log('Activation groupée de '.$ouverts->count().' accès');

    $this->selection = [];
    $this->message = $ouverts->count().' accès '.($ouverts->count() > 1 ? 'activés' : 'activé')
        .' — courriel de bienvenue envoyé à '.$ouverts->pluck('name')->implode(', ').'.';
};

/*
|--------------------------------------------------------------------------
| Suppression d'un accès
|--------------------------------------------------------------------------
| Actif ou préparé, un accès se supprime — quelqu'un s'en va, ou l'on s'est trompé
| d'adresse en le créant. Ce que la personne a saisi, en revanche, reste : une
| prospection appartient à l'entreprise, pas à celui qui l'a tapée. L'action s'en
| charge et dit ce qu'elle a fait de la fiche commerciale.
*/
$supprimer = function (int $id, SupprimerAcces $action) {
    $cible = User::find($id);

    if (! $cible) {
        return;
    }

    try {
        $bilan = $action->executer(auth()->user(), $cible);
    } catch (\RuntimeException $e) {
        $this->message = null;
        $this->addError('suppression', $e->getMessage());

        return;
    }

    // La ligne disparaît : une case restée cochée désignerait un compte absent.
    $this->selection = array_values(array_diff($this->selection, [(string) $id]));
    $this->resetErrorBag('suppression');
    unset($this->utilisateurs, $this->roles, $this->inactifs);

    $this->message = "Accès de {$cible->name} supprimé — fiche commerciale : {$bilan['fiche commerciale']}.";
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
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
            <a href="{{ route('super-admin.acces.creer') }}" wire:navigate
               style="display:inline-block; background:#C8102E; color:#fff; border-radius:8px; padding:9px 16px; font-weight:700; font-size:14.5px; text-decoration:none;">
                + Créer un accès
            </a>

            {{-- Le téléchargement n'est pas une action Livewire : il lui faut une vraie
                 réponse HTTP, donc un lien ordinaire — et surtout pas wire:navigate,
                 qui chargerait le PDF en arrière-plan sans jamais l'ouvrir. --}}
            <a href="{{ route('super-admin.annuaire', $annuaireEntrepriseId ? ['entreprise' => $annuaireEntrepriseId] : []) }}"
               style="display:inline-block; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:8px; padding:9px 16px; font-weight:700; font-size:14.5px; text-decoration:none; background:#fff;">
                ↓ Annuaire PDF
            </a>

            <select wire:model.live="annuaireEntrepriseId"
                style="padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px; background:#fff; max-width:250px;">
                <option value="">Toutes les entreprises</option>
                @foreach ($this->entreprises as $id => $nom)
                    <option value="{{ $id }}">{{ $nom }}</option>
                @endforeach
            </select>
        </div>

        <p style="font-size:12px; color:#9A9DA5; margin:-8px 0 16px; max-width:640px;">
            L'annuaire liste rôle, nom, adresse et périmètre de chaque personne, groupés par
            entreprise puis par ville. Le gérant et le superviseur en téléchargent chacun le leur,
            depuis leur propre écran.
        </p>

        <div style="margin-bottom:16px; max-width:340px;">
            <input type="search" wire:model.live.debounce.300ms="recherche" class="champ"
                placeholder="Rechercher un nom ou un e-mail…">
        </div>

        {{-- Activation groupée : la barre ne s'affiche que s'il y a matière, pour ne pas
             suggérer une action impossible quand tout est déjà ouvert. --}}
        @if (count($this->inactifs) > 0)
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#FFFBEA; border:1px solid #D9770633; border-radius:8px; padding:10px 14px; margin-bottom:16px;">
                <span style="font-size:13.5px; color:#4B4E55;">
                    <b>{{ count($this->inactifs) }}</b> accès préparé{{ count($this->inactifs) > 1 ? 's' : '' }},
                    en attente d'activation.
                    @if (count($selection))
                        <b>{{ count($selection) }}</b> sélectionné{{ count($selection) > 1 ? 's' : '' }}.
                    @endif
                </span>

                <button type="button" wire:click="toutSelectionner"
                    style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:6px; padding:6px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">
                    Tout sélectionner
                </button>

                @if (count($selection))
                    <button type="button" wire:click="viderSelection"
                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76; border-radius:6px; padding:6px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">
                        Tout décocher
                    </button>

                    <button type="button" wire:click="activerSelection"
                        wire:confirm="Activer les {{ count($selection) }} accès sélectionnés ? Un courriel de bienvenue partira vers chacun."
                        style="background:#0E9F6E; border:0; color:#fff; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:700; cursor:pointer;">
                        Activer la sélection ({{ count($selection) }})
                    </button>
                @endif
            </div>
        @endif

        @if ($message)
            <div class="encart encart-succes">{{ $message }}</div>
        @endif

        @error('suppression')
            <div class="encart encart-alerte">{{ $message }}</div>
        @enderror

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
                        <th style="width:28px;"></th>
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
                                {{-- La case n'apparaît que sur un accès préparé : cocher un accès
                                     déjà ouvert n'aurait rien à activer. --}}
                                @unless ($utilisateur->est_actif)
                                    <input type="checkbox" wire:model.live="selection" value="{{ $utilisateur->id }}"
                                        aria-label="Sélectionner {{ $utilisateur->name }}">
                                @endunless
                            </td>
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
                            <td style="text-align:right; white-space:nowrap;">
                                @if ($utilisateur->id !== auth()->id())
                                    {{-- L'ecran de creation sert aussi a reprendre : les champs sont
                                         les memes, et un formulaire logé dans une cellule de tableau
                                         devenait illisible des que l'adresse depassait la colonne. --}}
                                    <a href="{{ route('super-admin.acces.modifier', $utilisateur) }}" wire:navigate
                                        style="display:inline-block; text-decoration:none; background:transparent; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; margin-right:6px;">
                                        Modifier
                                    </a>
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
                                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer; margin-right:6px;">
                                        Générer
                                    </button>

                                    {{-- Supprimer efface le compte, actif ou préparé. Ce que la
                                         personne a saisi reste : la fiche commerciale qui porte
                                         des écritures est conservée en Inactif, pas détruite. --}}
                                    <button type="button" wire:click="supprimer({{ $utilisateur->id }})"
                                        wire:confirm="Supprimer définitivement l'accès de {{ $utilisateur->name }} ({{ $utilisateur->email }}) ?&#10;&#10;La personne ne pourra plus se connecter. Ses saisies, elles, sont conservées : elles appartiennent à l'entreprise."
                                        style="background:#C8102E; border:0; color:#fff; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer;">
                                        Supprimer
                                    </button>
                                @else
                                    <span style="color:#9A9DA5; font-size:12.5px;">— vous-même —</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="6" texte="Aucun compte ne correspond à cette recherche." />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageUtilisateurs" :total="$this->utilisateurs->count()" prop="pageUtilisateurs" />
    </x-a-venir>
