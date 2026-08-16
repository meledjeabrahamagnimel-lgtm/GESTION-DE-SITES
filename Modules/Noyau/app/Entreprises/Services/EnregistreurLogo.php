<?php

namespace Modules\Noyau\Entreprises\Services;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enregistrement du logo d'une entreprise sur le disque public.
 * Le fichier est renommé pour ne jamais exposer le nom d'origine, et l'ancien
 * logo est supprimé afin de ne pas accumuler de fichiers orphelins.
 */
class EnregistreurLogo
{
    /** Règles de validation à appliquer au champ d'upload. */
    public const REGLES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

    public function enregistrer(UploadedFile $fichier, ?Entreprise $entreprise = null, ?string $slug = null): string
    {
        $base = Str::slug($slug ?? $entreprise?->slug ?? 'entreprise');
        $chemin = 'logos/'.$base.'-'.Str::random(8).'.'.$fichier->getClientOriginalExtension();

        Storage::disk('public')->put($chemin, file_get_contents($fichier->getRealPath()));

        if ($entreprise?->logo_chemin && $entreprise->logo_chemin !== $chemin) {
            Storage::disk('public')->delete($entreprise->logo_chemin);
        }

        return $chemin;
    }
}
