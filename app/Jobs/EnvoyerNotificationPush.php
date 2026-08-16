<?php

namespace App\Jobs;

use Modules\Noyau\Commun\Services\WebPush\EnvoyeurPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoi des notifications poussées hors du temps de réponse.
 *
 * Chaque appareil abonné suppose un appel HTTP vers le service de push du navigateur,
 * qui peut prendre plusieurs secondes. Laissé dans la requête, un message adressé à
 * dix personnes équipées de deux appareils bloquerait la page pendant vingt appels.
 */
class EnvoyerNotificationPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @param  array<int, int>  $utilisateurIds
     */
    public function __construct(
        public array $utilisateurIds,
        public string $titre,
        public ?string $corps,
        public ?string $lien,
    ) {}

    public function handle(): void
    {
        EnvoyeurPush::diffuser($this->utilisateurIds, $this->titre, $this->corps, $this->lien);
    }
}
