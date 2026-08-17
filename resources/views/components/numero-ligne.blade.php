@props(['ligne' => null, 'numero' => null])

{{-- Deux marques dans la même cellule : au-dessus le numéro du document, qui situe la
     ligne dans toute l'entreprise ; en dessous, plus petit, le code de celui qui l'a
     saisie et son rang à lui. Une seule colonne — les tableaux sont déjà larges, et le
     code se lit toujours en regard du numéro auquel il se rapporte. --}}
<div style="line-height:1.35;">
    <span style="font-weight:700;">{{ $numero ?? $ligne?->numero ?? '—' }}</span>

    @if ($ligne?->code_auteur)
        <div style="font-size:11px; color:var(--th-gris,#6B6E76); font-weight:600; letter-spacing:.2px;"
            title="Saisi par {{ $ligne->code_auteur }} — ville, rôle, initiales, rang de la personne">
            {{ $ligne->code_auteur }}
        </div>
    @endif
</div>
