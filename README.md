# PERSAKA 26/27 · Escape the Passcode (Detective Arcade edition)

A timed puzzle game with a detective arcade look. Five sealed case files sit on your desk. Each one hides a
3-digit vault combination behind three clues, and you have 60 seconds to crack it. Finish all five to close
the investigation, then see where your score lands on the leaderboard.

Built with **Vue 3 (Vite)** for the game, **PHP Slim 4** for the API, and **MySQL/MariaDB** (Laragon + HeidiSQL) for the leaderboard.
The original single-file version is kept in `legacy/escape-the-passcode.html`.

---

## About the game

### How to play

1. Enter a detective name (2 to 16 characters: letters, numbers, spaces, `_` `.` `-`) and press **Start case 01**.
2. Read the three clues. Each clue works out to a single digit.
3. Type the three digits in order and press **Unlock file** (or Enter). You can also paste a 3-digit code.
4. A correct code stamps **ACCESS GRANTED** and unlocks the next case. Cases get harder as your rank rises,
   from Rookie to Master Detective.

### Rules

- **60 seconds per case.** The timer starts when a case opens. Time spent on the stamp screen between cases does not count.
- **5 tries per case.** Each wrong combination uses one try. Losing all five locks the vault.
- **The run ends** if time runs out or the vault locks. Your score so far is still saved if you solved at least one case.
- **Sound** effects can be muted with the button in the header.

---

## Score calculation

The server works out the score, not the browser, so it cannot be edited from the dev tools.

### Points for one solved case

```
base       = 1000 + (10 × seconds left) − (100 × wrong tries on this case)
case score = base × (1 + 0.2 × case index)
```

- **Seconds left** is the whole seconds remaining on the 60-second timer when you enter the correct code.
- **Wrong tries** only count on the case they happened in. Each one costs 100 points before the multiplier.
- **Case index** starts at 0, so later cases are worth more:

| Case | Rank | Multiplier |
|---|---|---|
| 01 | Rookie | ×1.0 |
| 02 | Junior Detective | ×1.2 |
| 03 | Detective | ×1.4 |
| 04 | Senior Investigator | ×1.6 |
| 05 | Master Detective | ×1.8 |

The base has a safety floor of 100. With 5 tries per case the base can never drop below 600, so the floor only matters if you raise `maxAttempts`.

### Worked examples

| Situation | Base | Multiplier | Case score |
|---|---|---|---|
| Case 01, solved with 45 s left, no wrong tries | 1000 + 450 = 1450 | ×1.0 | **1,450** |
| Case 03, solved with 40 s left, 1 wrong try | 1000 + 400 − 100 = 1300 | ×1.4 | **1,820** |
| Case 05, solved with 5 s left, 3 wrong tries | 1000 + 50 − 300 = 750 | ×1.8 | **1,350** |

### Your total

Your **score** is the sum of all solved cases.

- Solving every case with 50 seconds left on each (10 seconds of work per case) gives **10,500**.
- The theoretical maximum is **11,200** (every case solved instantly with no wrong tries).

### Total time and ranking

**Total time** adds up the time you spent on each case. A case you fail counts its full time (60 s for a timeout).

The leaderboard ranks runs by:

1. **Score**, highest first
2. **Total time**, lowest first (breaks ties)
3. **Finish time**, earliest first (breaks any remaining tie)

A run only appears on the board if it is finished and solved at least one case. The **Cases** column shows how many were solved,
with a ✓ when all five were.

---

## Project structure

```
backend/     PHP Slim 4 API (answers, timer, scoring, leaderboard)
  config/game.php        Cases, clues, answers, time limit, tries per case
  database/schema.sql    MySQL table (run once in HeidiSQL)
  public/index.php       Slim app and routes
  src/GameService.php    All game rules and scoring
  tests/                 Rule checks (run with composer test)
frontend/    Vue 3 app (arcade UI, sound, leaderboard)
  src/game.js            Game state and timer
  src/components/        Screens and leaderboard table
  public/persaka-logo.png
legacy/      The original single-file game
```

---

## Run it locally

### Requirements

- **Laragon** with PHP 8.1+ (`pdo_mysql` enabled, which is the default) and MySQL/MariaDB
- **Composer** (bundled with Laragon)
- **Node.js 18+**

### 1. Create the database

1. Start Laragon (**Start All**).
2. Open HeidiSQL from the Laragon menu (**Database > HeidiSQL**). If you create the session yourself:
   network type **MySQL (TCP/IP)**, host `127.0.0.1`, user `root`, password empty, port `3306`.
3. Open a **Query** tab, paste the contents of `backend/database/schema.sql`, and press **F9**.
4. Refresh the left panel. You should see `escape_passcode` > `runs`.

If your MySQL password or port is different, copy `backend/.env.example` to `backend/.env` and edit it:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=escape_passcode
DB_USER=root
DB_PASS=
```

### 2. Start the API (port 8000)

```bash
cd backend
composer install
composer start
```

### 3. Start the game (port 5173)

Open a second terminal:

```bash
cd frontend
npm install
npm run dev
```

Open **http://localhost:5173**. The dev server forwards `/api` requests to `http://localhost:8000`.

### 4. Check that it saves

Finish one case, then run this in HeidiSQL:

```sql
SELECT player_name, score, total_time_ms, cases_solved, status FROM runs;
```

### Optional: rule tests

```bash
cd backend
composer test
```

The tests use a temporary in-memory SQLite database (needs `pdo_sqlite`), so they never touch your MySQL data.

### Troubleshooting

| Message or symptom | What to check |
|---|---|
| "Cannot reach the case server" | `composer start` is running in `backend/` and port 8000 is free |
| "Database unavailable" | Laragon MySQL is running, `schema.sql` was run, and `backend/.env` matches your credentials. The real error is printed in the `composer start` terminal |
| `could not find driver` | Enable `pdo_mysql` in Laragon (Menu > PHP > Extensions) |
| Blank leaderboard | Normal until someone solves at least one case |

---

## Customising

- **Cases, clues, answers, time limit, tries per case:** `backend/config/game.php`. The answers stay on the server.
- **Scoring formula:** `GameService::guess()` in `backend/src/GameService.php`.
- **Rows shown per leaderboard:** the `:limit` value in `IntroScreen.vue` (5), `ResultScreen.vue` (10) and `BoardScreen.vue` (20).
  The API caps a request at 50 in `ApiController.php`.
- **Colours and fonts:** the variables at the top of `frontend/src/style.css`.

## API reference

| Method | Path | Purpose |
|---|---|---|
| GET  | `/api/config` | Case count, time limit, tries per case |
| POST | `/api/runs` `{name}` | Start a run, returns a private `runId` |
| POST | `/api/runs/{id}/start` | Open the current case and start its timer |
| POST | `/api/runs/{id}/guess` `{code}` | Returns `wrong`, `correct`, `locked` or `timeout` |
| POST | `/api/runs/{id}/timeout` | Client reports its countdown hit zero; the server verifies |
| GET  | `/api/leaderboard?limit=10&run={id}` | Ranked entries, plus the caller's own row |

## Production notes

- Build the game with `npm run build` and serve `frontend/dist/`. Route `/api/*` to `backend/public/index.php`
  (Apache uses the included `.htaccess`; nginx needs `try_files $uri /index.php$is_args$args;`).
- If the API is on another origin, build with `VITE_API_BASE=https://api.example.com/api` and set
  `CORS_ORIGIN=https://your-site.example` for PHP.
- Use a dedicated MySQL user instead of `root`, and keep `APP_DEBUG` off.
- Not included: rate limiting and a profanity filter for player names. Add them at the web-server layer or as Slim middleware
  if the game is public.
