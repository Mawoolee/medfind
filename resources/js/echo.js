import Echo from 'laravel-echo';

// Initialize Echo only when keys are present. Use dynamic import for pusher-js so builds
// that don't need it won't include/execute Pusher code.
(async function initEcho(){
  try {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || '';
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || '';

    if (reverbKey || pusherKey) {
      const Pusher = (await import('pusher-js')).default;
      window.Pusher = Pusher;

      window.Echo = new Echo({
        broadcaster: pusherKey ? 'pusher' : 'reverb',
        key: reverbKey || pusherKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws','wss'],
      });

    } else {
      // No keys present — do not import pusher-js and expose a harmless null Echo.
      window.Echo = null;
      console.debug && console.debug('Echo not initialized: no reverb/pusher key present');
    }
  } catch (e) {
    console.debug && console.debug('Echo initialization skipped due to error', e);
    window.Echo = null;
  }
})();
