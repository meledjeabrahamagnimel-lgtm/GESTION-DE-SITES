<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert le logo d'une entreprise par l'application, quand le disque public ne peut pas
 * le servir lui-même.
 *
 * Les fichiers téléversés vivent dans `storage/app/public` et ne sont accessibles au
 * navigateur que par le lien symbolique `public/storage`. Ce lien n'est pas versionné :
 * il se crée avec `php artisan storage:link`, et sur un hébergement mutualisé il
 * disparaît à la première restauration, au premier déplacement de dossier, parfois sans
 * que personne s'en aperçoive. Le symptôme est silencieux — le logo cesse simplement
 * d'exister, dans l'application comme dans les courriels.
 *
 * Cette route est le filet. Elle ne lit jamais un chemin venu de la requête : elle
 * relit en base le `logo_chemin` de l'entreprise désignée, et ne sert que celui-là. Le
 * logo est par ailleurs une donnée publique — il s'affiche sur l'écran de connexion et
 * dans les courriels, qui sont lus hors session.
 */
class LogoEntrepriseController extends Controller
{
    /** Une journée : un logo change rarement, et le courriel peut être relu longtemps après. */
    private const CACHE_EN_SECONDES = 86400;

    public function __invoke(Request $request, Entreprise $entreprise): BinaryFileResponse
    {
        abort_unless((bool) $entreprise->logo_chemin, 404);

        $chemin = str_starts_with($entreprise->logo_chemin, 'public:')
            ? public_path(substr($entreprise->logo_chemin, 7))
            : Storage::disk('public')->path($entreprise->logo_chemin);

        abort_unless(is_file($chemin), 404);

        return response()
            ->file($chemin, [
                'Cache-Control' => 'public, max-age='.self::CACHE_EN_SECONDES,
                // Le fichier est une image de marque, jamais un téléchargement : sans
                // cela, certains clients proposent de l'enregistrer au lieu de l'afficher.
                'Content-Disposition' => 'inline',
            ])
            ->setAutoEtag();
    }
}
