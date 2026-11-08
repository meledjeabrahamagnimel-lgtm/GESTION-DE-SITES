<?php

namespace Modules\SuperAdmin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ConfigurationIAController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $request->validate([
            'fournisseur' => ['required', 'in:openai,anthropic,google,openrouter'],
            'modele' => ['required', 'string', 'max:150'],
            'cle_api' => ['nullable', 'string', 'max:4096'],
            'actif' => ['nullable', 'boolean'],
        ]);

        $configuration = DB::table('configurations_ia')
            ->where('fournisseur', $data['fournisseur'])
            ->first();

        $payload = [
            'modele' => trim($data['modele']),
            'actif' => $request->boolean('actif'),
            'updated_at' => now(),
        ];

        if (! $configuration) {
            if (blank($data['cle_api'] ?? null)) {
                return back()->withErrors(['cle_api' => 'Une clé API est obligatoire pour le premier enregistrement.']);
            }

            $payload += [
                'fournisseur' => $data['fournisseur'],
                'cle_api_chiffree' => Crypt::encryptString($data['cle_api']),
                'created_at' => now(),
            ];

            DB::table('configurations_ia')->insert($payload);
        } else {
            if (filled($data['cle_api'] ?? null)) {
                $payload['cle_api_chiffree'] = Crypt::encryptString($data['cle_api']);
            }

            DB::table('configurations_ia')
                ->where('fournisseur', $data['fournisseur'])
                ->update($payload);
        }

        return back()->with('success', 'Configuration IA enregistrée. La clé est conservée chiffrée.');
    }
}
