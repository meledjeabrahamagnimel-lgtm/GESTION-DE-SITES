<?php

namespace Modules\Noyau\Entreprises\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Support\LibellesRoles;

/**
 * Qui travaille où, et à quel titre.
 *
 * Le même service sert les trois lecteurs, et c'est le lecteur qui détermine ce qu'il
 * voit — jamais l'écran d'où il part. Un périmètre calculé au moment de l'affichage
 * puis passé en paramètre finit tôt ou tard par arriver depuis l'extérieur ; ici, la
 * seule chose reçue est l'identité de celui qui demande.
 *
 *   Super Admin ......... toutes les entreprises, ou celle qu'il désigne
 *   Gérant .............. son entreprise entière
 *   Superviseur de ville  sa ville, et rien d'autre
 *
 * On s'arrête là : un responsable de site n'encadre qu'un lieu, dont il connaît déjà
 * les quelques noms, et un commercial n'encadre personne.
 */
class Annuaire
{
    /** Du plus large au plus étroit : un annuaire se lit de la direction vers le terrain. */
    private const ORDRE = ['gerant', 'responsable_ville', 'responsable_site', 'caissier', 'commercial'];

    /** Personnes sans ville : la direction, dont le périmètre est l'entreprise entière. */
    public const HORS_VILLE = 'Direction';

    /** Vrai si ce lecteur a droit à un annuaire. */
    public static function ouvertA(User $lecteur): bool
    {
        return $lecteur->hasRole('super_admin')
            || $lecteur->hasRole('gerant')
            || $lecteur->hasRole('responsable_ville');
    }

    /**
     * L'annuaire tel que ce lecteur a le droit de le voir.
     *
     * @return array<int, array{entreprise: Entreprise, villes: array<string, array<int, array<string, string>>>, total: int}>
     */
    public static function pour(User $lecteur, ?int $entrepriseId = null): array
    {
        $entreprises = self::entreprisesVisibles($lecteur, $entrepriseId);
        $villesAutorisees = self::villesAutorisees($lecteur);

        return $entreprises
            ->map(fn (Entreprise $entreprise) => self::pourUneEntreprise($entreprise, $villesAutorisees))
            ->filter(fn (array $bloc) => $bloc['total'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>|null  $villesAutorisees  null = aucune restriction de ville
     * @return array{entreprise: Entreprise, villes: array<string, array<int, array<string, string>>>, total: int}
     */
    private static function pourUneEntreprise(Entreprise $entreprise, ?array $villesAutorisees): array
    {
        $comptes = User::query()
            ->where('entreprise_id', $entreprise->id)
            // Un superviseur ne voit que sa ville. Le gérant, lui, n'est rattaché à
            // aucune : il figure en Direction, et le filtre par ville l'exclut donc
            // naturellement du document d'un superviseur.
            ->when($villesAutorisees !== null, fn ($q) => $q->whereIn('ville_id', $villesAutorisees))
            ->orderBy('name')
            ->get();

        if ($comptes->isEmpty()) {
            return ['entreprise' => $entreprise, 'villes' => [], 'total' => 0];
        }

        $roles = self::rolePrincipalParCompte($comptes->pluck('id'));
        $villes = Ville::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)->pluck('nom', 'id');
        $lieux = Site::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)->pluck('nom', 'id');

        $groupes = $comptes
            ->map(fn (User $compte) => [
                'cle_role' => $roles[$compte->id] ?? '',
                'role' => LibellesRoles::de($roles[$compte->id] ?? null),
                'nom' => $compte->name,
                'email' => $compte->email,
                'telephone' => $compte->telephone ?: '—',
                'ville' => $compte->ville_id ? ($villes[$compte->ville_id] ?? '—') : self::HORS_VILLE,
                // Un responsable de site répond d'un lieu ; les autres couvrent la ville
                // entière. La colonne dit lequel des deux, sans quoi deux personnes de la
                // même ville paraîtraient avoir le même périmètre.
                'perimetre' => $compte->site_id
                    ? ($lieux[$compte->site_id] ?? '—')
                    : ($compte->ville_id ? ($villes[$compte->ville_id] ?? '—').' (ville entière)' : 'Entreprise entière'),
                'statut' => $compte->est_actif ? 'Actif' : 'Inactif',
            ])
            // Le rang du rôle d'abord, le nom ensuite : une seule clef composée suffit,
            // le rang étant cadré sur deux chiffres pour que 10 ne passe pas avant 2.
            ->sortBy(fn (array $ligne) => sprintf('%02d-%s', self::rang($ligne['cle_role']), $ligne['nom']))
            ->groupBy('ville')
            ->map(fn (Collection $lignes) => $lignes->values()->all())
            ->all();

        // La direction d'abord, puis les villes par ordre alphabétique.
        uksort($groupes, fn ($a, $b) => match (true) {
            $a === self::HORS_VILLE => -1,
            $b === self::HORS_VILLE => 1,
            default => strcmp($a, $b),
        });

        return ['entreprise' => $entreprise, 'villes' => $groupes, 'total' => $comptes->count()];
    }

    private static function rang(string $role): int
    {
        $rang = array_search($role, self::ORDRE, true);

        return $rang === false ? count(self::ORDRE) : $rang;
    }

    /**
     * Le rôle de chacun, lu directement dans la table de liaison.
     *
     * getRoleNames() filtre sur l'équipe posée dans la requête en cours : hors contexte
     * d'une entreprise — ce qui est le cas du Super Admin — il ne renvoie rien.
     *
     * @return array<int, string>
     */
    private static function rolePrincipalParCompte(Collection $ids): array
    {
        return collect(User::nomsRolesParUtilisateur($ids))
            ->map(fn (string $noms) => trim(explode(',', $noms)[0]))
            ->all();
    }

    /** @return Collection<int, Entreprise> */
    private static function entreprisesVisibles(User $lecteur, ?int $entrepriseId): Collection
    {
        if ($lecteur->hasRole('super_admin')) {
            return Entreprise::query()
                ->when($entrepriseId, fn ($q) => $q->where('id', $entrepriseId))
                ->orderBy('nom')->get();
        }

        // Pour tous les autres, l'entreprise demandée est ignorée : c'est la leur, ou rien.
        return Entreprise::where('id', $lecteur->entreprise_id)->get();
    }

    /**
     * Villes auxquelles ce lecteur a droit, ou null s'il les voit toutes.
     *
     * @return array<int, int>|null
     */
    private static function villesAutorisees(User $lecteur): ?array
    {
        if ($lecteur->hasRole('super_admin') || $lecteur->hasRole('gerant')) {
            return null;
        }

        // Le rattachement est porté à deux endroits : la ville désigne son responsable,
        // et le compte porte sa ville. On prend les deux — l'un des deux peut manquer
        // après une reprise d'accès, et un annuaire vide ne dirait pas pourquoi.
        $villes = Ville::withoutGlobalScopes()
            ->where('entreprise_id', $lecteur->entreprise_id)
            ->where('responsable_id', $lecteur->id)
            ->pluck('id')->all();

        if ($lecteur->ville_id) {
            $villes[] = $lecteur->ville_id;
        }

        return array_values(array_unique($villes));
    }
}
