// Chart.js, bundled — replaces the jsdelivr CDN <script> the analytics pages used
// to load. Exposed as a global because the pages' inline scripts call `new Chart()`.
import Chart from 'chart.js/auto';

window.Chart = Chart;
