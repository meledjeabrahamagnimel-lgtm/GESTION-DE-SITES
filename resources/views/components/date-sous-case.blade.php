@props(['date' => null])

{{-- La date qui accompagne une case cochée. Une coche seule laisse croire que la date
     n'a pas été retenue ; on l'affiche donc systématiquement sous la case, qu'elle soit
     cliquable ou figée. Rien ne s'affiche quand la case n'est pas cochée. --}}
@if ($date)
    <div style="font-size:11px; color:var(--th-gris,#6B6E76); margin-top:2px; white-space:nowrap;">
        {{ $date->format('d/m/Y') }}
    </div>
@endif
