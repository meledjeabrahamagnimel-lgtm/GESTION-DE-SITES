<?php

namespace Modules\\SuperAdmin\\Http\\Controllers;

use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Routing\\Controller;
use Modules\\Noyau\\Entreprises\\Modeles\\Entreprise;
use Modules\\Noyau\\Exploitation\\Modeles\\LiaisonComptaflow;
use Modules\\Noyau\\Exploitation\\Services\\ComptaflowClient;
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
}
