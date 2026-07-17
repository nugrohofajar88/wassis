# TODO

## Done
- Setup Laravel (13, PHP 8.4, MySQL)
- Setup WhatsApp gateway abstraction (Fonnte, Wablas)
- Setup AI provider abstraction (OpenAI, Gemini — default Gemini)
- Auth (register/login/me/logout/fcm-token via Sanctum)
- Memory Engine (remember/recall/forget/analyzeAndStore/suggestReply)
- Contact, Message, Memory, Event, Setting REST API
- Inbound WhatsApp webhook (Fonnte) + Auto Reply (opt-in via `auto_reply_enabled` setting)
- Auto-reply pipeline: debounce (batches rapid-fire message bursts), AI-judged `needs_reply` (reads conversation context instead of keyword matching), daily per-contact ceiling as a cost/spam backstop (`App\Jobs\ProcessAutoReply`)
- `ai_enabled` flipped to opt-in (default false, not true) — owner reviews and enables auto-reply per contact rather than disabling it after the fact
- Deployed to production (`wassis.fajarnugroho.info`, Rumahweb shared hosting), crontab installed and confirmed working (`schedule:run` every minute → in-process `queue:work --stop-when-empty`, see AGENTS.md)
- WhatsApp chat export import (`/contacts/{id}/messages/import`) — backfills real historical messages from a phone-exported `.txt` so style/memory analysis learns the owner's actual voice, not just AI-generated auto-reply text

## Not started
- Wablas inbound webhook (Fonnte only so far)
- Automatic memory extraction trigger on incoming messages (currently manual via `/contacts/{id}/memories/analyze`, or right after a chat import)
- Google Calendar two-way sync + conflict detection
- FCM push notifications
- Mobile app