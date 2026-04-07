<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $expectedToken = (string) config('services.whatsapp.webhook_verify_token');

        if ($mode !== 'subscribe' || $expectedToken === '' || ! hash_equals($expectedToken, $token)) {
            abort(403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $updated = $whatsAppService->handleWebhook($request->all());

        return response()->json([
            'ok' => true,
            'updated_messages' => $updated,
        ]);
    }
}
