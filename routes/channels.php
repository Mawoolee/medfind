<?php

use Illuminate\Support\Facades\Broadcast;

// Guard: skip channel registration during build/package:discover when broadcasting isn't configured
if (app()->runningInConsole() && !config('broadcasting.default')) {
    return;
}

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
