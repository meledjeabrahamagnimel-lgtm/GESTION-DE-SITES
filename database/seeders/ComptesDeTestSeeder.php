<?php

namespace Database\Seeders;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\CompteurDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aligne les comptes du serveur sur ceux du poste de développement.
 *
 * Les autres seeders s'arrêtent dès que l'entreprise existe : ils installent, ils ne
 * rattrapent pas. Celui-ci fait l'inverse — il repasse sur chaque compte, le crée s'il
 * manque, corrige son rôle et son rattachement s'ils ont changé, et remet le mot de
 * passe. Il ne touche à aucune ecriture : prospections, devis, factures, encaissements
 * et charges restent intacts.
 *
 *     php artisan db:seed --class=ComptesDeTestSeeder --force
 *
 * Rejouable autant de fois que voulu, sans jamais creer de doublon.
 */
class ComptesDeTestSeeder extends Seeder
{
    private const MOT_DE_PASSE = 'password';

    /**
     * Anciennes adresses a renommer plutot qu'a recreer : renommer conserve
     * l'historique du compte (ses saisies, ses messages), recreer le perdrait.
     */
    private const RENOMMAGES = [
        'comptabiliteabidjansite2@gmail.com' => 'comptabiliteabidjan@gmail.com',
        'comptabiliteboauke@gmail.com' => 'comptabilitebouake@gmail.com',
    ];

    public function run(): void
    {
        $entreprise = Entreprise::withoutGlobalScopes()->where('slug', 'artisan-automobile')->first();

        if (! $entreprise) {
            $this->command?->error("L'entreprise « L'Artisan Automobile » n'existe pas.");
            $this->command?->warn('Lancez d’abord : php artisan db:seed --force');

            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        // Le rôle plateforme n'appartient à aucune entreprise : il vit dans l'équipe
        // conventionnelle 0. On s'assure qu'il existe avant d'y rattacher quiconque.
        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $this->renommerLesAnciennesAdresses();

        // --- Plateforme : hors de toute entreprise.
        $this->compte(null, 'superadmin@gmail.com', 'Super Admin', 'super_admin');
        $this->compte(null, 'support@gmail.com', 'Support Plateforme', 'super_admin', doitChanger: true);

        // --- Direction.
        $this->compte($entreprise, 'gerant@gmail.com', 'Jean-Baptiste Kouassi', 'gerant');

        $abidjan = $this->ville($entreprise, 'ABJ');
        $bouake = $this->ville($entreprise, 'BOU');
        $sanPedro = $this->ville($entreprise, 'SPD');

        // --- Abidjan : deux lieux, donc deux responsables de site en plus du superviseur.
        if ($abidjan) {
            $siteUn = $this->site($entreprise, $abidjan, 'ABJ-1');
            $siteDeux = $this->site($entreprise, $abidjan, 'ABJ-2');

            $superviseur = $this->compte($entreprise, 'superviseurabidjan@gmail.com', 'Marie-Claire Aya', 'responsable_ville', ville: $abidjan);
            $abidjan->update(['responsable_id' => $superviseur->id]);
            $this->ficheCommerciale($entreprise, $abidjan, $superviseur, 9_000_000, 4_000_000);

            if ($siteUn) {
                $resp = $this->compte($entreprise, 'responsableabidjansite1@gmail.com', 'Sylvain Kouassi', 'responsable_site', ville: $abidjan, site: $siteUn);
                $siteUn->update(['responsable_id' => $resp->id]);
                $this->ficheCommerciale($entreprise, $abidjan, $resp, 7_000_000, 3_000_000);
            }

            if ($siteDeux) {
                $resp = $this->compte($entreprise, 'responsableabidjansite2@gmail.com', 'Estelle Kacou', 'responsable_site', ville: $abidjan, site: $siteDeux);
                $siteDeux->update(['responsable_id' => $resp->id]);
                $this->ficheCommerciale($entreprise, $abidjan, $resp, 7_000_000, 3_000_000);
            }

            $commercial = $this->compte($entreprise, 'commercialabidjan@gmail.com', 'Koffi Yao', 'commercial', ville: $abidjan);
            $this->ficheCommerciale($entreprise, $abidjan, $commercial, 12_000_000, 5_000_000);

            $this->compte($entreprise, 'comptabiliteabidjan@gmail.com', 'Fatou Diabaté', 'caissier', ville: $abidjan);
            $this->clientSpontane($entreprise, $abidjan);
        }

        // --- Bouaké et San Pedro : un seul lieu, confondu avec la ville.
        foreach ([
            [$bouake, 'bouake', 'Aminata Koné', 'Ibrahim Traoré', 'Adama Coulibaly'],
            [$sanPedro, 'sanpedro', 'Chantal Bamba', 'Paul Zadi', 'Serge Gnahoré'],
        ] as [$ville, $slug, $nomSuperviseur, $nomCommercial, $nomComptable]) {
            if (! $ville) {
                continue;
            }

            $superviseur = $this->compte($entreprise, "superviseur$slug@gmail.com", $nomSuperviseur, 'responsable_ville', ville: $ville);
            $ville->update(['responsable_id' => $superviseur->id]);
            $this->ficheCommerciale($entreprise, $ville, $superviseur, 8_000_000, 3_500_000);

            $commercial = $this->compte($entreprise, "commercial$slug@gmail.com", $nomCommercial, 'commercial', ville: $ville);
            $this->ficheCommerciale($entreprise, $ville, $commercial, 10_000_000, 4_000_000);

            $this->compte($entreprise, "comptabilite$slug@gmail.com", $nomComptable, 'caissier', ville: $ville);
            $this->clientSpontane($entreprise, $ville);
        }

        $this->recalerCompteurCommerciaux($entreprise);

        $this->command?->info('Comptes de test alignés. Mot de passe : '.self::MOT_DE_PASSE);
    }

    private function renommerLesAnciennesAdresses(): void
    {
        foreach (self::RENOMMAGES as $ancienne => $nouvelle) {
            $compte = User::withoutGlobalScopes()->where('email', $ancienne)->first();

            if ($compte && ! User::withoutGlobalScopes()->where('email', $nouvelle)->exists()) {
                $compte->forceFill(['email' => $nouvelle])->save();
                $this->command?->line("  $ancienne renommé en $nouvelle");
            }
        }
    }

    /** Crée le compte s'il manque, le remet d'aplomb s'il existe. */
    private function compte(
        ?Entreprise $entreprise,
        string $email,
        string $nom,
        string $role,
        ?Ville $ville = null,
        ?Site $site = null,
        bool $doitChanger = false,
    ): User {
        $utilisateur = User::withoutGlobalScopes()->where('email', $email)->first() ?? new User(['email' => $email]);

        $utilisateur->forceFill([
            'entreprise_id' => $entreprise?->id,
            'name' => $nom,
            'email' => $email,
            'password' => Hash::make(self::MOT_DE_PASSE),
            'email_verified_at' => $utilisateur->email_verified_at ?? now(),
            'est_actif' => true,
            'doit_changer_mot_de_passe' => $doitChanger,
            'ville_id' => $ville?->id,
            'site_id' => $site?->id,
        ])->save();

        // syncRoles plutôt qu'assignRole : un compte dont le rôle a changé en cours de
        // route doit retrouver le bon, pas en cumuler deux.
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise?->id ?? 0);
        $utilisateur->syncRoles([$role]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise?->id);

        return $utilisateur;
    }

    private function ville(Entreprise $entreprise, string $code): ?Ville
    {
        return Ville::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)->where('code', $code)->first();
    }

    private function site(Entreprise $entreprise, Ville $ville, string $code): ?Site
    {
        return Site::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)
            ->where('ville_id', $ville->id)->where('code', $code)->first();
    }

    /**
     * Un responsable et un superviseur prospectent aussi : sans fiche commerciale,
     * ni leurs prospections ni leur chiffre d'affaires ne seraient rattachables.
     */
    private function ficheCommerciale(Entreprise $entreprise, Ville $ville, User $utilisateur, int $mecanique, int $sinistre): void
    {
        $fiche = Commercial::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)
            ->where('user_id', $utilisateur->id)->first();

        if ($fiche) {
            $fiche->forceFill(['nom' => $utilisateur->name, 'ville_id' => $ville->id, 'statut' => 'Actif'])->save();

            return;
        }

        Commercial::withoutGlobalScopes()->create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $utilisateur->id,
            'numero' => $this->numeroCommercialLibre($entreprise),
            'nom' => $utilisateur->name,
            'objectif_mecanique' => $mecanique,
            'objectif_sinistre' => $sinistre,
            'statut' => 'Actif',
            'est_spontane' => false,
        ]);
    }

    /** Une vente sans commercial nommé reste une vente : chaque ville a son réceptacle. */
    private function clientSpontane(Entreprise $entreprise, Ville $ville): void
    {
        Commercial::withoutGlobalScopes()->firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'ville_id' => $ville->id, 'est_spontane' => true],
            [
                'numero' => 'SP-'.$ville->code,
                'nom' => 'Client spontané',
                'objectif_mensuel' => 0,
                'statut' => 'Actif',
            ],
        );
    }

    private function numeroCommercialLibre(Entreprise $entreprise): string
    {
        $dernier = Commercial::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)
            ->where('est_spontane', false)->where('numero', 'like', 'C-%')
            ->orderByDesc('numero')->value('numero');

        return sprintf('C-%04d', ((int) substr((string) $dernier, 2)) + 1);
    }

    /** Le compteur doit dépasser le plus grand numéro posé, sinon la prochaine création collisionne. */
    private function recalerCompteurCommerciaux(Entreprise $entreprise): void
    {
        $plusGrand = (int) substr((string) Commercial::withoutGlobalScopes()
            ->where('entreprise_id', $entreprise->id)->where('numero', 'like', 'C-%')
            ->orderByDesc('numero')->value('numero'), 2);

        $compteur = CompteurDocument::withoutGlobalScopes()
            ->firstOrNew(['entreprise_id' => $entreprise->id, 'type' => 'com']);

        if ((int) $compteur->dernier_numero < $plusGrand) {
            $compteur->forceFill(['dernier_numero' => $plusGrand])->save();
        }
    }
}
