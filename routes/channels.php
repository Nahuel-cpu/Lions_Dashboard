<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado de operaciones: cualquier usuario autenticado y verificado.
Broadcast::channel('operaciones', fn ($user) => (bool) $user->id);
