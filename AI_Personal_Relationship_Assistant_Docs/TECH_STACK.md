# Tech Stack
- Backend: Laravel 13, requires PHP >=8.4.1
- Mobile: Expo/React Native (TypeScript), `jarwo_app` — companion dashboard client, mirrors the sibling `pioneer-cnc-mobile` project's conventions; see AGENTS.md's "Companion mobile app" section
- DB: MySQL (via XAMPP/MariaDB), database `jarwo`
- AI: provider-abstracted (OpenAI or Gemini via `AI_DRIVER`); default driver is **Gemini** (`gemini-2.5-flash`)
- WhatsApp: provider-abstracted (Fonnte or Wablas via `WHATSAPP_DRIVER`); default driver is **Fonnte**
- Push: Firebase Cloud Messaging (not yet implemented)
