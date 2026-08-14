<?php

namespace Database\Seeders;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
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
            gerantNom: 'Adama Coulibaly',
            responsableNom: 'Aminata Koné',
            commercialNom: 'Ibrahim Traoré',
        ));

        $nouveauxSites = $nouveauxSites->merge($this->creerVille(
            $entreprise,
            code: 'SPD',
            nom: 'San Pedro',
            commune: 'San Pedro',
            slugCompte: 'sanpedro',
            couleur: '#D97706',
            gerantNom: 'Serge Gnahoré',
            responsableNom: 'Chantal Bamba',
            commercialNom: 'Paul Zadi',
        ));

        // Même générateur d'historique que pour Abidjan (60 jours, toutes activités),
        // mais borné à ces seuls sites : il ne rejoue rien sur les écritures d'Abidjan.
        (new DonneesOperationnellesSeeder())->genererPourSites($nouveauxSites, $entreprise->id);
    }

    /** @return Collection<int, Site> les deux sites (Mécanique + Sinistre) de la ville créée. */
    private function creerVille(
        Entreprise $entreprise,
        string $code,
        string $nom,
        string $commune,
        string $slugCompte,
        string $couleur,
        string $gerantNom,
        string $responsableNom,
        string $commercialNom,
    ): Collection {
        // Un compte gérant par ville, pour disposer d'un accès de test par site — étant
        // entendu que le rôle gérant porte structurellement sur toute l'entreprise : ce
        // compte verra donc, comme gerant@gmail.com, l'ensemble des villes (y compris
        // Abidjan), pas seulement celle-ci.
        $gerant = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => $gerantNom,
            'email' => "gerant{$slugCompte}@gmail.com",
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $gerant->assignRole('gerant');

        $responsable = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => $responsableNom,
            'email' => "responsable{$slugCompte}@gmail.com",
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $responsable->assignRole('responsable_site');

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

        $siteMecanique = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'nom' => "$nom — Mécanique",
            'activite' => 'Mécanique',
            'est_actif' => true,
        ]);

        $siteSinistre = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'nom' => "$nom — Sinistre",
            'activite' => 'Sinistre',
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
        // collision avec celui d'Abidjan (SP-MEC).
        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'numero' => "SP-{$code}",
            'nom' => 'Client spontané',
            'objectif_mensuel' => 0,
            'statut' => 'Actif',
            'est_spontane' => true,
        ]);

        return new Collection([$siteMecanique, $siteSinistre]);
    }
}
