<?php

namespace Modules\SuperAdmin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Exploitation\Modeles\LiaisonComptaflow;
use Modules\Noyau\Exploitation\Modeles\Tiers;
use Modules\Noyau\Exploitation\Modeles\CorrespondanceTiersComptaflow;
use Modules\Noyau\Exploitation\Services\ComptaflowClient;
use Throwable;

class LiaisonComptaflowController extends Controller
{
    public function store(Request $request, ComptaflowClient $client): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'comptaflow_company_id' => ['required', 'integer', 'min:1'],
        ]);

        $entreprise = Entreprise::query()->findOrFail($data['entreprise_id']);
        $existante = LiaisonComptaflow::query()->where('entreprise_id', $entreprise->id)->first();
        if ($existante && $existante->statut === 'active') {
            return back()->withErrors(['entreprise_id' => 'Cette entreprise possède déjà une liaison COMPTAFLOW active.']);
        }

        try {
            $reponse = $client->provisionner((int) $entreprise->id, (int) $data['comptaflow_company_id']);
            $liaison = $existante ?? new LiaisonComptaflow();
            $liaison->entreprise_id = $entreprise->id;
            $liaison->comptaflow_company_id = (int) $reponse['company_id'];
            $liaison->definirCle((string) $reponse['sync_key']);
            $liaison->statut = 'active';
            $liaison->liee_le = now();
            $liaison->liee_par = $request->user()->id;
            $liaison->dernier_message = $reponse['message'] ?? 'Liaison établie.';
            $liaison->save();

            return back()->with('success', 'Liaison COMPTAFLOW établie pour '.$entreprise->nom.'.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['comptaflow_company_id' => $e->getMessage()]);
        }
    }

    public function associerTiers(Request $request, ComptaflowClient $client): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'tiers_id' => ['required', 'integer'],
            'comptaflow_tier_id' => ['required', 'integer', 'min:1'],
        ]);

        $liaison = LiaisonComptaflow::query()
            ->where('entreprise_id', $data['entreprise_id'])
            ->where('statut', 'active')
            ->firstOrFail();

        $tiers = Tiers::query()
            ->where('entreprise_id', $liaison->entreprise_id)
            ->findOrFail($data['tiers_id']);

        try {
            $tiersDistant = collect($client->listerTiers($liaison))
                ->first(fn ($tiersCf) => (int) ($tiersCf['id'] ?? 0) === (int) $data['comptaflow_tier_id']);

            if (!$tiersDistant) {
                return back()->withErrors(['comptaflow_tier_id' => 'Ce tiers n’existe plus dans le dossier COMPTAFLOW. Rechargez la page.']);
            }

            CorrespondanceTiersComptaflow::query()->updateOrCreate(
                ['entreprise_id' => $liaison->entreprise_id, 'tiers_id' => $tiers->id],
                [
                    'comptaflow_tier_id' => (int) $tiersDistant['id'],
                    'numero_de_tiers' => $tiersDistant['numero_de_tiers'] ?? null,
                    'intitule' => $tiersDistant['intitule'] ?? null,
                    'associe_par' => $request->user()->id,
                ]
            );

            return back()->with('success', 'Le tiers « '.$tiers->nom.' » est maintenant associé au tiers COMPTAFLOW « '.($tiersDistant['intitule'] ?? $tiersDistant['numero_de_tiers'] ?? 'sans intitulé').' ».');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['comptaflow_tier_id' => 'Impossible de récupérer les tiers COMPTAFLOW : '.$e->getMessage()]);
        }
    }

    public function associerFournisseur(Request $request, ComptaflowClient $client): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'fournisseur_id' => ['required', 'integer'],
            'comptaflow_tier_id' => ['required', 'integer', 'min:1'],
        ]);

        $liaison = LiaisonComptaflow::query()
            ->where('entreprise_id', $data['entreprise_id'])
            ->where('statut', 'active')
            ->firstOrFail();

        $fournisseur = FournisseurReferentiel::query()
            ->where('entreprise_id', $liaison->entreprise_id)
            ->findOrFail($data['fournisseur_id']);

        try {
            $tiersDistant = collect($client->listerTiers($liaison))
                ->first(fn ($tiersCf) => (int) ($tiersCf['id'] ?? 0) === (int) $data['comptaflow_tier_id']);

            if (! $tiersDistant) {
                return back()->withErrors(['comptaflow_tier_id' => 'Ce tiers n’existe plus dans le dossier COMPTAFLOW. Rechargez la page.']);
            }

            CorrespondanceFournisseurComptaflow::query()->updateOrCreate(
                ['entreprise_id' => $liaison->entreprise_id, 'fournisseur_id' => $fournisseur->id],
                [
                    'comptaflow_tier_id' => (int) $tiersDistant['id'],
                    'numero_de_tiers' => $tiersDistant['numero_de_tiers'] ?? null,
                    'intitule' => $tiersDistant['intitule'] ?? null,
                    'associe_par' => $request->user()->id,
                ]
            );

            return back()->with('success', 'Le fournisseur « '.$fournisseur->nom.' » est maintenant associé au tiers COMPTAFLOW « '.($tiersDistant['intitule'] ?? $tiersDistant['numero_de_tiers'] ?? 'sans intitulé').' ».');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['comptaflow_tier_id' => 'Impossible de récupérer les tiers COMPTAFLOW : '.$e->getMessage()]);
        }
    }

    public function verifier(LiaisonComptaflow $liaison, Request $request, ComptaflowClient $client): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        try {
            $reponse = $client->verifier($liaison);
            $liaison->statut = 'active';
            $liaison->dernier_message = $reponse['message'] ?? 'Liaison vérifiée avec COMPTAFLOW.';
            $liaison->save();

            return back()->with('success', 'La liaison avec le dossier COMPTAFLOW n° '.$liaison->comptaflow_company_id.' est valide.');
        } catch (Throwable $e) {
            report($e);
            $liaison->statut = 'erreur';
            $liaison->dernier_message = mb_substr($e->getMessage(), 0, 2000);
            $liaison->save();

            return back()->withErrors(['liaison' => 'Vérification impossible : '.$e->getMessage()]);
        }
    }
}
