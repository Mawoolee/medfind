import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Initialize Laravel Echo with Reverb (local WebSocket server).
 *
 * Falls back to a silent no-op when no key is configured so pages
 * that don't need WebSockets won't crash.
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
    // No key — install a safe no-op stub so downstream code won't throw.
    window.Echo = {
        channel: () => ({ listen: () => ({}) }),
        private: () => ({ listen: () => ({}), notification: () => ({}) }),
        leave: () => {},
    };
    console.debug('[MedFind] Echo not initialized (no REVERB key)');
}
