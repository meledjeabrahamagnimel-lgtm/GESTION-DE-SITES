<?php

namespace App\Domain\Tenants\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'nom', 'slug', 'logo_chemin',
    'couleur_ink', 'couleur_paper', 'couleur_ligne', 'couleur_accent',
    'couleur_succes', 'couleur_alerte', 'couleur_info',
    'plan', 'est_active',
])]
class Entreprise extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Entreprise $entreprise) {
            $entreprise->slug ??= Str::slug($entreprise->nom).'-'.Str::random(4);
        });
    }

    protected function casts(): array
    {
        return ['est_active' => 'boolean'];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_chemin ? Storage::disk('public')->url($this->logo_chemin) : null;
    }

    /** Palette de marque, prête à être injectée en custom properties CSS. */
    public function theme(): array
    {
        return [
            '--th-ink' => $this->couleur_ink,
            '--th-paper' => $this->couleur_paper,
            '--th-ligne' => $this->couleur_ligne,
            '--th-accent' => $this->couleur_accent,
            '--th-succes' => $this->couleur_succes,
            '--th-alerte' => $this->couleur_alerte,
            '--th-info' => $this->couleur_info,
        ];
    }
}
