<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['entreprise_id', 'type', 'dernier_numero'])]
class CompteurDocument extends Model
{
    use AppartientAUneEntreprise;

    protected $table = 'compteurs_documents';
}
