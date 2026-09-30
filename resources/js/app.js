import './bootstrap';
import './echo';  // ✨ Real-time WebSocket support
import './notification-sound';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
