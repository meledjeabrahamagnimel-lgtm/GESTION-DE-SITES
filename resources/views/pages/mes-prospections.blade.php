<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Services\GenerateurNumero;
use function Livewire\Volt\{state, computed, mount};

state([
    'date' => null,
    'client' => '', 'localisation' => '', 'moyen' => 'RDV',
    'activite' => 'Mécanique', 'passage' => false, 'devisApres' => false, 'observations' => '',
    'commentaire' => '',
    'message' => null,
]);

mount(function () {
    $this->date = now()->toDateString();
    $this->activite = $this->commercial?->activite ?? 'Mécanique';
});

$commercial = computed(fn () => Commercial::where('user_id', auth()->id())->with('site')->first());

$mesLignes = computed(fn () => $this->commercial
    ? Prospection::where('commercial_id', $this->commercial->id)->latest('date')->latest('id')->limit(60)->get()
    : collect());

$compteurs = computed(fn () => [
    'brouillon' => $this->mesLignes->where('statut_validation', 'Brouillon')->count(),
    'transmise' => $this->mesLignes->where('statut_validation', 'Transmise')->count(),
    'validee' => $this->mesLignes->where('statut_validation', 'Validée')->count(),
    'refusee' => $this->mesLignes->where('statut_validation', 'Refusée')->count(),
]);

$ajouter = function () {
    if (! $this->commercial) {
        return;
    }

    $donnees = $this->validate([
        'date' => ['required', 'date'],
        'client' => ['required', 'string', 'max:255'],
        'localisation' => ['nullable', 'string', 'max:255'],
        'moyen' => ['required', 'in:RDV,Téléphone,Mail'],
        'activite' => ['required', 'in:Mécanique,Carrosserie'],
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
        'passage' => $this->passage,
        'devis_apres_passage' => $this->devisApres,
        'observations' => $donnees['observations'] ?: null,
        'cree_par' => auth()->id(),
        // Saisie par le commercial : reste un brouillon tant qu'il ne l'a pas transmise.
        'statut_validation' => 'Brouillon',
    ]);

    $this->reset(['client', 'localisation', 'observations', 'passage', 'devisApres']);
    $this->moyen = 'RDV';
    unset($this->mesLignes, $this->compteurs);
    $this->message = 'Prospection enregistrée en brouillon. Transmettez-la à votre responsable quand elle est complète.';
};

$supprimer = function (int $id) {
    // On ne peut supprimer que ses propres brouillons : une ligne transmise appartient au responsable.
    Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')
        ->where('id', $id)
        ->delete();

    unset($this->mesLignes, $this->compteurs);
    $this->message = 'Brouillon supprimé.';
};

$transmettre = function () {
    $nombre = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')
        ->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

    unset($this->mesLignes, $this->compteurs);
    $this->message = $nombre > 0
        ? "$nombre prospection(s) transmise(s) à votre responsable de site."
        : 'Aucun brouillon à transmettre.';
};

?>

<div>
    @if (! $this->commercial)
        <x-a-venir titre="Aucune fiche commerciale associée"
            description="Votre compte n'est rattaché à aucune fiche commerciale. Contactez votre responsable de site." />
    @else
        <div class="carte" style="margin-bottom:16px; display:flex; flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
            <div>
                <h1 style="font-family:'Barlow Condensed',sans-serif; font-size:24px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin:0;">
                    {{ $this->commercial->nom }}
                </h1>
                <p style="color:var(--th-gris,#6B6E76); font-size:13px; margin:2px 0 0;">
                    {{ $this->commercial->site->nom }} · {{ $this->commercial->activite }} · N° {{ $this->commercial->numero }}
                </p>
            </div>
            <button type="button" wire:click="transmettre"
                wire:confirm="Transmettre tous vos brouillons à votre responsable de site ?"
                class="bouton" @disabled($this->compteurs['brouillon'] === 0)>
                Transmettre mes brouillons ({{ $this->compteurs['brouillon'] }})
            </button>
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

        <x-carte-section titre="Prospections">
            <div class="bloc-saisie">
                <x-champ label="Date" model="date" type="date" width="140" />
                <x-champ label="Clients visités" model="client" requis="true" />
                <x-champ label="Localisation" model="localisation" width="150" />
                <x-champ label="Moyens" model="moyen" type="select"
                    :options="['RDV' => 'RDV', 'Téléphone' => 'Téléphone', 'Mail' => 'Mail']" width="130" />
                <x-champ label="Activité" model="activite" type="select"
                    :options="['Mécanique' => 'Mécanique', 'Carrosserie' => 'Carrosserie']" width="150" />
                <x-champ label="Passage" model="passage" type="checkbox" />
                <x-champ label="Devis après passage" model="devisApres" type="checkbox" />
                <x-champ label="Observations" model="observations" />
                <button type="button" wire:click="ajouter" class="bouton bouton-sombre">+ Ajouter</button>
            </div>

            <div style="margin-top:12px;">
                <label class="champ-libelle">Commentaire à l'attention de votre responsable</label>
                <textarea wire:model="commentaire" rows="2" class="champ" style="resize:vertical;"
                    placeholder="Ex. : affluence en baisse (pluies), campagne en cours sur la zone industrielle..."></textarea>
            </div>

            <div class="tableau-conteneur" style="margin-top:14px;">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Clients visités</th>
                            <th>Localisation</th>
                            <th>Moyens</th>
                            <th>Activité</th>
                            <th>Passage</th>
                            <th>Devis après passage</th>
                            <th>Observations</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->mesLignes as $ligne)
                            @php
                                $pastille = [
                                    'Brouillon' => 'pastille-ambre',
                                    'Transmise' => 'pastille-bleu',
                                    'Validée' => 'pastille-vert',
                                    'Refusée' => 'pastille-rouge',
                                ][$ligne->statut_validation] ?? 'pastille-ambre';
                            @endphp
                            <tr wire:key="pros-{{ $ligne->id }}">
                                <td style="font-weight:700;">{{ $ligne->numero }}</td>
                                <td>{{ $ligne->date->format('d/m/Y') }}</td>
                                <td>{{ $ligne->client }}</td>
                                <td style="color:var(--th-gris,#6B6E76);">{{ $ligne->localisation ?? '—' }}</td>
                                <td>{{ $ligne->moyen }}</td>
                                <td>{{ $ligne->activite }}</td>
                                <td>{{ $ligne->passage ? '☑' : '☐' }}</td>
                                <td>{{ $ligne->devis_apres_passage ? '☑' : '☐' }}</td>
                                <td style="color:var(--th-gris,#6B6E76);">{{ $ligne->observations ?? '—' }}</td>
                                <td>
                                    <span class="pastille {{ $pastille }}">{{ $ligne->statut_validation }}</span>
                                    @if ($ligne->statut_validation === 'Refusée' && $ligne->motif_refus)
                                        <div style="font-size:11.5px; color:var(--th-accent,#C8102E); margin-top:3px;">{{ $ligne->motif_refus }}</div>
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
                            <x-table-vide :colspan="11" texte="Aucune prospection saisie pour le moment." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-carte-section>
    @endif
</div>
