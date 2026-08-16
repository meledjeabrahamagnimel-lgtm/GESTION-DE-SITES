<?php

namespace App\Console\Commands;

use Modules\Noyau\Commun\Services\WebPush\CleEc;
use Illuminate\Console\Command;

class GenererClesVapid extends Command
{
    protected $signature = 'push:cles';

    protected $description = 'Génère la paire de clés VAPID nécessaire aux notifications poussées.';

    public function handle(): int
    {
        if (filled(config('webpush.cle_publique')) && ! $this->confirm(
            'Des clés VAPID sont déjà configurées. Les remplacer désabonnera TOUS les appareils. Continuer ?',
            false,
        )) {
            $this->line('Génération annulée.');

            return self::SUCCESS;
        }

        $paire = CleEc::genererPaire();

        $this->newLine();
        $this->info('Clés VAPID générées. Recopiez ces trois lignes dans votre fichier .env :');
        $this->newLine();
        $this->line('VAPID_CLE_PUBLIQUE='.CleEc::base64UrlEncoder($paire['publique']));
        $this->line('VAPID_CLE_PRIVEE='.CleEc::base64UrlEncoder($paire['privee']));
        $this->line('VAPID_CONTACT=mailto:votre-adresse@exemple.com');
        $this->newLine();
        $this->comment('Conservez la clé privée comme un mot de passe : elle ne doit jamais être versionnée.');

        return self::SUCCESS;
    }
}
