# API Specification
All routes below are prefixed `/api` and (except auth register/login) require Sanctum bearer auth.

- `/auth` — register, login, me, logout, fcm-token
- `/contacts` — CRUD (index, store, show, update, destroy)
- `/contacts/{contact}/messages` — index, store (send via WhatsApp gateway)
- `/contacts/{contact}/messages/import` — upload a WhatsApp "Export Chat" .txt file (multipart: `file`, `owner_name`) to backfill historical messages for a contact. Response (`201`) returns immediately with `imported_count`/`skipped_count` and `"analysis": "queued"` — memory extraction + style profile analysis run in a background job (`App\Jobs\AnalyzeContactHistory`), not inline, so a large import's AI calls can't make the request itself time out. This is how the AI learns the *owner's* actual voice — normal webhook traffic never captures what the owner types from their own phone, only inbound messages.
- `/contacts/{contact}/suggest-reply` — AI-generated reply suggestion
- `/contacts/{contact}/memories/analyze` — extract memories + refresh style profile from recent conversation
- `/contacts/{contact}/style-profile` (GET) — read the contact's current `StyleProfile` row (`{"style_profile": null}` if no analysis has ever run). No AI call — a passive read counterpart to `memories/analyze`, added for the `jarwo_app` mobile client so viewing a contact's style doesn't force a costly re-analysis.
- `/memories` — index (filter by contact_id/type), store, destroy
- `/events` — CRUD, calendar entries
- `/settings` — index, update (upsert by key), destroy. Notable keys: `auto_reply_enabled` (global auto-reply switch), `default_persona` (free-text fallback style used for contacts with no `StyleProfile` yet — see AGENTS.md "Default persona + periodic auto-reanalysis").
- `/webhooks/fonnte/{secret}` — **public** (no Sanctum), verified by a shared secret path segment instead. Receives inbound WhatsApp messages from Fonnte, creates/updates the Contact, stores the Message, fires an AI auto-reply only if both the owner's global `auto_reply_enabled` setting AND the contact's `ai_enabled` flag are on, and independently dispatches `App\Jobs\AnalyzeContactHistory` once enough new messages have accumulated for that contact (see AGENTS.md).
- `/contacts/{contact}` (update) also accepts `ai_enabled` (boolean) to toggle auto-reply for that one contact, independent of the global setting.

Not yet implemented: Google Calendar sync endpoints, Wablas inbound webhook (Fonnte only so far).