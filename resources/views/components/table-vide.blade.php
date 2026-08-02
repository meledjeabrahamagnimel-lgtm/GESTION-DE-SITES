@props(['colspan' => 6, 'texte' => 'Aucune opération enregistrée sur cette période.'])

<tr>
    <td colspan="{{ $colspan }}" style="padding:0;">
        <div class="etat-vide" style="min-height:140px;"><span class="etat-vide-texte">{{ $texte }}</span></div>
    </td>
</tr>
