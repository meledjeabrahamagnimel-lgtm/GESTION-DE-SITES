<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un site n'est plus une activité mais un lieu : l'endroit physique où l'entreprise
 * opère, et où l'on pratique indifféremment les deux activités (Mécanique et
 * Sinistre). Une ville peut donc compter plusieurs sites — Abidjan en a deux —
 * tandis que Bouaké et San Pedro n'en ont qu'un, confondu avec la ville elle-même.
 *
 * L'activité reste portée par chaque opération (prospection, devis, facture), jamais
 * par le lieu : `sites.activite` disparaît, et les deux sites historiques de chaque
 * ville (« X — Mécanique » et « X — Sinistre ») sont fusionnés en un seul lieu qui
 * récupère tout leur historique, pour ne perdre aucune donnée déjà saisie.
 */
return new class extends Migration
{
    /** Tables dont la simple réaffectation de `site_id` suffit à déplacer l'historique. */
    private const TABLES_A_REPORTER = ['prospections', 'devis', 'factures', 'encaissements', 'charges', 'users'];

    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Code court et stable du lieu (ABJ-1, BOU…) : sert de clé métier lisible,
            // notamment pour désigner un site depuis une application tierce (Windev).
            $table->string('code', 20)->nullable()->after('ville_id');
        });

        foreach (DB::table('villes')->orderBy('id')->get() as $ville) {
            $sites = DB::table('sites')->where('ville_id', $ville->id)->orderBy('id')->get();

            if ($sites->isEmpty()) {
                continue;
            }

            $survivant = $sites->firstWhere('activite', 'Mécanique') ?? $sites->first();

            foreach ($sites->where('id', '!=', $survivant->id) as $absorbe) {
                $this->reporterHistorique($absorbe->id, $survivant->id);
                DB::table('sites')->where('id', $absorbe->id)->delete();
            }

            DB::table('sites')->where('id', $survivant->id)->update([
                'nom' => $ville->nom,
                'code' => $ville->code,
            ]);
        }

        Schema::table('sites', function (Blueprint $table) {
            // MySQL s'appuie sur l'index unique pour la clé étrangère ville_id : il faut
            // lui en offrir un autre avant de retirer celui-ci, sinon il refuse la suppression.
            $table->index('ville_id', 'sites_ville_id_index');
            $table->dropUnique(['ville_id', 'activite']);
            $table->dropColumn('activite');
        });

        $this->dedoublerAbidjan();
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->enum('activite', ['Mécanique', 'Sinistre'])->default('Mécanique')->after('nom');
        });

        // La fusion n'est pas réversible : on ne peut pas deviner de quel atelier venait
        // chaque ligne d'historique. On se contente de rendre au schéma sa forme d'origine,
        // en ne gardant qu'un site (Mécanique) par ville — la clôture n'étant jamais
        // censée s'exécuter sur une base de production.
        foreach (DB::table('villes')->orderBy('id')->get() as $ville) {
            $sites = DB::table('sites')->where('ville_id', $ville->id)->orderBy('id')->get();

            if ($sites->isEmpty()) {
                continue;
            }

            $survivant = $sites->first();

            foreach ($sites->where('id', '!=', $survivant->id) as $absorbe) {
                $this->reporterHistorique($absorbe->id, $survivant->id);
                DB::table('sites')->where('id', $absorbe->id)->delete();
            }

            DB::table('sites')->where('id', $survivant->id)->update([
                'nom' => $ville->nom.' — Mécanique',
                'activite' => 'Mécanique',
            ]);
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->unique(['ville_id', 'activite']);
            $table->dropIndex('sites_ville_id_index');
        });
    }

    /** Déplace tout ce qui pend au site absorbé vers le site survivant. */
    private function reporterHistorique(int $absorbeId, int $survivantId): void
    {
        foreach (self::TABLES_A_REPORTER as $table) {
            DB::table($table)->where('site_id', $absorbeId)->update(['site_id' => $survivantId]);
        }

        // Les saisies journalières sont uniques par (site, date) : deux lignes du même
        // jour ne peuvent pas cohabiter après fusion. On fusionne alors leurs champs —
        // le survivant garde ce qu'il a déjà renseigné, et complète avec l'autre.
        foreach (DB::table('saisies_journalieres')->where('site_id', $absorbeId)->get() as $saisie) {
            $existante = DB::table('saisies_journalieres')
                ->where('site_id', $survivantId)->where('date', $saisie->date)->first();

            if (! $existante) {
                DB::table('saisies_journalieres')->where('id', $saisie->id)->update(['site_id' => $survivantId]);

                continue;
            }

            DB::table('saisies_journalieres')->where('id', $existante->id)->update([
                'vehicules_sans_facture' => $existante->vehicules_sans_facture + $saisie->vehicules_sans_facture,
                'commentaire_prospects' => $this->fusionner($existante->commentaire_prospects, $saisie->commentaire_prospects),
                'commentaire_devis' => $this->fusionner($existante->commentaire_devis, $saisie->commentaire_devis),
                'commentaire_ca' => $this->fusionner($existante->commentaire_ca, $saisie->commentaire_ca),
                'commentaire_tresorerie' => $this->fusionner($existante->commentaire_tresorerie, $saisie->commentaire_tresorerie),
                'commentaire_charges' => $this->fusionner($existante->commentaire_charges, $saisie->commentaire_charges),
            ]);

            DB::table('saisies_journalieres')->where('id', $saisie->id)->delete();
        }
    }

    private function fusionner(?string $garde, ?string $ajout): ?string
    {
        return trim(implode("\n", array_filter([$garde, $ajout]))) ?: null;
    }

    /**
     * Abidjan est la seule ville à compter deux lieux distincts. Le site issu de la
     * fusion devient « Abidjan — Site 1 » et un second lieu vide est créé à côté de lui,
     * prêt à recevoir ses propres saisies. Les deux relèvent du responsable de la ville
     * tant qu'aucun responsable de site ne leur est nommé.
     */
    private function dedoublerAbidjan(): void
    {
        foreach (DB::table('villes')->where('code', 'ABJ')->get() as $ville) {
            $premier = DB::table('sites')->where('ville_id', $ville->id)->orderBy('id')->first();

            if (! $premier) {
                continue;
            }

            DB::table('sites')->where('id', $premier->id)->update([
                'nom' => $ville->nom.' — Site 1',
                'code' => $ville->code.'-1',
            ]);

            if (DB::table('sites')->where('ville_id', $ville->id)->count() > 1) {
                continue;
            }

            DB::table('sites')->insert([
                'entreprise_id' => $ville->entreprise_id,
                'ville_id' => $ville->id,
                'code' => $ville->code.'-2',
                'nom' => $ville->nom.' — Site 2',
                'responsable_id' => null,
                'est_actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
