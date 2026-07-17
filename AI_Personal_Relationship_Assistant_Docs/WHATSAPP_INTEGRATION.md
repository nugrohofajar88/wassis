# WhatsApp Integration
Gateway abstraction via `WhatsAppGatewayInterface`, switched by `WHATSAPP_DRIVER` env var. Implemented drivers: **Fonnte** and **Wablas** (`app/Services/WhatsApp/`). Meta Cloud API is not implemented.

**Inbound**: `POST /api/webhooks/fonnte/{secret}` (Fonnte only; single-tenant — configured `WHATSAPP_OWNER_EMAIL` app user owns all incoming messages). Fonnte doesn't sign webhook payloads, so the `{secret}` path segment (`FONNTE_WEBHOOK_SECRET`) is the verification mechanism — set it as the webhook URL in the Fonnte device dashboard. Duplicate deliveries are deduped by `inboxid`, when a real (non-zero) one is present — see AGENTS.md.

**Auto-reply toggle is two-tier and opt-in** (pattern borrowed from the sibling `chatbot-crm` project, opt-in default added 2026-07-17): a reply is only auto-sent when **both** are true — the owner's global `auto_reply_enabled` Setting, AND the specific contact's `ai_enabled` column (`contacts` table, default **`false`**). New contacts (however created — webhook or API) start with auto-reply off; the owner reviews and enables it per contact via `PUT /api/contacts/{id}` with `{"ai_enabled": true}` once they're ready. This is deliberately opt-in rather than opt-out — the owner wants to decide who gets auto-replied to before it happens, not disable it after the fact.

Confirmed working with a real inbound WhatsApp message on 2026-07-16 (via a Cloudflare quick tunnel to a local dev server) — contact auto-created, message content stored correctly.

Auto-reply itself was tested the same day and hit a real bug (fixed — see AGENTS.md: Fonnte's send API returns `id` as an array, which crashed the save step) plus a real operational risk (possible bot-vs-bot reply loop against a contact that has its own auto-responder).

**Auto-reply now runs through a three-part pipeline** (`App\Jobs\ProcessAutoReply`), not an immediate synchronous send:
1. **Debounce** — waits `AUTO_REPLY_DEBOUNCE_SECONDS` (default 12s) after an inbound message before replying, so a burst of separate WhatsApp bubbles from one person is read as one combined thought. If another message arrives from the same contact before the wait is up, the earlier reply attempt is dropped in favor of the newer one.
2. **AI reads the room** — before generating a reply, the AI judges (from the recent conversation, not a keyword list) whether the exchange has actually reached a natural stopping point, in any language or slang. If the person is just signing off / acknowledging, no reply is sent at all.
3. **Daily ceiling per contact** (`AUTO_REPLY_DAILY_LIMIT`, default 25) as a cost/spam backstop — not a smart loop detector, just a hard ceiling that auto-disables that contact's `ai_enabled` if hit.

`auto_reply_enabled` is currently turned **off** for the real account pending a re-test now that these safeguards exist — see AGENTS.md for the full design rationale and the production deployment plan (no persistent worker on the target hosting, cron-driven queue processing instead).