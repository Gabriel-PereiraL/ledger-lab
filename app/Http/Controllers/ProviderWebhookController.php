<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProviderWebhook;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.provider.webhook_secret');
        $signature = $request->header('X-Provider-Signature', '');
        abort_unless(is_string($secret) && $secret !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature), 401, 'Invalid webhook signature.');
        $payload = $request->validate(['id' => 'required|string|max:128', 'type' => 'required|string|max:100', 'data' => 'required|array']);
        $existing = WebhookEvent::query()->where('external_event_id', $payload['id'])->first();
        if ($existing) {
            if (! $existing->processed_at) {
                ProcessProviderWebhook::dispatch($existing->id)->afterCommit();
            }

            return response()->json(['status' => 'duplicate']);
        }
        try {
            $event = WebhookEvent::query()->create(['external_event_id' => $payload['id'], 'type' => $payload['type'], 'payload' => $payload]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }
        ProcessProviderWebhook::dispatch($event->id)->afterCommit();

        return response()->json(['status' => 'accepted'], 202);
    }
}
