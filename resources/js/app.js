import { Chart, registerables } from 'chart.js';

import './notifications';

Chart.register(...registerables);
window.Chart = Chart;
