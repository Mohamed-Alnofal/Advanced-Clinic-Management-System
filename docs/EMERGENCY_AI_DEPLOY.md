# Emergency AI — production deploy checklist

## Required on remote server (do NOT rely on local Ollama)

1. In `.env`:
   - `EMERGENCY_AI_PROVIDER=gemini`
   - `GEMINI_API_KEY=<your key>`
   - `GEMINI_MODEL=gemini-2.0-flash`
   - `EMERGENCY_AI_FAILOVER=openai` (optional if `OPENAI_API_KEY` set)
   - `EMERGENCY_AI_EMBEDDINGS=true`
   - `QUEUE_CONNECTION=redis` (or `database`) and run `php artisan queue:work`

2. Health check:
   - `GET /api/v1/emergency/ai-health` must return `"ready": true`

3. Smoke test:
   ```bash
   curl -X POST "$APP_URL/api/v1/emergency/triage" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{"latitude":33.5,"longitude":36.3,"text_transcript":"ألم شديد في الصدر وضيق تنفس وتعرق"}'
   ```
   Expect `ai_fallback_used: false` and a cardiac-related category when Gemini is healthy.

4. Flutter app `.env`:
   - `BASE_URL=https://your-domain.com/api` (HTTPS public API, not LAN IP)

5. Local development may keep `EMERGENCY_AI_PROVIDER=ollama` with Ollama running on the same machine.
