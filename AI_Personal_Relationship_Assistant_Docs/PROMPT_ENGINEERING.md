# Prompt Engineering

All prompt classes live in `app/Services/AI/Prompts/`, each with a `system()` and `user()` static method, consumed identically by both `GeminiProvider` and `OpenAIProvider`.

- **`MemoryExtractionPrompt`** — reads a conversation, returns a JSON array of extracted memories (`type`: short_term/long_term/relationship, `content`, `importance` 1-10). Used by `MemoryEngine::analyzeAndStore()`, triggered manually via `POST /contacts/{id}/memories/analyze`.
- **`StyleAnalysisPrompt`** — reads a conversation, returns a JSON object describing the contact's communication style (formality level, tone, emoji usage, language, summary). Used by `MemoryEngine::buildOrUpdateStyleProfile()`, same manual trigger as above.
- **`ReplyGenerationPrompt`** — two variants:
  - `system()` / `user()` — plain reply suggestion, returns raw reply text. Used by `MemoryEngine::suggestReply()` for the on-demand `POST /contacts/{id}/suggest-reply` endpoint. No "should I reply" judgment — the human already asked for a suggestion.
  - `systemForAutoReply()` — used only by the unattended auto-reply pipeline (`MemoryEngine::suggestAutoReply()` → `App\Jobs\ProcessAutoReply`). Same style/memory context as the plain variant, plus an explicit instruction to first judge whether the conversation has reached a natural stopping point (any language/dialect/slang, judged by meaning not fixed keywords) before writing a reply. Returns JSON `{"needs_reply": bool, "reply": string}` instead of raw text, so the decision and the reply come out of a single AI call.

See AGENTS.md ("Auto-reply pipeline") for why the auto-reply variant exists and what alternatives (keyword lists, rate-limiting) were tried and rejected first.