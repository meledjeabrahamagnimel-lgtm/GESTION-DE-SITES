<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux tables neuves, et rien d'autre.
 *
 * Cette migration ne touche à aucune donnée existante : elle n'ajoute ni ne modifie
 * aucune colonne des tables métier. Passée sur une base qui porte les écritures réelles
 * de l'entreprise, elle crée deux tables vides et s'arrête là.
 *
 * `sessions_utilisateur` : une ligne par connexion. Elle répond à « qui est entré, quand,
 * depuis où, et combien de temps est-il resté ». La table `sessions` de Laravel ne le
 * dirait pas : elle ne garde que la session en cours et s'efface à la déconnexion.
 *
 * `visites_ecran` : une ligne par page ouverte. Elle répond à « sur quel écran, et
 * combien de temps ». La durée est celle qui sépare deux pages : c'est une durée
 * d'affichage, pas une durée d'attention — nuance à garder en tête avant d'en tirer une
 * conclusion sur quelqu'un.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_utilisateur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Recopié plutôt que joint : les écrans de la plateforme filtrent par
            // entreprise, et une jointure de plus sur une table qui grossit vite se
            // paie à chaque affichage. La valeur ne bouge pas après la connexion.
            $table->unsignedBigInteger('entreprise_id')->nullable()->index();

            // Rôle au moment de la connexion. Un compte peut changer de rôle ; ce qui
            // s'est passé ce jour-là s'est passé sous l'ancien.
            $table->string('role', 60)->nullable();

            // L'identifiant de session Laravel, seul lien fiable entre une déconnexion
            // et la connexion qu'elle termine.
            $table->string('identifiant_session', 100)->nullable()->index();

            $table->string('adresse_ip', 45)->nullable();
            $table->string('navigateur', 255)->nullable();
            $table->string('plateforme', 60)->nullable();

            $table->timestamp('ouverte_le');
            $table->timestamp('derniere_activite_le');
            $table->timestamp('fermee_le')->nullable();

            // Tenue à jour à chaque requête plutôt que recalculée : une différence de
            // dates s'écrit différemment sous MySQL et sous SQLite, et une somme sur
            // une colonne entière reste lisible et portable.
            $table->unsignedInteger('duree_secondes')->default(0);

            // deconnexion | expiration : une session fermée par son titulaire ne se lit
            // pas comme une session abandonnée en cours de route.
            $table->string('motif_fin', 20)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'ouverte_le']);
            $table->index(['entreprise_id', 'ouverte_le']);
            // Les écrans cherchent d'abord « qui est là maintenant » : les sessions
            // ouvertes, les plus récemment actives en tête.
            $table->index(['fermee_le', 'derniere_activite_le']);
        });

        Schema::create('visites_ecran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_utilisateur_id')->constrained('sessions_utilisateur')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('entreprise_id')->nullable()->index();

            // Le nom de route est la clef stable ; l'URL garde les paramètres, utiles
            // pour distinguer deux fiches ouvertes sur le même écran.
            $table->string('route', 120)->nullable()->index();
            $table->string('url', 500);
            $table->string('ecran', 120);

            $table->timestamp('vue_le');
            $table->unsignedInteger('duree_secondes')->default(0);

            $table->index(['user_id', 'vue_le']);
            $table->index(['session_utilisateur_id', 'vue_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_ecran');
        Schema::dropIfExists('sessions_utilisateur');
    }
};
