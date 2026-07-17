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

## Not started
- Wablas inbound webhook (Fonnte only so far)
- Automatic memory extraction trigger on incoming messages (currently manual via `/contacts/{id}/memories/analyze`)
- Install the production crontab (cron every minute → `queue:work --stop-when-empty`, confirmed viable with Rumahweb support — see AGENTS.md for the exact line and PHP-binary caveat). Not deployed yet — everything so far has only run against local `artisan serve`.
- Google Calendar two-way sync + conflict detection
- FCM push notifications
- Mobile app