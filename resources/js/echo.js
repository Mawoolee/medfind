import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Suppress Pusher's own warnings — we use it as a WebSocket transport
// for Reverb, not as a Pusher cloud connection.
Pusher.logToConsole = false;
Pusher.Runtime.createXHR = function() { return new XMLHttpRequest(); };

/**
 * Initialize Laravel Echo with Reverb (local WebSocket server).
 * Falls back to a silent no-op when no key is configured.
 */
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || '';

if (reverbKey) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
        wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
        wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    console.info('[MedFind] Laravel Echo initialized (Reverb)');
} else {
    window.Pusher = Pusher;   // still expose it so Echo works if key added later
    window.Echo = {
        channel: () => ({ listen: () => ({}) }),
        private: () => ({ listen: () => ({}), notification: () => ({}) }),
        leave: () => {},
    };
    console.debug('[MedFind] Echo not initialized (no REVERB key configured)');
}