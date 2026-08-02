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
    'nom', 'slug', 'code_entreprise', 'logo_chemin',
    'couleur_ink', 'couleur_paper', 'couleur_ligne', 'couleur_accent',
    'couleur_succes', 'couleur_alerte', 'couleur_info',
    'plan', 'est_active',
    'gerant_nom', 'gerant_prenom', 'gerant_fonction', 'gerant_email',
    'adresse', 'telephone', 'email', 'rccm',
    'ncc', 'regime_imposition', 'centre_impots', 'compte_contribuable',
    'idu', 'commune', 'quartier', 'reference_cadastrale', 'proprietaire_local',
])]
class Entreprise extends Model
{
    use HasFactory;

    /** Régimes d'imposition ivoiriens proposés à l'inscription. */
    public const REGIMES = [
        'RSI — Régime Simplifié d\'Imposition' => 'RSI — Régime Simplifié d\'Imposition',
        'RNI — Régime Normal d\'Imposition' => 'RNI — Régime Normal d\'Imposition',
        'RME — Régime des Microentreprises' => 'RME — Régime des Microentreprises',
        'Entreprenant' => 'Entreprenant',
    ];

    protected static function booted(): void
    {
        static::creating(function (Entreprise $entreprise) {
            $entreprise->slug ??= Str::slug($entreprise->nom).'-'.Str::random(4);
        });
    }

    /**
     * Code de rattachement communiqué au personnel : lisible, sans caractères ambigus
     * (ni O/0 ni I/1), et vérifié unique en base.
     */
    public static function genererCode(?string $nom = null): string
    {
        $prefixe = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $nom) ?: 'ENT', 0, 3));
        $prefixe = str_pad($prefixe, 3, 'X');

        do {
            $code = $prefixe.'-'.Str::upper(Str::random(6));
            $code = strtr($code, ['O' => 'R', '0' => '4', 'I' => 'K', '1' => '7', 'L' => 'M']);
        } while (static::where('code_entreprise', $code)->exists());

        return $code;
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

    /**
     * URL publique du logo, ou null s'il n'est pas réellement servable.
     * Le lien symbolique public/storage n'est pas versionné : sans « php artisan storage:link »
     * le fichier existe mais n'est pas accessible. On préfère alors afficher le nom de
     * l'entreprise plutôt qu'une image cassée.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_chemin) {
            return null;
        }

        // Préfixe « public: » : fichier livré avec le dépôt, servi directement.
        if (str_starts_with($this->logo_chemin, 'public:')) {
            return asset(substr($this->logo_chemin, 7));
        }

        if (! Storage::disk('public')->exists($this->logo_chemin)) {
            return null;
        }

        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_chemin);
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
