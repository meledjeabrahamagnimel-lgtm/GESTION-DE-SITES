/**
 * Agent de service : reçoit les notifications poussées même quand aucun onglet
 * de l'application n'est ouvert, et ouvre la bonne page au clic.
 *
 * Ce fichier est servi tel quel depuis /sw.js : il ne passe pas par Vite, car un
 * agent de service doit être disponible à la racine du site pour couvrir toutes
 * les pages.
 */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (evenement) => evenement.waitUntil(self.clients.claim()));

self.addEventListener('push', (evenement) => {
    let donnees = { titre: 'Nouvelle notification', corps: null, lien: '/' };

    try {
        if (evenement.data) donnees = { ...donnees, ...evenement.data.json() };
    } catch (e) {
        if (evenement.data) donnees.corps = evenement.data.text();
    }

    evenement.waitUntil(
        self.registration.showNotification(donnees.titre, {
            body: donnees.corps || '',
            icon: '/logos/icone-192.png',
            badge: '/logos/icone-192.png',
            vibrate: [130, 60, 130],
            tag: donnees.lien || 'gestion-sites',
            renotify: true,
            data: { lien: donnees.lien || '/' },
        })
    );
});

self.addEventListener('notificationclick', (evenement) => {
    evenement.notification.close();

    const lien = evenement.notification.data?.lien || '/';

    evenement.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((fenetres) => {
            // Si l'application est déjà ouverte quelque part, on y navigue plutôt
            // que d'empiler un onglet supplémentaire.
            for (const fenetre of fenetres) {
                if ('focus' in fenetre) {
                    fenetre.navigate?.(lien);

                    return fenetre.focus();
                }
            }

            return self.clients.openWindow(lien);
        })
    );
});
