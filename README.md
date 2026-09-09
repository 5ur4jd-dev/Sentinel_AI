# Sentinel AI

> Real prospect research for people who are tired of generic lead lists.

Sentinel AI is a local-first sales research workspace for freelancers, agencies, and small teams. It discovers businesses through OpenRouter web search, verifies their public websites, audits real HTML, qualifies opportunities, and prepares evidence-based outreach.

It is approval-based: Sentinel does not invent prospect replies, pretend an email was sent, or save an AI-generated business until its website has been fetched successfully.

## What it does

| Workflow | What happens |
| --- | --- |
| Discover | OpenRouter web search finds businesses for an industry and location. |
| Verify | Every candidate needs a source URL, a valid website URL, and a successful live fetch. |
| Audit | The fetched website is checked for SEO, structure, responsiveness, accessibility, and conversion signals. |
| Qualify | AI or deterministic local scoring turns findings into a usable lead score. |
| Draft | Personalized outreach is generated from actual audit findings. |
| Send | An owner explicitly sends approved outreach through authenticated SMTP. |
| Respond | A real email-provider webhook can pass inbound messages to the conversation engine. |

## Why it is different

- No dummy discovery fallback.
- No placeholder or guessed domains.
- No duplicate active websites.
- No automatic “sent” claims.
- No simulated prospect replies.
- Old unverified discovery records are archived instead of shown as active leads.
- SQLite works locally; MySQL remains supported for a larger deployment.

## Screens

- `/` — standalone product home page
- `/public/index.php` — working SDR dashboard
- `/public/lead.php?id=ID` — lead audit, qualification, outreach, and sending controls
- `/public/export.php?id=ID` — print-ready lead report
- `/public/inbound.php` — authenticated JSON endpoint for real inbound messages

## Requirements

- PHP 8+
- PHP extensions: `curl`, `json`, `pdo`, `pdo_sqlite`
- `dom` is recommended; Sentinel has a lightweight HTML fallback when it is unavailable
- OpenRouter API key for AI discovery and generation
- SMTP account for sending real email from localhost

On Fedora:

```bash
sudo dnf install php-pdo php-sqlite3 php-xml
```

On Debian or Ubuntu:

```bash
sudo apt install php-cli php-curl php-sqlite3 php-xml
```

## Local setup

1. Clone the repository and enter it:

   ```bash
   git clone https://github.com/5ur4jd-dev/Sentinel_AI.git
   cd Sentinel_AI
   ```

2. Create your local configuration:

   ```bash
   cp .env.example .env
   ```

3. Add your OpenRouter configuration to `.env`:

   ```env
   AI_PROVIDER=openrouter
   OPENROUTER_API_KEY=your_openrouter_key
   OPENROUTER_MODEL=nex-agi/nex-n2.5-mini:free
   AI_TIMEOUT=45
   ```

4. Start the local server from the project root:

   ```bash
   php -S 127.0.0.1:8000
   ```

5. Open `http://127.0.0.1:8000/`. The working dashboard is at `http://127.0.0.1:8000/public/index.php`.

The app automatically uses `storage/database.sqlite` when MySQL is unavailable. The storage directory and `.env` are ignored by git.

## OpenRouter discovery

Discovery is not a plain “ask the model for companies” prompt. The request uses OpenRouter's `openrouter:web_search` server tool and strict JSON output. Each result must include:

1. A real web-search source URL.
2. An official website URL.
3. A successful live HTTP fetch.
4. Non-placeholder, non-duplicate data.

If the API times out, returns malformed data, or cannot provide evidence, Sentinel returns zero new leads. That is intentional—the system prefers an empty result over a fictional lead.

The default model is configured in `.env.example`:

```env
OPENROUTER_MODEL=nex-agi/nex-n2.5-mini:free
```

You can change it to another OpenRouter model that supports web search tools and structured outputs.

## Real email sending

Sentinel uses authenticated SMTP for local sending:

```env
MAIL_FROM=your-email@example.com
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@example.com
SMTP_PASS=your-app-password
SMTP_SECURE=tls
```

For Gmail, use an App Password rather than your normal account password. The Send button only changes the lead to `Outreach Sent` after the SMTP server accepts the message.

## Real inbound replies

External email providers cannot call `localhost` directly. For local development, expose the app through a secure tunnel or use a mail-forwarding service that can reach your machine.

Configure a secret:

```env
INBOUND_WEBHOOK_TOKEN=replace-with-a-long-random-secret
```

Then POST normalized JSON to `/public/inbound.php`:

```json
{
  "token": "replace-with-a-long-random-secret",
  "from": "prospect@example.com",
  "text": "Thanks, I would like to discuss this next week."
}
```

The sender email must match an existing lead. Sentinel stores the inbound message, generates the next SDR response, and flags buying intent when appropriate.

## Database

For local work, SQLite is automatic. To use MySQL instead, set these values in `.env` and import [`schema.sql`](schema.sql):

```env
DB_HOST=127.0.0.1
DB_NAME=sentinel_ai
DB_USER=root
DB_PASS=your_password
```

## Testing

```bash
php tests/UnitTests.php
find . -name '*.php' -not -path './storage/*' -print0 | xargs -0 -n1 php -l
```

## Security notes

- Never commit `.env` or paste API keys into git issues, screenshots, or public chats.
- Rotate any API key that has been exposed.
- Use HTTPS and a strong webhook token outside local development.
- Review outreach before sending it.
- Respect consent, privacy, anti-spam, and applicable data-protection rules.

## Stack

PHP · SQLite/MySQL · OpenRouter · cURL · HTML/CSS · Vanilla JavaScript

## License

Add the license that matches how you want to distribute Sentinel AI.
