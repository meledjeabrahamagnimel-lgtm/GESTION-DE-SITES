<?php

namespace Modules\Noyau\Commun\Modeles;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'endpoint', 'cle_p256dh', 'cle_auth', 'appareil', 'empreinte'])]
class AbonnementPush extends Model
{
    protected $table = 'abonnements_push';

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function empreinteDe(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
