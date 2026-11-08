<?php

use Illuminate\Support\Facades\DB;

$configurations = DB::table('configurations_ia')->get()->keyBy('fournisseur');
$fournisseurs = [
    'openai' => ['nom' => 'OpenAI', 'modele' => 'gpt-6'],
    'anthropic' => ['nom' => 'Anthropic', 'modele' => 'claude'],
    'google' => ['nom' => 'Google Gemini', 'modele' => 'gemini'],
    'openrouter' => ['nom' => 'OpenRouter', 'modele' => ''],
];

?>

<div>
    <x-titre-ecran titre="Intelligence artificielle"
        sous-titre="Configurer les fournisseurs et modèles utilisés pour proposer les imputations comptables. Accès réservé au superadministrateur." />

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

    <div class="carte" style="margin-bottom:16px;">
        <strong>Protection des clés</strong>
        <p style="margin:6px 0 0;color:#6B7280;font-size:13px;">
            Les clés API sont chiffrées en base. Elles ne sont jamais réaffichées.
            Laisser le champ vide conserve la clé déjà enregistrée.
        </p>
    </div>

    @foreach ($fournisseurs as $code => $fournisseur)
        @php($configuration = $configurations->get($code))
        <div class="carte" style="margin-bottom:14px;">
            <h3 style="margin-top:0;">{{ $fournisseur['nom'] }}</h3>
            <form method="POST" action="{{ route('super-admin.ia.enregistrer') }}">
                @csrf
                <input type="hidden" name="fournisseur" value="{{ $code }}">

                <label for="modele-{{ $code }}">Modèle</label>
                <input id="modele-{{ $code }}" name="modele" class="champ"
                    value="{{ old('modele', $configuration->modele ?? $fournisseur['modele']) }}"
                    placeholder="Identifiant exact du modèle" required>

                <label for="cle-{{ $code }}" style="display:block;margin-top:12px;">Clé API</label>
                <input id="cle-{{ $code }}" name="cle_api" type="password" class="champ"
                    autocomplete="new-password"
                    placeholder="{{ $configuration ? 'Clé enregistrée — saisir uniquement pour la remplacer' : 'Saisir la clé API' }}">

                <label style="display:flex;align-items:center;gap:8px;margin:12px 0;">
                    <input type="checkbox" name="actif" value="1" @checked($configuration?->actif)>
                    Utiliser ce fournisseur pour les imputations IA
                </label>

                <p style="font-size:12px;color:#6B7280;">
                    Statut : {{ $configuration ? ($configuration->actif ? 'Actif' : 'Configuré, désactivé') : 'Non configuré' }}
                </p>
                <button type="submit" class="bouton">Enregistrer la configuration</button>
            </form>
        </div>
    @endforeach

    <div class="carte">
        <h3>Règles d'utilisation de l'IA comptable</h3>
        <ul>
            <li>L'IA propose les comptes à partir des libellés, pièces et règles de correspondance.</li>
            <li>Les comptes, journaux et tiers proposés doivent exister dans le référentiel COMPTAFLOW ou être créés par son mécanisme natif.</li>
            <li>Une proposition incertaine doit être signalée et soumise à validation humaine.</li>
            <li>L'IA ne déclenche jamais directement un déversement comptable sans validation.</li>
        </ul>
        <p style="font-size:12px;color:#6B7280;">
            L'écran enregistre les clés et les modèles. Le test réel des fournisseurs et le moteur d'imputation seront ajoutés dans une étape distincte.
        </p>
    </div>
</div>
