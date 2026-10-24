# AI integration (Resource Centre)

## What it is
Per-tenant chat assistant using any **OpenAI-compatible** HTTP API:
- OpenAI (`https://api.openai.com/v1`)
- xAI Grok (`https://api.x.ai/v1`)
- Groq, Azure OpenAI (custom base URL), Ollama local (`http://127.0.0.1:11434/v1`)

## Enable
1. Super Admin → Tenant Setup → enable module **`ai`**.
2. Open **AI Assistant** tab → Settings.
3. Choose provider, paste API key, Save.
4. Key is stored only at `tenants/{id}/data/config/ai.json` (not in git).

## Features
- Free-form chat
- **Sales MIS brief** — uses live sales register aggregates (no full PII dump)
- Social caption / email structure helpers

## Security
- Server-side calls only; key never sent to the browser after save
- CSRF on all actions
- Audit log entries without prompt bodies or secrets
- Disable anytime via Settings or module matrix
