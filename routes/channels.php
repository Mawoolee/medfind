<?php

use Illuminate\Support\Facades\Broadcast;

// Guard: skip channel registration when app is booting during package:discover
// or when the broadcasting driver is not properly configured (e.g., during CI/CD builds)
try {
    Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id;
    });
} catch (\Throwable $e) {
    // Silently skip - this happens during build/package:discover
}
