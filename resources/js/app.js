import './bootstrap';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-routing-machine/src/index.js';
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css';
import Alpine from 'alpinejs';

window.L = L;
window.Alpine = Alpine;

Alpine.start();
