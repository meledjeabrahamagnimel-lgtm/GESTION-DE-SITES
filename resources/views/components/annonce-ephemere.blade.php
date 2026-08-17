{{-- Un bandeau qui dit ce qui vient de se passer, puis s'efface.

     Il remplace les boîtes de dialogue « Confirmer ? » sur les gestes réversibles.
     Demander confirmation pour transmettre une prospection coûtait un clic à chaque
     ligne et n'évitait rien : le responsable arbitre ensuite, rien n'est perdu. La
     confirmation reste, elle, sur ce qui ne se rattrape pas — supprimer, clôturer
     un exercice, purger une entreprise.

     Posé une fois dans la mise en page ; n'importe quel écran l'appelle par
     $this->dispatch('annonce', texte: '...', ton: 'succes'|'alerte'). --}}
<div
    x-data="{
        messages: [],
        compteur: 0,
        montrer(texte, ton) {
            const id = ++this.compteur;
            this.messages.push({ id, texte, ton: ton || 'succes' });
            setTimeout(() => { this.messages = this.messages.filter(m => m.id !== id); }, 3000);
        },
    }"
    @annonce.window="montrer($event.detail.texte, $event.detail.ton)"
    style="position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:9999;
           display:flex; flex-direction:column; gap:8px; align-items:center;
           pointer-events:none; max-width:92vw;"
    aria-live="polite"
>
    <template x-for="m in messages" :key="m.id">
        <div
            x-transition:enter="annonce-entre"
            x-transition:leave="annonce-sort"
            :class="m.ton === 'alerte' ? 'annonce annonce-alerte' : 'annonce annonce-succes'"
            x-text="m.texte"
        ></div>
    </template>
</div>
