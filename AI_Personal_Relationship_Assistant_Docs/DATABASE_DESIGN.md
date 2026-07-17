# Database Design
Users, Contacts, Messages, Memories, Events, StyleProfiles, Settings.

`contacts.ai_enabled` (boolean, default true) — per-contact auto-reply toggle, ANDed with the global `auto_reply_enabled` Setting before the webhook auto-sends a reply.