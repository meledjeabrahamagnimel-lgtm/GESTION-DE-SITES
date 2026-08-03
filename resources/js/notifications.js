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

/* ------------------------------------------------ Notifications poussées */

function versOctets(base64url) {
    const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const binaire = atob(base64);

    return Uint8Array.from(binaire, (c) => c.charCodeAt(0));
}

function versBase64Url(tampon) {
    return btoa(String.fromCharCode(...new Uint8Array(tampon)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

/** Le navigateur sait-il recevoir des notifications poussées ? */
export function pushDisponible() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/**
 * Abonne cet appareil. Renvoie les trois valeurs à enregistrer côté serveur,
 * ou null si l'utilisateur refuse ou si le navigateur ne sait pas faire.
 */
async function abonnerAppareil(clePublique) {
    if (!pushDisponible() || !clePublique) return null;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return null;

    const enregistrement = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;

    // Un abonnement existant est réutilisé : re-souscrire créerait un doublon.
    // La souscription contacte le service de push du navigateur : sans réseau, la
    // promesse peut ne jamais se résoudre. Le délai de garde évite un bouton figé.
    const abonnement =
        (await enregistrement.pushManager.getSubscription()) ||
        (await Promise.race([
            enregistrement.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: versOctets(clePublique),
            }),
            new Promise((_, rejeter) =>
                setTimeout(() => rejeter(new Error('Le service de notification du navigateur est injoignable.')), 15000)
            ),
        ]));

    return {
        endpoint: abonnement.endpoint,
        p256dh: versBase64Url(abonnement.getKey('p256dh')),
        auth: versBase64Url(abonnement.getKey('auth')),
    };
}

/*
 * Ces fonctions sont exposées sur window plutôt qu'enregistrées via Alpine.data().
 *
 * Alpine est démarré par Livewire, dont le script est injecté en fin de page, alors
 * que ce module est chargé depuis l'en-tête en « type=module » donc différé. Selon le
 * navigateur et la mise en cache, Alpine peut démarrer AVANT ce module : l'évènement
 * « alpine:init » est alors déjà passé, le composant n'est jamais enregistré, et la
 * page échoue sur « ouvert is not defined ». Les vues utilisent donc un x-data en
 * ligne, et n'appellent ces fonctions qu'au clic, longtemps après le chargement.
 */

window.pushDisponible = pushDisponible;

/**
 * Abonne l'appareil puis transmet les clés au composant Livewire.
 * Renvoie toujours un objet décrivant l'issue, jamais une exception.
 */
window.activerPush = async function (clePublique, wire) {
    try {
        const abonnement = await abonnerAppareil(clePublique);

        if (!abonnement) {
            return { etat: typeof Notification !== 'undefined' ? Notification.permission : 'default' };
        }

        await wire.call('enregistrerAbonnement', abonnement.endpoint, abonnement.p256dh, abonnement.auth, navigator.userAgent);

        return { etat: 'granted' };
    } catch (e) {
        console.warn('Abonnement aux notifications impossible :', e);

        return { echec: e.message || 'Activation impossible sur cet appareil.' };
    }
};

/*
 * Le serveur signale lui-même l'arrivée d'une notification : plus besoin d'observer
 * le badge dans le DOM, ce qui supposait un composant Alpine correctement enregistré.
 */
window.addEventListener('nouvelle-notification', (evenement) => {
    const detail = evenement.detail;
    const titre = detail?.titre || detail?.[0]?.titre || 'Nouvelle notification';

    jouerSignal();
    afficherBandeau(titre);
});
