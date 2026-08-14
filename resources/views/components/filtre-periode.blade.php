@props([
    'periode', 'villes' => null, 'villeUnique' => null, 'villeFiltre' => null, 'activiteFiltre' => null,
    'moisFiltre' => null, 'semaineFiltre' => null, 'jourFiltre' => null,
    'masquerActivite' => false, 'commerciaux' => null, 'commercialFiltre' => null,
])

@php
    use App\Domain\Shared\Services\PeriodeCalculateur;
    use Carbon\Carbon;

    // La précision Mécanique/Sinistre/Consolidé n'a de sens qu'une fois une ville
    // précise en contexte : soit l'utilisateur n'en a qu'une (fixe), soit le Gérant
    // vient d'en choisir une dans la liste. Le caissier fait exception : sa
    // comptabilité reste toujours consolidée à l'échelle de la ville, jamais scindée
    // par activité.
    $afficherPrecision = ! $masquerActivite && ($villeUnique !== null || ($villes !== null && $villeFiltre));

    // Le sélecteur de commercial suit la même logique : il n'apparaît qu'une fois la
    // ville connue (fixe ou choisie), et seulement si la page en fournit la liste.
    $afficherCommercial = $afficherPrecision && $commerciaux !== null;

    $mois = PeriodeCalculateur::moisDeLAnnee();
    $anneeEnCours = Carbon::today()->year;

    $semainesDuMois = $moisFiltre
        ? PeriodeCalculateur::semainesDuMois(Carbon::create($anneeEnCours, (int) $moisFiltre, 1))
        : [];

    $joursOptions = [];
    if ($semaineFiltre && $semainesDuMois) {
        $semaineChoisie = $semainesDuMois[(int) $semaineFiltre - 1] ?? null;
        if ($semaineChoisie) {
            $nb = $semaineChoisie['debut']->diffInDays($semaineChoisie['fin']) + 1;
            $joursOptions = range(1, $nb);
        }
    } elseif ($moisFiltre) {
        $joursOptions = range(1, Carbon::create($anneeEnCours, (int) $moisFiltre, 1)->daysInMonth);
    }
@endphp

<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
    <div style="display:flex; gap:6px;">
        <button type="button" wire:click="$set('periode', 'calendrier')"
            class="onglet {{ $periode === 'calendrier' ? 'est-actif' : '' }}">Calendrier</button>
        <button type="button" wire:click="$set('periode', 'periode')"
            class="onglet {{ $periode === 'periode' ? 'est-actif' : '' }}">Période</button>
    </div>

    @if ($villeUnique)
        <span style="font-size:13.5px; font-weight:700; color:var(--th-ink,#191B20); padding:9px 14px; background:#F4F3EF; border-radius:8px; white-space:nowrap;">
            Ville : {{ $villeUnique->nom }}
        </span>
    @elseif ($villes !== null)
        <select wire:model.live="villeFiltre" class="champ" style="width:auto; font-weight:600; background:#fff;">
            <option value="">Toutes les villes (consolidé)</option>
            @foreach ($villes as $ville)
                <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
            @endforeach
        </select>
    @endif

    @if ($afficherPrecision)
        <select wire:model.live="activiteFiltre" class="champ" style="width:auto; font-weight:600; background:#fff;">
            <option value="">Consolidé (les deux sites)</option>
            <option value="Mécanique">Mécanique</option>
            <option value="Sinistre">Sinistre</option>
        </select>
    @endif

    @if ($afficherCommercial)
        <select wire:model.live="commercialFiltre" class="champ" style="width:auto; font-weight:600; background:#fff;">
            <option value="">Tous les commerciaux</option>
            @foreach ($commerciaux->where('est_spontane', false) as $commercial)
                <option value="{{ $commercial->id }}">{{ $commercial->nom }}</option>
            @endforeach
            {{-- Un « Client spontané » par site (Mécanique et Sinistre) : une seule entrée
                 à l'écran, qui filtre sur l'ensemble d'entre eux à la fois. --}}
            @if ($commerciaux->contains('est_spontane', true))
                <option value="spontane">Client spontané</option>
            @endif
        </select>
    @endif
</div>

@if ($periode === 'calendrier')
    <div style="display:flex; gap:12px; align-items:center; margin:-4px 0 20px; font-size:14px; flex-wrap:wrap;">
        <span style="color:var(--th-gris,#6B6E76); font-weight:600;">Exercice {{ $anneeEnCours }}</span>

        <select wire:model.live="moisFiltre" class="champ" style="width:auto;">
            <option value="">Tous les mois</option>
            @foreach ($mois as $numero => $libelle)
                <option value="{{ $numero }}">{{ $libelle }}</option>
            @endforeach
        </select>

        <select wire:model.live="semaineFiltre" class="champ" style="width:auto;" @if (! $moisFiltre) disabled @endif>
            <option value="">Toutes les semaines</option>
            @foreach ($semainesDuMois as $semaine)
                <option value="{{ $semaine['numero'] }}">
                    Semaine {{ $semaine['numero'] }} ({{ $semaine['debut']->format('d/m') }} – {{ $semaine['fin']->format('d/m') }})
                </option>
            @endforeach
        </select>

        <select wire:model.live="jourFiltre" class="champ" style="width:auto;" @if (! $moisFiltre) disabled @endif>
            <option value="">Tous les jours</option>
            @foreach ($joursOptions as $j)
                <option value="{{ $j }}">Jour {{ $j }}</option>
            @endforeach
        </select>
    </div>
@else
    <div style="display:flex; gap:12px; align-items:center; margin:-4px 0 20px; font-size:14px;">
        <label style="color:var(--th-gris,#6B6E76); font-weight:600;">Du</label>
        <input type="month" wire:model.live="dateDebut" min="{{ $anneeEnCours }}-01" max="{{ $anneeEnCours }}-12" class="champ" style="width:auto;">
        <label style="color:var(--th-gris,#6B6E76); font-weight:600;">Au</label>
        <input type="month" wire:model.live="dateFin" min="{{ $anneeEnCours }}-01" max="{{ $anneeEnCours }}-12" class="champ" style="width:auto;">
    </div>
@endif
