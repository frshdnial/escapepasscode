# Escape the Passcode — PERSAKA 26/27 (Detective Arcade edition)

Vue 3 (Vite) frontend + PHP Slim 4 backend with a MySQL/MariaDB leaderboard (Laragon + HeidiSQL).
The original single-file game is kept in `legacy/escape-the-passcode.html`.

```
backend/    Slim 4 API  (answers, timer, scoring, leaderboard)
frontend/   Vue 3 app   (arcade UI, sound, HUD)
```

## Run it locally

Requirements: Laragon (PHP 8.1+ with `pdo_mysql`, MySQL/MariaDB), Composer, Node 18+.

**1. Create the database.** Start Laragon, open HeidiSQL (Laragon menu > Database > HeidiSQL), connect to the
MySQL session (host `127.0.0.1`, user `root`, empty password, port `3306`), open a Query tab, paste
`backend/database/schema.sql` and press F9.
If your credentials differ, copy `backend/.env.example` to `backend/.env` and edit it.

**2. Start the servers.**

```bash
# terminal 1: API on http://localhost:8000
cd backend
composer install
composer start

# terminal 2: web app on http://localhost:5173 (proxies /api to :8000)
cd frontend
npm install
npm run dev
```

Optional self-check (uses an in-memory SQLite database, not MySQL): `cd backend && composer test`.

## How the leaderboard works

The browser never sees the answers. The server owns the puzzle codes, the per-case timer and the score,
so nobody can post a fake score from the dev tools.

| Method | Path | Purpose |
|---|---|---|
| GET  | `/api/config` | case count, time limit, tries per case |
| POST | `/api/runs` `{name}` | start a run, returns a private `runId` |
| POST | `/api/runs/{id}/start` | open the current case (this starts its 60s timer) |
| POST | `/api/runs/{id}/guess` `{code}` | returns `wrong`, `correct`, `locked` or `timeout` |
| POST | `/api/runs/{id}/timeout` | client reports its countdown hit zero; server verifies |
| GET  | `/api/leaderboard?limit=10&run={id}` | ranked entries, plus the caller's own row |

**Score per solved case** = `(1000 + 10 × seconds left − 100 × wrong tries, min 100) × (1 + 0.2 × case index)`.
Later cases are worth more. A run at 10 seconds per case scores 10,500.
**Ranking** = score, then lower total time, then earlier finish. Runs that end early (time out or 5 wrong tries)
still count if at least one case was solved.

Rules to tune live in `backend/config/game.php` (`timeLimitSeconds`, `maxAttempts`, and the cases themselves).
The 5-tries-per-case limit is new: without it a script could brute-force all 1,000 combinations.

## Production notes

- Build the frontend with `npm run build` and serve `frontend/dist/`. Route `/api/*` to `backend/public/index.php`
  (nginx `try_files $uri /index.php$is_args$args;` for the API location, or use the included `.htaccess` on Apache).
- If the API lives on another origin, set `VITE_API_BASE=https://api.example.com/api` at build time and
  `CORS_ORIGIN=https://your-site.example` for PHP.
- Database settings come from `backend/.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`). Create a dedicated MySQL user for production instead of `root`.
- Set `APP_DEBUG=true` only while developing.
- Not included: rate limiting and a profanity filter for names. Add them at the web-server layer or in Slim middleware if the game is public.
