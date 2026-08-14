import { Chart, registerables } from 'chart.js';

import './notifications';

Chart.register(...registerables);
window.Chart = Chart;

/*
 * Menus de navigation repliables (Indicateur / Général) : <details> natif ne se ferme
 * qu'en recliquant sur son <summary>, jamais en cliquant ailleurs sur la page — d'où un
 * menu qui reste ouvert indéfiniment tant qu'on ne le referme pas explicitement. Un seul
 * écouteur global, posé une fois : il survit aux navigations wire:navigate (le script ne
 * se recharge pas, seul le corps de la page est remplacé).
 */
document.addEventListener('click', (event) => {
    document.querySelectorAll('.nav-groupe[open]').forEach((details) => {
        if (! details.contains(event.target)) {
            details.removeAttribute('open');
        }
    });
});
