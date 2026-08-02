<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Shared\Concerns\GereLesDonneesLibres;
use App\Domain\Shared\Models\Referentiel;
use Livewire\WithPagination;
use function Livewire\Volt\{state, computed, mount, uses, usesPagination};

uses([GereLesDonneesLibres::class, WithPagination::class]);
usesPagination();

state([
    'date' => null,
    'client' => '', 'localisation' => '', 'moyen' => 'RDV',
    'activite' => '', 'passage' => false, 'devisApres' => false, 'observations' => '',
    'commentaire' => '',
    'message' => null,
    'selection' => [],
]);

// Les filtres sont portés par l'adresse de la page : ils survivent au rechargement,
// au retour arrière et au partage du lien.
state([
    'fNumero' => '', 'fDate' => '', 'fActivite' => '',
    'fClient' => '', 'fStatut' => '',
])->url(except: '');

mount(function () {
    $this->date = now()->toDateString();
    $this->activite = $this->commercial?->activite ?? array_key_first($this->optionsActivite);
});

$commercial = computed(fn () => Commercial::where('user_id', auth()->id())->with('site')->first());

$optionsActivite = computed(fn () => Referentiel::options(Referentiel::ACTIVITE));

$optionsMoyen = computed(fn () => Referentiel::options(Referentiel::MOYEN_PROSPECTION));

/** Requête filtrée : chaque filtre est appliqué dès la frappe. */
$requete = computed(function () {
    $q = Prospection::where('commercial_id', $this->commercial?->id ?? 0)->with('donneesLibres');

    if ($this->fNumero !== '') {
        $q->where('numero', 'like', '%'.$this->fNumero.'%');
    }
    if ($this->fDate !== '') {
        $q->whereDate('date', $this->fDate);
    }
    if ($this->fActivite !== '') {
        $q->where('activite', $this->fActivite);
    }
    if ($this->fClient !== '') {
        $q->where('client', 'like', '%'.$this->fClient.'%');
    }
    if ($this->fStatut !== '') {
        $q->where('statut_validation', $this->fStatut);
    }

    return $q->latest('date')->latest('id');
});

$lignes = computed(fn () => $this->requete->paginate(20));

$compteurs = computed(function () {
    $base = Prospection::where('commercial_id', $this->commercial?->id ?? 0);

    return [
        'brouillon' => (clone $base)->where('statut_validation', 'Brouillon')->count(),
        'transmise' => (clone $base)->where('statut_validation', 'Transmise')->count(),
        'validee' => (clone $base)->where('statut_validation', 'Validée')->count(),
        'refusee' => (clone $base)->where('statut_validation', 'Refusée')->count(),
    ];
});

/** Identifiants des brouillons de la page courante, pour le « tout sélectionner ». */
$brouillonsAffiches = computed(fn () => $this->lignes->getCollection()
    ->where('statut_validation', 'Brouillon')->pluck('id')->all());

$selectionnes = computed(fn () => collect($this->selection)->filter()->keys()->map(fn ($i) => (int) $i)->all());

// Tout filtre modifié ramène à la première page, sinon on peut se retrouver sur une page vide.
$updatedFNumero = fn () => $this->resetPage();
$updatedFDate = fn () => $this->resetPage();
$updatedFActivite = fn () => $this->resetPage();
$updatedFClient = fn () => $this->resetPage();
$updatedFStatut = fn () => $this->resetPage();

$reinitialiserFiltres = function () {
    $this->reset(['fNumero', 'fDate', 'fActivite', 'fClient', 'fStatut']);
    $this->resetPage();
};

$toutSelectionner = function () {
    foreach ($this->brouillonsAffiches as $id) {
        $this->selection[$id] = true;
    }
};

$toutDeselectionner = function () {
    $this->selection = [];
};

$ajouter = function () {
    if (! $this->commercial) {
        return;
    }

    $donnees = $this->validate([
        'date' => ['required', 'date'],
        'client' => ['required', 'string', 'max:255'],
        'localisation' => ['nullable', 'string', 'max:255'],
        'moyen' => ['required', 'string', 'max:60'],
        'activite' => ['required', 'string', 'max:60'],
        'observations' => ['nullable', 'string'],
    ], [], ['client' => 'clients visités', 'activite' => 'activité']);

    Prospection::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->commercial->site_id,
        'commercial_id' => $this->commercial->id,
        'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'pro'),
        'date' => $donnees['date'],
        'client' => $donnees['client'],
        'localisation' => $donnees['localisation'] ?: null,
        'moyen' => $donnees['moyen'],
        'activite' => $donnees['activite'],
        // Cast explicite : une case jamais cochée peut remonter vide du navigateur,
        // et la colonne n'accepte pas NULL.
        'passage' => (bool) $this->passage,
        'devis_apres_passage' => (bool) $this->devisApres,
        'observations' => $donnees['observations'] ?: null,
        'cree_par' => auth()->id(),
        'statut_validation' => 'Brouillon',
    ]);

    $this->reset(['client', 'localisation', 'observations', 'passage', 'devisApres']);
    $this->resetPage();
    $this->message = 'Prospection enregistrée en brouillon. Sélectionnez-la puis transmettez-la à votre responsable.';
};

$supprimer = function (int $id) {
    Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')->where('id', $id)->delete();

    unset($this->selection[$id]);
    $this->message = 'Brouillon supprimé.';
};

$transmettreSelection = function () {
    $ids = $this->selectionnes;

    if (empty($ids)) {
        $this->message = 'Sélectionnez au moins un brouillon à transmettre.';

        return;
    }

    // On ne transmet que ses propres brouillons, jamais une ligne déjà arbitrée.
    $nombre = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')
        ->whereIn('id', $ids)
        ->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

    $this->selection = [];
    $this->message = $nombre > 0
        ? "$nombre prospection(s) transmise(s) à votre responsable de site."
        : 'Aucun brouillon transmis.';
};

?>

<div>
    @if (! $this->commercial)
        <x-a-venir titre="Aucune fiche commerciale associée"
            description="Votre compte n'est rattaché à aucune fiche commerciale. Contactez votre responsable de site." />
    @else
        <div class="carte" style="margin-bottom:14px; display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:12px;">
                <x-avatar :utilisateur="auth()->user()" :taille="44" />
                <div>
                    <h1 style="font-family:'Barlow Condensed',sans-serif; font-size:23px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin:0;">
                        {{ $this->commercial->nom }}
                    </h1>
                    <p style="color:var(--th-gris,#6B6E76); font-size:12.5px; margin:2px 0 0;">
                        {{ $this->commercial->site->nom }} · {{ $this->commercial->activite }} · N° {{ $this->commercial->numero }}
                    </p>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" wire:click="toutSelectionner" class="bouton bouton-secondaire bouton-petit">
                    Tout sélectionner ({{ count($this->brouillonsAffiches) }})
                </button>
                <button type="button" wire:click="toutDeselectionner" class="bouton bouton-secondaire bouton-petit">Aucun</button>
                <button type="button" wire:click="transmettreSelection"
                    wire:confirm="Transmettre les prospections sélectionnées à votre responsable ?"
                    class="bouton" @disabled(count($this->selectionnes) === 0)>
                    Transmettre la sélection ({{ count($this->selectionnes) }})
                </button>
            </div>
        </div>

        @if ($message)
            <div class="encart encart-succes">{{ $message }}</div>
        @endif

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(165px, 1fr)); gap:10px; margin-bottom:16px;">
            <x-kpi-card label="Brouillons" :value="$this->compteurs['brouillon']" sub="À transmettre" />
            <x-kpi-card label="Transmises" :value="$this->compteurs['transmise']" sub="En attente du responsable" />
            <x-kpi-card label="Validées" :value="$this->compteurs['validee']" :bon="true" />
            <x-kpi-card label="Refusées" :value="$this->compteurs['refusee']" :accent="$this->compteurs['refusee'] > 0" />
        </div>

        <x-carte-section titre="Nouvelle prospection">
            <div class="bloc-saisie">
                <x-champ label="Date" model="date" type="date" width="140" />
                <x-champ label="Clients visités" model="client" requis="true" />
                <x-champ label="Localisation" model="localisation" width="150" />
                <x-champ label="Moyens" model="moyen" type="select" :options="$this->optionsMoyen" width="140" />
                <x-champ label="Activité" model="activite" type="select" :options="$this->optionsActivite" width="150" />
                <x-champ label="Passage" model="passage" type="checkbox" />
                <x-champ label="Devis après passage" model="devisApres" type="checkbox" />
                <x-champ label="Observations" model="observations" />
                <button type="button" wire:click="ajouter" class="bouton bouton-sombre">+ Ajouter</button>
            </div>
            <p style="font-size:11.5px; color:#9A9DA5; margin:8px 0 0;">
                Une valeur manque dans « Moyens » ou « Activité » ?
                <a href="{{ route('mon-espace') }}" wire:navigate style="color:var(--th-accent,#C8102E); font-weight:600;">Ajoutez-la dans vos paramètres.</a>
            </p>

            <div style="margin-top:12px;">
                <label class="champ-libelle">Commentaire à l'attention de votre responsable</label>
                <textarea wire:model="commentaire" rows="2" class="champ" style="resize:vertical;"
                    placeholder="Ex. : affluence en baisse (pluies), campagne en cours sur la zone industrielle..."></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Mes prospections">
            {{-- Filtres : appliqués à la frappe et conservés dans l'adresse de la page. --}}
            <div class="bloc-saisie" style="background:#fff; border-style:solid;">
                <x-champ label="N°" model="fNumero" live="true" width="120" placeholder="P-00…" />
                <x-champ label="Date" model="fDate" type="date" live="true" width="150" />
                <x-champ label="Activité" model="fActivite" type="select" live="true" width="150"
                    :options="collect(['' => 'Toutes'])->union($this->optionsActivite)" />
                <x-champ label="Client" model="fClient" live="true" placeholder="Nom du client…" />
                <x-champ label="Statut" model="fStatut" type="select" live="true" width="150"
                    :options="['' => 'Tous', 'Brouillon' => 'Brouillon', 'Transmise' => 'Transmise', 'Validée' => 'Validée', 'Refusée' => 'Refusée']" />
                <button type="button" wire:click="reinitialiserFiltres" class="bouton bouton-secondaire bouton-petit">Réinitialiser</button>
            </div>

            <div class="tableau-conteneur" style="margin-top:12px;">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>✓</th><th>N°</th><th>Date</th><th>Clients visités</th><th>Localisation</th>
                            <th>Moyens</th><th>Activité</th><th>Passage</th><th>Devis après passage</th>
                            <th>Observations</th><th>Informations libres</th><th>Statut</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->lignes as $ligne)
                            @php
                                $pastille = [
                                    'Brouillon' => 'pastille-ambre', 'Transmise' => 'pastille-bleu',
                                    'Validée' => 'pastille-vert', 'Refusée' => 'pastille-rouge',
                                ][$ligne->statut_validation] ?? 'pastille-ambre';
                            @endphp
                            <tr wire:key="pros-{{ $ligne->id }}">
                                <td>
                                    @if ($ligne->statut_validation === 'Brouillon')
                                        <input type="checkbox" wire:model.live="selection.{{ $ligne->id }}">
                                    @endif
                                </td>
                                <td style="font-weight:700;">{{ $ligne->numero }}</td>
                                <td>{{ $ligne->date->format('d/m/Y') }}</td>
                                <td>{{ $ligne->client }}</td>
                                <td style="color:var(--th-gris,#6B6E76);">{{ $ligne->localisation ?? '—' }}</td>
                                <td>{{ $ligne->moyen }}</td>
                                <td>{{ $ligne->activite }}</td>
                                <td>{{ $ligne->passage ? '☑' : '☐' }}</td>
                                <td>{{ $ligne->devis_apres_passage ? '☑' : '☐' }}</td>
                                <td style="color:var(--th-gris,#6B6E76);">{{ $ligne->observations ?? '—' }}</td>
                                <td style="white-space:normal; min-width:230px;">
                                    <x-saisie-libre :sujet="$ligne"
                                        :ouvert="$libreSujetId === $ligne->id && $libreSujetType === get_class($ligne)" />
                                </td>
                                <td>
                                    <span class="pastille {{ $pastille }}">{{ $ligne->statut_validation }}</span>
                                    @if ($ligne->statut_validation === 'Refusée' && $ligne->motif_refus)
                                        <div style="font-size:11px; color:var(--th-accent,#C8102E); margin-top:3px; white-space:normal;">{{ $ligne->motif_refus }}</div>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    @if ($ligne->statut_validation === 'Brouillon')
                                        <button type="button" wire:click="supprimer({{ $ligne->id }})"
                                            wire:confirm="Supprimer ce brouillon ?"
                                            class="bouton bouton-secondaire bouton-petit">Supprimer</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="13" texte="Aucune prospection ne correspond à ces filtres." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px;">{{ $this->lignes->links() }}</div>
        </x-carte-section>
    @endif
</div>
