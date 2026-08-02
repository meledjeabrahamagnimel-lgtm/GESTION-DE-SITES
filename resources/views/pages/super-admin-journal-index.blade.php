<?php

use function Livewire\Volt\{computed};

$activites = computed(fn () => \Spatie\Activitylog\Models\Activity::with('causer')->latest()->limit(50)->get());

?>

<x-a-venir titre="Journal d'audit" description="Connexions, créations et révocations d'accès, changements sensibles.">
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:13px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                        <th style="padding:8px 10px;">Date</th>
                        <th style="padding:8px 10px;">Auteur</th>
                        <th style="padding:8px 10px;">Évènement</th>
                        <th style="padding:8px 10px;">Sujet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->activites as $activite)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:8px 10px; white-space:nowrap;">{{ $activite->created_at->format('d/m/Y H:i') }}</td>
                            <td style="padding:8px 10px;">{{ $activite->causer?->name ?? 'Système' }}</td>
                            <td style="padding:8px 10px;">{{ $activite->event ?? $activite->description }}</td>
                            <td style="padding:8px 10px;">{{ class_basename($activite->subject_type ?? '') }} #{{ $activite->subject_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:14px 10px; color:#6B6E76;">Aucune activité enregistrée pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-a-venir>
