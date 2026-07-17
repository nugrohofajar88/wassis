<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeContactHistory;
use App\Jobs\ProcessAutoReply;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle an incoming WhatsApp message pushed by Fonnte's webhook.
     *
     * Fonnte does not sign its webhook payloads, so the configured URL itself
     * carries a shared secret path segment as the verification mechanism.
     */
    public function fonnte(Request $request, string $secret): JsonResponse
    {
        $expected = config('whatsapp.fonnte_webhook_secret');

        if (! $expected || ! hash_equals($expected, $secret)) {
            abort(403);
        }

        $sender  = $request->input('sender');
        $content = $request->input('message');

        if (! $sender || ! $content) {
            // Non-text events (status pings, polls, attachments, etc.) — acknowledge and ignore.
            return response()->json(['status' => true]);
        }

        $ownerEmail = config('whatsapp.owner_email');
        $user = $ownerEmail ? User::where('email', $ownerEmail)->first() : null;

        if (! $user) {
            Log::error('Fonnte webhook: no owner user configured/found for WHATSAPP_OWNER_EMAIL', ['email' => $ownerEmail]);
            return response()->json(['status' => false, 'error' => 'No owner user configured.'], 500);
        }

        // Fonnte's `inboxid` is not reliably a unique per-message id in practice — observed
        // as literally "0" on real deliveries. Treat 0/empty as "no id provided" so we don't
        // mistake distinct messages for duplicates of each other.
        $inboxId = $request->input('inboxid');
        if (! $inboxId || (int) $inboxId === 0) {
            $inboxId = null;
        }

        if ($inboxId && Message::where('gateway_message_id', $inboxId)->exists()) {
            // Duplicate webhook delivery (Fonnte retry) — already recorded.
            return response()->json(['status' => true]);
        }

        // Opt-in model: new contacts start with auto-reply OFF. The owner reviews and enables
        // it per contact via PUT /api/contacts/{id} — see AGENTS.md.
        $contact = Contact::firstOrCreate(
            ['user_id' => $user->id, 'phone' => $sender],
            ['name' => $request->input('name') ?: $sender, 'ai_enabled' => false]
        );

        $message = $contact->messages()->create([
            'user_id'            => $user->id,
            'direction'          => 'in',
            'content'            => $content,
            'gateway_message_id' => $inboxId,
            'sent_at'            => now(),
        ]);

        if ($contact->ai_enabled && $user->getSetting('auto_reply_enabled', false)) {
            // Debounced: if more messages arrive from this contact before the delay elapses,
            // this job bails and the job dispatched for the newest message takes over — see
            // ProcessAutoReply and AGENTS.md.
            ProcessAutoReply::dispatch($contact->id, $message->id, $message->created_at->toDateTimeString())
                ->delay(now()->addSeconds((int) config('whatsapp.auto_reply_debounce_seconds', 12)));
        }

        // Independent of ai_enabled — keep the style profile improving from any conversation
        // (including the owner's own replies sent via the app), not just auto-reply traffic.
        $reanalyzeInterval = (int) config('whatsapp.auto_reanalyze_message_interval', 15);

        if ($reanalyzeInterval > 0) {
            $sinceLastAnalysis = optional($contact->styleProfile)->last_analyzed_at ?? $contact->created_at;

            $messagesSinceAnalysis = Message::where('contact_id', $contact->id)
                ->where('created_at', '>', $sinceLastAnalysis)
                ->count();

            if ($messagesSinceAnalysis >= $reanalyzeInterval) {
                AnalyzeContactHistory::dispatch($user->id, $contact->id);
            }
        }

        return response()->json(['status' => true, 'message_id' => $message->id]);
    }
}
