# API Specification
All routes below are prefixed `/api` and (except auth register/login) require Sanctum bearer auth.

- `/auth` — register, login, me, logout, fcm-token
- `/contacts` — CRUD (index, store, show, update, destroy)
- `/contacts/{contact}/messages` — index, store (send via WhatsApp gateway)
- `/contacts/{contact}/suggest-reply` — AI-generated reply suggestion
- `/contacts/{contact}/memories/analyze` — extract memories + refresh style profile from recent conversation
- `/memories` — index (filter by contact_id/type), store, destroy
- `/events` — CRUD, calendar entries
- `/settings` — index, update (upsert by key), destroy
- `/webhooks/fonnte/{secret}` — **public** (no Sanctum), verified by a shared secret path segment instead. Receives inbound WhatsApp messages from Fonnte, creates/updates the Contact, stores the Message, and fires an AI auto-reply only if both the owner's global `auto_reply_enabled` setting AND the contact's `ai_enabled` flag are on.
- `/contacts/{contact}` (update) also accepts `ai_enabled` (boolean) to toggle auto-reply for that one contact, independent of the global setting.

Not yet implemented: Google Calendar sync endpoints, Wablas inbound webhook (Fonnte only so far).