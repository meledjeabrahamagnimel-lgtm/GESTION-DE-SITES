<?php

use function Livewire\Volt\{computed};

$commercial = computed(fn () => \App\Domain\Operations\Models\Commercial::where('user_id', auth()->id())->with('site')->first());

?>

@if ($this->commercial)
        <x-a-venir titre="{{ $this->commercial->nom }} — {{ $this->commercial->site->nom }}"
            description="Objectif, réalisation, écart, taux d'atteinte, contribution au CA et détail de vos factures.">
            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div>
                    <div style="font-size:11px; text-transform:uppercase; color:#6B6E76;">Activité</div>
                    <div style="font-size:15px; font-weight:700;">{{ $this->commercial->activite ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:11px; text-transform:uppercase; color:#6B6E76;">Objectif mensuel</div>
                    <div style="font-size:15px; font-weight:700;">{{ number_format($this->commercial->objectif_mensuel, 0, ',', ' ') }} F</div>
                </div>
            </div>
        </x-a-venir>
    @else
    <x-a-venir titre="Aucune fiche commerciale associée" description="Contactez votre responsable de site." />
@endif
