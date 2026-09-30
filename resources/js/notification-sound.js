const notificationBell = document.querySelector('[data-notification-sound]');

if (notificationBell) {
    const badge = notificationBell.querySelector('[data-notification-badge]');
    let knownTotal = Number(notificationBell.dataset.notificationTotal || 0);
    let audioContext = null;
    let audioUnlocked = false;
    let pendingSound = false;
    let requestInProgress = false;

    function playNotificationSound() {
        if (!audioContext || audioContext.state !== 'running') {
            pendingSound = true;
            return;
        }

        pendingSound = false;
        const start = audioContext.currentTime;
        const oscillator = audioContext.createOscillator();
        const envelope = audioContext.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(1046.5, start);
        envelope.gain.setValueAtTime(0.001, start);
        envelope.gain.exponentialRampToValueAtTime(0.11, start + 0.006);
        envelope.gain.exponentialRampToValueAtTime(0.001, start + 0.24);

        oscillator.connect(envelope);
        envelope.connect(audioContext.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.25);
    }

    function unlockAudio() {
        if (audioUnlocked) return;
        audioUnlocked = true;

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            console.warn('[MedFind] Notification sound is unavailable in this browser.');
            return;
        }

        audioContext = new AudioContextClass();
        if (audioContext.state === 'suspended') {
            audioContext.resume().then(() => {
                if (pendingSound) playNotificationSound();
            }).catch(error => {
                console.warn('[MedFind] Could not enable notification sound:', error);
            });
        } else if (pendingSound) {
            playNotificationSound();
        }
    }

    document.addEventListener('pointerdown', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    function updateBadge(count) {
        if (!badge) return;
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.classList.toggle('hidden', count === 0);
        badge.classList.toggle('flex', count > 0);
    }

    async function checkNotifications() {
        if (requestInProgress || document.visibilityState === 'hidden') return;
        requestInProgress = true;

        try {
            const response = await fetch(notificationBell.dataset.countUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Notification count request failed (${response.status}).`);
            }

            const result = await response.json();
            const total = Number(result.notification_total);
            const unread = Number(result.count);

            if (Number.isFinite(total) && total > knownTotal) {
                knownTotal = total;
                playNotificationSound();
            } else if (Number.isFinite(total)) {
                knownTotal = total;
            }

            if (Number.isFinite(unread)) updateBadge(unread);
        } catch (error) {
            console.error('[MedFind] Could not check for new notifications:', error);
        } finally {
            requestInProgress = false;
        }
    }

    window.setInterval(checkNotifications, 10000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkNotifications();
    });
}
