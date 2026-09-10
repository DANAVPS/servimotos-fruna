<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verificación del webhook exigida por Meta (GET).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Recepción de eventos (POST). La lógica conversacional
     * se implementará en la Fase 4.
     */
    public function handle(Request $request): Response
    {
        Log::info('Webhook WhatsApp recibido', $request->all());

        return response('EVENT_RECEIVED', 200);
    }
}
