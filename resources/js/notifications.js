/**
 * Cloche de notifications : badge, bandeau flottant et son court.
 *
 * Le son est synthétisé par l'API Web Audio plutôt que chargé depuis un fichier :
 * aucun binaire à versionner et rien à télécharger au premier signal.
 */

let contexteAudio = null;

function jouerSignal() {
    try {
        const Contexte = window.AudioContext || window.webkitAudioContext;
        if (!Contexte) return;

        contexteAudio = contexteAudio || new Contexte();

        // Les navigateurs suspendent le contexte tant que l'utilisateur n'a pas
        // interagi avec la page : on le réveille avant chaque signal.
        if (contexteAudio.state === 'suspended') contexteAudio.resume();

        const debut = contexteAudio.currentTime;

        // Deux notes brèves, façon SMS.
        [[880, 0], [1174, 0.13]].forEach(([frequence, decalage]) => {
            const oscillateur = contexteAudio.createOscillator();
            const gain = contexteAudio.createGain();

            oscillateur.type = 'sine';
            oscillateur.frequency.value = frequence;

            gain.gain.setValueAtTime(0.0001, debut + decalage);
            gain.gain.exponentialRampToValueAtTime(0.18, debut + decalage + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, debut + decalage + 0.16);

            oscillateur.connect(gain).connect(contexteAudio.destination);
            oscillateur.start(debut + decalage);
            oscillateur.stop(debut + decalage + 0.18);
        });
    } catch (e) {
        // Un son est un confort : son échec ne doit jamais casser la page.
    }
}

function afficherBandeau(texte) {
    let pile = document.getElementById('pile-notifications');

    if (!pile) {
        pile = document.createElement('div');
        pile.id = 'pile-notifications';
        document.body.appendChild(pile);
    }

    const bandeau = document.createElement('div');
    bandeau.className = 'notification-flottante';
    bandeau.textContent = texte;
    pile.appendChild(bandeau);

    setTimeout(() => bandeau.classList.add('sort'), 5000);
    setTimeout(() => bandeau.remove(), 5600);
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('cloche', (nonLuesInitiales) => ({
        ouvert: false,
        precedent: nonLuesInitiales,

        init() {
            // $el est le composant Livewire : on observe la valeur rendue du badge.
            this.$watch('ouvert', (valeur) => {
                if (valeur) this.precedent = this.compteurActuel();
            });

            Livewire.hook('morph.updated', ({ el }) => {
                if (!this.$el.contains(el) && el !== this.$el) return;

                const actuel = this.compteurActuel();

                if (actuel > this.precedent) {
                    jouerSignal();
                    afficherBandeau(this.dernierTitre() || 'Nouvelle notification');
                }

                this.precedent = actuel;
            });
        },

        compteurActuel() {
            const badge = this.$el.querySelector('.cloche-badge');

            return badge ? parseInt(badge.textContent.replace('+', ''), 10) || 0 : 0;
        },

        dernierTitre() {
            return this.$el.querySelector('.cloche-item.est-non-lu .cloche-titre')?.textContent?.trim();
        },
    }));
});
