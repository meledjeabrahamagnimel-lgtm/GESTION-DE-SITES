<?php

namespace Database\Seeders;

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Services\GenerateurNumero;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Étend la démonstration « L'Artisan Automobile » à deux villes supplémentaires
 * (Bouaké et San Pedro), sans toucher à Abidjan ni aux comptes déjà existants :
 * mêmes rôles, même structure à deux sites (Mécanique + Sinistre) par ville, et le
 * même historique opérationnel de 60 jours que DonneesOperationnellesSeeder génère
 * pour Abidjan — mais rejoué ici uniquement sur les sites de ces deux nouvelles
 * villes, jamais sur ceux d'Abidjan.
 */
class VillesBouakeSanPedroSeeder extends Seeder
{
    public function run(): void
    {
        $entreprise = Entreprise::where('slug', 'artisan-automobile')->first();

        if (! $entreprise) {
            $this->command?->warn("L'entreprise pilote n'existe pas — étape ignorée.");

            return;
        }

        if (Ville::where('entreprise_id', $entreprise->id)->where('code', 'BOU')->exists()) {
            $this->command?->warn('Bouaké et San Pedro existent déjà — étape ignorée.');

            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        $nouveauxSites = new Collection();
        $nouveauxSites = $nouveauxSites->merge($this->creerVille(
            $entreprise,
            code: 'BOU',
            nom: 'Bouaké',
            commune: 'Bouaké',
            slugCompte: 'bouake',
            couleur: '#0E9F6E',
            responsableNom: 'Aminata Koné',
            comptableNom: 'Adama Coulibaly',
            commercialNom: 'Ibrahim Traoré',
        ));

        $nouveauxSites = $nouveauxSites->merge($this->creerVille(
            $entreprise,
            code: 'SPD',
            nom: 'San Pedro',
            commune: 'San Pedro',
            slugCompte: 'sanpedro',
            couleur: '#D97706',
            responsableNom: 'Chantal Bamba',
            comptableNom: 'Serge Gnahoré',
            commercialNom: 'Paul Zadi',
        ));

        // Même générateur d'historique que pour Abidjan (60 jours, toutes activités),
        // mais borné à ces seuls sites : il ne rejoue rien sur les écritures d'Abidjan.
        (new DonneesOperationnellesSeeder())->genererPourSites($nouveauxSites, $entreprise->id);
    }

    /** @return Collection<int, Site> le lieu unique de la ville créée. */
    private function creerVille(
        Entreprise $entreprise,
        string $code,
        string $nom,
        string $commune,
        string $slugCompte,
        string $couleur,
        string $responsableNom,
        string $comptableNom,
        string $commercialNom,
    ): Collection {
        // Pas de gérant par ville : le rôle porte structurellement sur toute l'entreprise,
        // et un seul compte (gerant@gmail.com) suffit donc à en couvrir les trois.
        $responsable = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => $responsableNom,
            'email' => "superviseur{$slugCompte}@gmail.com",
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $responsable->assignRole('responsable_ville');

        $ville = Ville::create([
            'entreprise_id' => $entreprise->id,
            'code' => $code,
            'nom' => $nom,
            'commune' => $commune,
            'telephone' => '+225 27 23 45 '.random_int(10, 99).' '.random_int(10, 99),
            'adresse' => "Zone industrielle, $nom",
            'couleur' => $couleur,
            'responsable_id' => $responsable->id,
            'est_actif' => true,
        ]);

        // Hors Abidjan, la ville ne compte qu'un lieu : il se confond avec elle, et les
        // deux activités s'y pratiquent.
        $site = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'code' => $code,
            'nom' => $nom,
            'est_actif' => true,
        ]);

        $commercialUtilisateur = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => $commercialNom,
            'email' => "commercial{$slugCompte}@gmail.com",
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $commercialUtilisateur->assignRole('commercial');

        // Le rattachement est porté par le compte lui-même, pas seulement par la fiche :
        // c'est ce qui permet de le retrouver après une purge des données.
        $commercialUtilisateur->update(['ville_id' => $ville->id]);
        $responsable->update(['ville_id' => $ville->id]);

        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $commercialUtilisateur->id,
            'numero' => GenerateurNumero::suivant($entreprise->id, 'com'),
            'nom' => $commercialNom,
            'objectif_mecanique' => (int) round(Commercial::OBJECTIF_MENSUEL_DEFAUT * Commercial::PART_MECANIQUE_DEFAUT),
            'objectif_sinistre' => (int) round(Commercial::OBJECTIF_MENSUEL_DEFAUT * Commercial::PART_SINISTRE_DEFAUT),
            'statut' => 'Actif',
            'est_spontane' => false,
        ]);

        // Vente sans commercial nommé : un seul par ville (il couvre les deux activités,
        // comme tout commercial) — numéro propre à la ville pour ne pas entrer en
        // collision avec celui d'Abidjan (SP-ABJ).
        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'numero' => "SP-{$code}",
            'nom' => 'Client spontané',
            'objectif_mensuel' => 0,
            'statut' => 'Actif',
            'est_spontane' => true,
        ]);

        // La comptabilité couvre la ville entière, jamais un lieu : une seule caisse par ville.
        $comptable = User::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'name' => $comptableNom,
            'email' => "comptabilite{$slugCompte}@gmail.com",
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $comptable->assignRole('caissier');

        // Le superviseur de ville prospecte lui aussi : il figure donc parmi les commerciaux.
        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $responsable->id,
            'numero' => GenerateurNumero::suivant($entreprise->id, 'com'),
            'nom' => $responsableNom,
            'objectif_mecanique' => (int) round(Commercial::OBJECTIF_MENSUEL_DEFAUT * Commercial::PART_MECANIQUE_DEFAUT / 2),
            'objectif_sinistre' => (int) round(Commercial::OBJECTIF_MENSUEL_DEFAUT * Commercial::PART_SINISTRE_DEFAUT / 2),
            'statut' => 'Actif',
            'est_spontane' => false,
        ]);

        return new Collection([$site]);
    }
}
