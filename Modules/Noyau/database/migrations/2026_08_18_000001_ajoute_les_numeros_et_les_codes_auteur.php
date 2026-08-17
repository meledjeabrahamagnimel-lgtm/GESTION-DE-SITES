<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Noyau\Commun\Services\CodeAuteur;

/**
 * Deux marques sur chaque ligne saisie : son numéro, et le code de son auteur.
 *
 * Les encaissements et les décaissements n'avaient pas de numéro du tout — on les
 * désignait par leur date et leur montant, ce qui ne suffit pas quand deux caissiers
 * saisissent le même jour. Ils en reçoivent un, et l'existant est renuméroté dans
 * l'ordre où il a été saisi pour que la série soit continue depuis l'origine.
 *
 * Le code auteur est reconstitué de la même façon pour les lignes déjà en base, à
 * partir de « cree_par ». Cette reprise porte une réserve honnête : elle lit la ville
 * et le rôle *actuels* de l'agent. Quelqu'un qui a changé de ville depuis verra ses
 * anciennes lignes porter sa ville d'aujourd'hui. Les lignes créées à partir de
 * maintenant, elles, figent le code au moment de la saisie.
 */
return new class extends Migration
{
    /** Table de saisie => type de compteur de document. */
    private const SERIES = [
        'prospections' => 'pro',
        'devis' => 'dev',
        'factures' => 'fac',
        'encaissements' => 'enc',
        'charges' => 'dec',
    ];

    /** Celles à qui il manquait un numéro. */
    private const A_NUMEROTER = ['encaissements', 'charges'];

    public function up(): void
    {
        // Chaque étape se vérifie avant d'agir : MySQL ne revient pas en arrière sur une
        // modification de structure, si bien qu'un échec à mi-parcours laisse la base
        // dans un état intermédiaire. La migration doit pouvoir être relancée telle
        // quelle, sans se plaindre de ce qu'elle a déjà fait.
        if (! Schema::hasTable('compteurs_auteur')) {
            Schema::create('compteurs_auteur', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 8);
                $table->unsignedInteger('dernier_numero')->default(0);
                $table->timestamps();

                // Un seul compteur par personne et par type : la contrainte est ce qui
                // empêche deux rangs identiques, pas seulement le verrou applicatif.
                $table->unique(['user_id', 'type']);
            });
        }

        foreach (self::A_NUMEROTER as $table) {
            if (! Schema::hasColumn($table, 'numero')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('numero', 20)->nullable()->after('site_id'));
            }
        }

        foreach (array_keys(self::SERIES) as $table) {
            if (! Schema::hasColumn($table, 'code_auteur')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('code_auteur', 32)->nullable()->index()->after('cree_par'));
            }
        }

        $this->ouvrirLesSeriesDeCaisse();
        $this->numeroterLExistant();
        $this->reconstituerLesCodesAuteur();

        // Les numéros ne valent que s'ils sont uniques : la contrainte vient après la
        // reprise, sinon elle refuserait les lignes encore vides.
        foreach (self::A_NUMEROTER as $table) {
            $nom = $table.'_entreprise_numero_unique';

            if (! $this->indexExiste($table, $nom)) {
                Schema::table($table, fn (Blueprint $t) => $t->unique(['entreprise_id', 'numero'], $nom));
            }
        }
    }

    /**
     * Ouvre les séries « enc » et « dec » dans la liste des types de compteur.
     *
     * La colonne est une énumération sous MySQL : un type non prévu y est refusé, et
     * l'erreur — « Data truncated for column type » — ne dit pas de quoi il s'agit.
     * SQLite, où tournent les tests, n'a pas d'énumération et n'a rien à ouvrir.
     */
    private function ouvrirLesSeriesDeCaisse(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $types = collect(self::SERIES)->values()->merge(['com', 'nfa'])->unique()
                ->map(fn (string $t) => "'".$t."'")->implode(', ');

            DB::statement("ALTER TABLE compteurs_documents MODIFY type ENUM($types) NOT NULL");

            return;
        }

        // SQLite n'a pas d'énumération, mais il en garde la trace sous forme de
        // contrainte CHECK — invisible, et tout aussi bloquante. Repasser la colonne
        // en texte reconstruit la table sans elle. On ne perd rien : la liste des
        // types valides est déjà tenue par le code, dans GenerateurNumero.
        Schema::table('compteurs_documents', fn (Blueprint $t) => $t->string('type', 8)->change());
    }

    private function indexExiste(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $i) => $i['name'] === $index);
    }

    public function down(): void
    {
        foreach (self::A_NUMEROTER as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_entreprise_numero_unique');
                $t->dropColumn('numero');
            });
        }

        foreach (array_keys(self::SERIES) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('code_auteur');
            });
        }

        Schema::dropIfExists('compteurs_auteur');
    }

    /** Attribue un numéro aux encaissements et décaissements déjà enregistrés. */
    private function numeroterLExistant(): void
    {
        $prefixes = ['enc' => 'ENC', 'dec' => 'DEC'];

        foreach (self::A_NUMEROTER as $table) {
            $type = self::SERIES[$table];

            foreach (DB::table($table)->distinct()->pluck('entreprise_id') as $entrepriseId) {
                if (! $entrepriseId) {
                    continue;
                }

                $rang = 0;

                // Dans l'ordre de saisie : la série doit se lire comme une chronologie.
                DB::table($table)->where('entreprise_id', $entrepriseId)->orderBy('id')
                    ->select('id')->get()
                    ->each(function ($ligne) use ($table, $prefixes, $type, &$rang) {
                        $rang++;
                        DB::table($table)->where('id', $ligne->id)->update([
                            'numero' => $prefixes[$type].'-'.str_pad((string) $rang, 4, '0', STR_PAD_LEFT),
                        ]);
                    });

                $this->recalerLeCompteur($entrepriseId, $type, $rang);
            }
        }
    }

    private function recalerLeCompteur(int $entrepriseId, string $type, int $dernier): void
    {
        $existant = DB::table('compteurs_documents')
            ->where('entreprise_id', $entrepriseId)->where('type', $type)->first();

        if ($existant) {
            DB::table('compteurs_documents')->where('id', $existant->id)
                ->update(['dernier_numero' => max((int) $existant->dernier_numero, $dernier)]);

            return;
        }

        DB::table('compteurs_documents')->insert([
            'entreprise_id' => $entrepriseId,
            'type' => $type,
            'dernier_numero' => $dernier,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Rejoue les codes auteur sur l'historique, à partir de « cree_par ». */
    private function reconstituerLesCodesAuteur(): void
    {
        $auteurs = User::withoutGlobalScopes()->with(['ville', 'site.ville', 'entreprise'])->get()->keyBy('id');
        $rangs = [];

        foreach (self::SERIES as $table => $type) {
            DB::table($table)->whereNotNull('cree_par')->orderBy('id')
                ->select('id', 'cree_par')->get()
                ->each(function ($ligne) use ($table, $type, $auteurs, &$rangs) {
                    $auteur = $auteurs->get($ligne->cree_par);

                    if (! $auteur) {
                        return;
                    }

                    $cle = $auteur->id.':'.$type;
                    $rangs[$cle] = ($rangs[$cle] ?? 0) + 1;

                    DB::table($table)->where('id', $ligne->id)
                        ->update(['code_auteur' => CodeAuteur::composer($auteur, $rangs[$cle])]);
                });
        }

        // Les compteurs repartent d'où l'historique s'arrête, sans quoi la prochaine
        // saisie reprendrait au rang 1 et se confondrait avec une ancienne ligne.
        foreach ($rangs as $cle => $dernier) {
            [$userId, $type] = explode(':', $cle);

            DB::table('compteurs_auteur')->insert([
                'user_id' => (int) $userId,
                'type' => $type,
                'dernier_numero' => $dernier,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
