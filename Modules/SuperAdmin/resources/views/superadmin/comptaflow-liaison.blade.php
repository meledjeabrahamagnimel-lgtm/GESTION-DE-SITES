<?php

use Modules\\Noyau\\Entreprises\\Modeles\\Entreprise;
use Modules\\Noyau\\Exploitation\\Modeles\\LiaisonComptaflow;

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
                <thead><tr><th>Entreprise</th><th>Dossier COMPTAFLOW</th><th>Statut</th><th>Dernier déversement</th></tr></thead>
                <tbody>
                    @forelse (LiaisonComptaflow::query()->with('entreprise')->latest()->get() as $liaison)
                        <tr>
                            <td>{{ $liaison->entreprise?->nom ?? 'Entreprise supprimée' }}</td>
                            <td>{{ $liaison->comptaflow_company_id }}</td>
                            <td>{{ $liaison->statut }}</td>
                            <td>{{ $liaison->dernier_deversement_le?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucune liaison COMPTAFLOW pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
