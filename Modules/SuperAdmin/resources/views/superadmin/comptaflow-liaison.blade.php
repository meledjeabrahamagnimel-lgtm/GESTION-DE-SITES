<?php

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Exploitation\Modeles\LiaisonComptaflow;
use Modules\Noyau\Exploitation\Modeles\Tiers;
use Modules\Noyau\Exploitation\Modeles\CorrespondanceTiersComptaflow;
use Modules\Noyau\Exploitation\Services\ComptaflowClient;

?>

<div>
    <x-titre-ecran titre="Liaison COMPTAFLOW"
        sous-titre="Établir une liaison comptable entre une entreprise GESTION-DE-SITES et un dossier COMPTAFLOW. Réservé au superadministrateur." />

    @if (session('success'))
        <div class="carte" style="border-left:3px solid #15803D;margin-bottom:14px;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="carte" style="border-left:3px solid #B91C1C;margin-bottom:14px;">
            @foreach ($errors->all() as $error)
                <p style="margin:0 0 5px;">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="carte">
        <form method="POST" action="{{ route('super-admin.comptaflow.liaison.store') }}">
            @csrf
            <label for="entreprise_id">Entreprise GESTION-DE-SITES</label>
            <select id="entreprise_id" name="entreprise_id" class="champ" required>
                <option value="">Choisir une entreprise</option>
                @foreach (Entreprise::query()->orderBy('nom')->get() as $entreprise)
                    <option value="{{ $entreprise->id }}" @selected(old('entreprise_id') == $entreprise->id)>
                        {{ $entreprise->nom }} — #{{ $entreprise->id }}
                    </option>
                @endforeach
            </select>

            <label for="comptaflow_company_id" style="display:block;margin-top:14px;">Identifiant du dossier COMPTAFLOW</label>
            <input id="comptaflow_company_id" name="comptaflow_company_id" type="number" min="1"
                class="champ" value="{{ old('comptaflow_company_id') }}" required>

            <p style="font-size:12px;color:#6B7280;margin:10px 0 16px;">
                La liaison utilise une clé dédiée GESTION-DE-SITES. Elle ne remplace et ne modifie aucune liaison SELFLOW.
                Le dossier COMPTAFLOW doit exister et ne pas être déjà lié à SELFLOW.
            </p>
            <button type="submit" class="bouton">Établir la liaison</button>
        </form>
    </div>

    <div class="carte" style="margin-top:16px;">
        <h3>Liaisons enregistrées</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead><tr><th>Entreprise</th><th>Dossier COMPTAFLOW</th><th>Statut</th><th>Dernier déversement</th><th>Dernier retour</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse (LiaisonComptaflow::query()->with('entreprise')->latest()->get() as $liaison)
                        <tr>
                            <td>{{ $liaison->entreprise?->nom ?? 'Entreprise supprimée' }}</td>
                            <td>{{ $liaison->comptaflow_company_id }}</td>
                            <td>{{ $liaison->statut }}</td>
                            <td>{{ $liaison->dernier_deversement_le?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                            <td>{{ $liaison->dernier_message ?? 'Aucun retour' }}</td>
                            <td>
                                <form method="POST" action="{{ route('super-admin.comptaflow.liaison.verifier', $liaison) }}">
                                    @csrf
                                    <button type="submit" class="bouton">Vérifier</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune liaison COMPTAFLOW pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach (LiaisonComptaflow::query()->where('statut', 'active')->with('entreprise')->get() as $liaisonActive)
        <div class="carte" style="margin-top:16px;">
            <h3>Correspondance des tiers — {{ $liaisonActive->entreprise?->nom }}</h3>
            <p style="font-size:12px;color:#6B7280;">
                Associez chaque client ou autre tiers de GESTION-DE-SITES à un tiers déjà créé dans COMPTAFLOW.
                Aucun tiers comptable n'est créé depuis cet écran.
            </p>
            @php
                $tiersLocaux = Tiers::query()->where('entreprise_id', $liaisonActive->entreprise_id)->orderBy('nom')->get();
                $correspondances = CorrespondanceTiersComptaflow::query()->where('entreprise_id', $liaisonActive->entreprise_id)->get()->keyBy('tiers_id');
                try {
                    $tiersComptaflow = collect(app(ComptaflowClient::class)->listerTiers($liaisonActive));
                    $erreurChargementTiers = null;
                } catch (\Throwable $exception) {
                    $tiersComptaflow = collect();
                    $erreurChargementTiers = $exception->getMessage();
                }
            @endphp

            @if ($erreurChargementTiers)
                <p style="color:#B91C1C;">Impossible de charger les tiers COMPTAFLOW : {{ $erreurChargementTiers }}</p>
            @elseif ($tiersComptaflow->isEmpty())
                <p style="color:#B45309;">Aucun tiers trouvé dans le dossier COMPTAFLOW. Créez-les d'abord dans COMPTAFLOW, puis rechargez cette page.</p>
            @endif

            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead><tr><th>Tiers GESTION-DE-SITES</th><th>Rôle</th><th>Tiers COMPTAFLOW associé</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse ($tiersLocaux as $tiersLocal)
                            @php $correspondance = $correspondances->get($tiersLocal->id); @endphp
                            <tr>
                                <td>{{ $tiersLocal->nom }}</td>
                                <td>{{ $tiersLocal->role ?: '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('super-admin.comptaflow.liaison.tiers.associer') }}" style="display:flex;gap:8px;align-items:center;">
                                        @csrf
                                        <input type="hidden" name="entreprise_id" value="{{ $liaisonActive->entreprise_id }}">
                                        <input type="hidden" name="tiers_id" value="{{ $tiersLocal->id }}">
                                        <select name="comptaflow_tier_id" class="champ" required>
                                            <option value="">Choisir un tiers COMPTAFLOW</option>
                                            @foreach ($tiersComptaflow as $tiersCf)
                                                <option value="{{ $tiersCf['id'] }}" @selected((int) $correspondance?->comptaflow_tier_id === (int) $tiersCf['id'])>
                                                    {{ $tiersCf['numero_de_tiers'] }} — {{ $tiersCf['intitule'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="bouton" @disabled($tiersComptaflow->isEmpty())>Associer</button>
                                    </form>
                                    @if ($correspondance)
                                        <small style="color:#15803D;">Actuellement : {{ $correspondance->numero_de_tiers }} — {{ $correspondance->intitule }}</small>
                                    @endif
                                </td>
                                <td>{{ $correspondance ? 'Correspondance enregistrée' : 'À associer' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Aucun tiers GESTION-DE-SITES n'est encore enregistré.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
