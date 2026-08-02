<?php

use function Livewire\Volt\{computed};

$activites = computed(fn () => \Spatie\Activitylog\Models\Activity::with('causer')->latest()->limit(50)->get());

?>

<x-a-venir titre="Journal d'audit" description="Connexions, créations et révocations d'accès, changements sensibles.">
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Auteur</th>
                        <th>Évènement</th>
                        <th>Sujet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->activites as $activite)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td>{{ $activite->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $activite->causer?->name ?? 'Système' }}</td>
                            <td>{{ $activite->event ?? $activite->description }}</td>
                            <td>{{ class_basename($activite->subject_type ?? '') }} #{{ $activite->subject_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:14px 10px; color:#6B6E76;">Aucune activité enregistrée pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-a-venir>
