---
name: cloudflare-run
description: Use when Axcee wants to run the local app through a Cloudflare quick tunnel ("run cloudflare", "cloudflare tunnel", "/cloudflare-run"), for phone testing, demos or sharing a link, especially when ngrok's bandwidth is used up. Starts Laravel and the tunnel, then gives him the new link so HE can update `.env` himself. Also handles "done" (stop the tunnel, restore `.env`).
---

# cloudflare run

Serves the app from this PC through a free Cloudflare **quick tunnel** (`*.trycloudflare.com`). No account, no
bandwidth cap, no "Visit Site" warning page. The catch: **the URL is random and changes every run**, so Axcee
updates `.env` by hand each time. Whatever branch is checked out (plus uncommitted changes) is what visitors see.
Not real hosting: for that see `plans/hostinger-vps-deployment.md`.

Three modes: **start** (default), **apply** (after he has updated `.env`), and **stop** (when he is done).

**Rule: do not edit `.env` in start or apply mode.** He wants the link so he can put it in `.env`
himself. Only Stop mode touches `.env`, and only because he asked for it to be restored.

## Start

Reuse anything already running; never start a duplicate. Work from the repo root.

1. **cloudflared installed.** `~/.local/bin/cloudflared --version`. If missing, tell him to run
   `! curl -L -o ~/.local/bin/cloudflared https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 && chmod +x ~/.local/bin/cloudflared`
   himself (the `!` must be the very first character). Do not download it yourself: auto mode blocks it.
2. **Stop ngrok if it is running.** `pkill -x ngrok` (exact name; `pkill -f` can kill your own shell). Two
   tunnels waste memory on this PC.
3. **Laravel on port 8000.** `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000`. If it does not
   answer, start `php artisan serve --port=8000` with `run_in_background: true`.
4. **Tunnel.** Check for a running one first: `pgrep -x cloudflared`. If none, start
   `~/.local/bin/cloudflared tunnel --url http://127.0.0.1:8000 --no-autoupdate` with `run_in_background: true`,
   then read its output file for up to 20 s until it shows `https://<words>.trycloudflare.com`. If one is already
   running, read its URL from the same output file instead of starting another.
5. **Give him the link.** This is the main output. Show it on its own line, then the exact `.env` lines to
   change (see Report). Then **stop and wait**: he edits `.env`, then says "done" or "updated".

## Apply (after he says he has updated `.env`)

1. Read back `grep -nE '^(APP_URL|TRUSTED_PROXIES)' .env`. Check that **every** `APP_URL=` line equals the
   tunnel URL with **no trailing slash**, and that `TRUSTED_PROXIES=*` is there. `.env` has had `APP_URL` twice
   (lines 6 and 125); the first one wins, so both must match. If anything is wrong, say which line and stop.
2. `php artisan config:clear`.
3. Restart `artisan serve` (it keeps the old `APP_URL`): `kill <pid>` of the `php artisan serve` process
   (find it with `pgrep -fa 'artisan serve'`; kill the `php` pid, not a bash wrapper), then start it again in the
   background.
4. `php artisan db:show | head -8` must show a connection (MariaDB, `abanganan_hub`).
5. **Reverb (live chat).** `pgrep -f 'artisan reverb:start'`. Only start it if he plans to test chat or
   notifications; otherwise report its state.
6. **Verify through the tunnel:**
   - `curl -s <public_url>` returns 200.
   - The CSS link in that HTML starts with `https://` and fetching it returns `200 text/css`.
7. Report the status table below with the final result.

## Report

For **Start**, reply with:

> **Your new link:** `https://<words>.trycloudflare.com`
>
> Put this in `.env` (both lines if `APP_URL` appears twice, no trailing slash):
> `APP_URL=https://<words>.trycloudflare.com`
> and make sure `TRUSTED_PROXIES=*` is there. Tell me "updated" and I will restart Laravel and verify.

Then notes, short:

- The link **changes every run**. If the tunnel dies (for example a memory shutdown), the next start gives a new
  link and `.env` has to be updated again.
- Google/Facebook login needs `<link>/auth/google/callback` and `<link>/auth/facebook/callback` added in each
  provider's console, again for every new link.
- Live chat may not work over the tunnel: it forwards only port 8000, and Reverb has its own port. Test chat on
  a phone before relying on it.
- It serves the current branch (`git branch --show-current`); the PC must stay on and online.

For **Apply**, reply with this table, then the link:

| Check | Result |
|---|---|
| Laravel (port 8000) | ... |
| Cloudflare tunnel | ... |
| Public URL | ... |
| Database | ... |
| Reverb | ... |
| `.env` | `APP_URL` ..., `TRUSTED_PROXIES=*` |

## Stop

When he says he is done ("done with the tunnel", "stop cloudflare", "restore .env"):

1. `pkill -x cloudflared`.
2. In `.env` set every `APP_URL=` line to `http://localhost:8000` and delete the `TRUSTED_PROXIES` line.
3. `php artisan config:clear`, then restart `artisan serve` so it forgets the tunnel URL.
4. Confirm with `grep -nE '^(APP_URL|TRUSTED_PROXIES)' .env`.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| No `trycloudflare.com` URL after 20 s | Read the output file for the error. Usually no internet, or an old `cloudflared` still running (`pgrep -x cloudflared`). |
| Unstyled page, huge logo | Assets linked as `http://`. `TRUSTED_PROXIES=*` missing in `.env`, or `artisan serve` not restarted after the `.env` change. |
| Links go to localhost or the old link | `APP_URL` is wrong, has a trailing slash, or a second `APP_URL=` line still has the old value. Fix, `config:clear`, restart `artisan serve`. |
| `public/hot` exists, assets 404 | Vite dev server is blocking the tunnel. Delete `public/hot` or run `npm run build`. |
| `Address already in use` on 8000 | `artisan serve` is already running. Reuse it. |
| Tunnel and server vanish together | The system killed them for low memory. Report it, do not restart on your own; restart only when asked. |
| Social login fails | The new link's callback URLs are missing in the Google/Facebook console. |

## Rules

- Never edit `.env` in Start or Apply mode. He updates it himself.
- Do not commit `.env`. Tell him the `.env` changes are temporary until Stop is run.
- Do not suggest merging or deploying anything as part of this.
- This skill replaces ngrok only while ngrok's bandwidth is used up (ngrok resets in December). `ngrok-run` stays as is.
