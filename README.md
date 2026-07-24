# Sentinel AI v0.1

An autonomous AI sales development assistant for freelancers, agencies, and small businesses.

## What this version includes

- Manual lead import via form or CSV
- Website audit from a submitted website URL
- AI-generated business understanding summary
- Personalized outreach draft per lead
- Export report as print-ready HTML
- Owner approval flow for AI outputs

## Tech stack

- PHP 8+
- MySQL
- HTML5, CSS3, Vanilla JS
- OpenRouter.ai / NVIDIA build.nvidia.com AI provider support

## Setup

1. Copy `.env.example` to `.env`.
2. Configure your MySQL connection and AI provider keys.
3. Import `schema.sql` into MySQL.
4. Serve the app from `public/` on PHP built-in server or your local web server.

### Required PHP extensions

- `pdo_mysql`
- `curl`
- `json`

## `.env` values

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `AI_PROVIDER` (`openrouter` or `nvidia`)
- `OPENROUTER_API_KEY`
- `NVIDIA_API_KEY`

## Notes

- PDF export is not implemented in v0.1 to avoid dependency weight. The app provides a clean printable HTML export view.
- AI responses are parsed as structured JSON and retried once if malformed.
