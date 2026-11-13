<?php

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

if (! function_exists('broadcast_safely')) {
    /**
     * Emite el evento por broadcast sin romper la operación si Reverb
     * no está disponible: registra el aviso y continúa. El feed en vivo
     * se actualiza cuando Reverb vuelva a estar arriba.
     */
    function broadcast_safely(mixed $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (BroadcastException $e) {
            Log::warning('Broadcast no enviado (Reverb no disponible): '.$e->getMessage());
        }
    }
}
