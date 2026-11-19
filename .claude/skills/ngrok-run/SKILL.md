---
name: ngrok-run
description: Use when Axcee wants to run the local app through ngrok for any reason (phone test, demo link, sharing with the PM, testing webhooks or social login) ("run ngrok", "start the tunnel", "/ngrok-run"), or says he is done and `.env` needs restoring. Starts Laravel, the ngrok tunnel and the `.env` settings, verifies everything, and reports a status table.
---

# ngrok run

Serves the app from this PC through a public ngrok URL, for any use: phone testing, demos, sharing with the
PM, webhooks, social login. Whatever branch is checked out (plus uncommitted changes) is what visitors see. Not real hosting: for that see `plans/hostinger-vps-deployment.md`.

Two modes: **start** (default) and **stop** (when he says he is done).

## Start

Run the checks in order. Reuse anything already running; never start a duplicate.

1. **ngrok installed and signed in.** `ngrok version`. If missing, install the Linux tarball to
   `~/.local/bin` (`https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz`). If
   `~/.config/ngrok/ngrok.yml` has no authtoken, tell him to run `! ngrok config add-authtoken <TOKEN>`
   himself. Never ask him to paste the token into chat.
2. **Laravel on port 8000.** `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000`. If it does not
   answer, start `php artisan serve --port=8000` with `run_in_background: true`.
3. **Tunnel.** `curl -s http://127.0.0.1:4040/api/tunnels` and read `public_url`. If there is none, start
   `ngrok http 8000 --log=stdout` in the background, wait ~4 s, read `public_url` again. The free account has
   one static domain, so the URL should be the same every run: `https://unbeaten-jarring-sniff.ngrok-free.dev`.
4. **`.env`.** Set `APP_URL=<public_url>` with **no trailing slash** and make sure `TRUSTED_PROXIES=*` is
   there (add the line if missing). Then `php artisan config:clear`. If `APP_URL` changed, restart
   `artisan serve` too (it keeps the old value).
5. **Database.** `php artisan db:show | head -8` must show a connection (MariaDB, `abanganan_hub`).
6. **Reverb (live chat).** Check `pgrep -f 'artisan reverb:start'`. Only start it
   (`php artisan reverb:start`, background) if he plans to test chat or notifications; otherwise just report
   "not running".
7. **Verify through the tunnel**, with the header that skips ngrok's warning page:
   - `curl -s -H 'ngrok-skip-browser-warning: 1' <public_url>` returns 200.
   - The CSS link in that HTML starts with `https://`, and fetching it returns `200 text/css`.
     `http://` links mean proxy trust is broken (unstyled page for visitors). See Troubleshooting.
8. **Report** this table, then the URL, then the notes below:

   | Check | Result |
   |---|---|
   | Laravel (port 8000) | ... |
   | ngrok tunnel | ... |
   | Public URL | ... |
   | Database | ... |
   | Reverb | ... |
   | `.env` | `APP_URL` ..., `TRUSTED_PROXIES=*` |

Notes to include, short: visitors must tap **Visit Site** on ngrok's warning page (once per visitor); it
serves the current branch (`git branch --show-current`); the PC must stay on and online; Google/Facebook
login needs `<public_url>/auth/<provider>/callback` in the provider's allowed redirect URIs.

## Stop

When he says he is done ("done", "stop ngrok", "restore .env"):

1. `pkill -x ngrok` (match the exact name; `pkill -f` can kill your own shell).
2. In `.env` set `APP_URL=http://localhost:8000` and delete the `TRUSTED_PROXIES` line.
3. `php artisan config:clear`. Leave `artisan serve` running unless he asks to stop it.
4. Confirm with `grep -nE '^(APP_URL|TRUSTED_PROXIES)' .env`.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Unstyled page, huge logo | Assets linked as `http://`. Check `TRUSTED_PROXIES=*` is in `.env`; `bootstrap/app.php` reads it in `$app->booted()` (it must not use `env()` inside `withMiddleware`, which runs before `.env` loads). Restart `artisan serve`. |
| Links go to localhost | `APP_URL` is wrong or has a trailing slash. Fix it and `config:clear`. |
| `public/hot` exists, assets 404 | Vite dev server is blocking the tunnel. Delete `public/hot` or run `npm run build`. |
| `Address already in use` on 8000 | `artisan serve` is already running. Reuse it. |
| Tunnel URL changed | Free static domain not claimed. Claim it in the ngrok dashboard and use `ngrok http --url=<domain> 8000`. |
| Social login fails | Callback URL missing in the provider settings (see notes). |

## Rules

- Never print or store the ngrok authtoken. It lives in `~/.config/ngrok/ngrok.yml`.
- Do not commit `.env`. Tell him the `.env` changes are temporary until Stop is run.
- Do not suggest merging or deploying anything as part of this.
