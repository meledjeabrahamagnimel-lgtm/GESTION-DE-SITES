<?php

namespace Modules\Noyau\Commun\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Photo de profil : nom de fichier aléatoire, et suppression de l'ancienne. */
class EnregistreurPhoto
{
    public const REGLES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

    public function enregistrer(UploadedFile $fichier, User $utilisateur): string
    {
        $chemin = 'photos/'.$utilisateur->id.'-'.Str::random(8).'.'.$fichier->getClientOriginalExtension();

        Storage::disk('public')->put($chemin, file_get_contents($fichier->getRealPath()));

        if ($utilisateur->photo_chemin && $utilisateur->photo_chemin !== $chemin) {
            Storage::disk('public')->delete($utilisateur->photo_chemin);
        }

        return $chemin;
    }
}
