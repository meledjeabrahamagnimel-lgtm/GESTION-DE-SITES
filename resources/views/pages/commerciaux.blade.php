<?php

use function Livewire\Volt\{computed};

$commerciaux = computed(fn () => \App\Domain\Operations\Models\Commercial::actifs()->with('site')->get());

?>

<x-a-venir titre="Commerciaux"
        description="Statistique globale (objectif vs réalisation, classement) et performance individuelle par commercial.">
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:13px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                        <th style="padding:8px 10px;">N°</th>
                        <th style="padding:8px 10px;">Commercial</th>
                        <th style="padding:8px 10px;">Site</th>
                        <th style="padding:8px 10px;">Activité</th>
                        <th style="padding:8px 10px;">Objectif mensuel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->commerciaux as $commercial)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:8px 10px; font-weight:600;">{{ $commercial->numero }}</td>
                            <td style="padding:8px 10px;">{{ $commercial->nom }}</td>
                            <td style="padding:8px 10px;">{{ $commercial->site->nom }}</td>
                            <td style="padding:8px 10px;">{{ $commercial->activite ?? '—' }}</td>
                            <td style="padding:8px 10px; font-variant-numeric:tabular-nums;">{{ number_format($commercial->objectif_mensuel, 0, ',', ' ') }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-a-venir>
