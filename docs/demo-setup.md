# Demo / presentation checklist

Use this **every time** the site is shown to someone else (instructor, panel, client). It exists
because the site once took 2–5 s per page at school while feeling instant at home — the cause was
payload and dev-server behaviour, not the database. Full write-up: `plans/performance-optimization.md`.

## Before you leave home (needs internet + your PC)

```bash
npm run build                 # public/build goes stale fast — the Sep 9 bundle was 30 KB behind
php artisan migrate           # picks up the performance indexes
php artisan optimize:clear    # clear first so nothing stale gets cached
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

`.env` must have:

```
APP_DEBUG=true            # or false for a real "production" feel
DEBUGBAR_ENABLED=false    # Debugbar adds ~100 KB to every page
REVERB_HOST=127.0.0.1     # never "localhost" (Windows tries IPv6 first: +500 ms)
```

> After `config:cache`, edits to `.env` are **ignored** until you run `php artisan config:clear`
> (or `optimize:clear`). If a setting "does nothing", that's why.
> Never leave `config:cache` on while developing — undo with `php artisan optimize:clear`.

## Serve it

| Option | When | Notes |
|---|---|---|
| **XAMPP Apache** vhost → `public/` | Best for a graded demo | Multi-process: CSS/JS/images download in parallel |
| `php artisan serve` | Fine for solo dev | Single-threaded; Windows can't raise workers. Every asset queues behind the last |

Also run `php artisan reverb:start` for real-time chat, and browse via **`http://127.0.0.1`**,
not `localhost`.

## Right before presenting

1. Open every page you plan to show **once** (warms OPcache and the browser cache).
2. Confirm `ls public/hot` says *no such file* (a stale one points assets at a dead dev server).
3. Do **not** run `npm run dev` for the demo — use the built bundle.
4. DevTools → Network → "Disable cache" **off**.

## If it's slow anyway — 60-second triage

1. DevTools → Network, hard reload. Look at **transferred MB** and the slowest requests, not the query count.
2. Anything from `images.unsplash.com` at `w=1200`? A view is missing `Images::resize()` / `loading="lazy"`.
3. Any request to `fonts.googleapis.com` or `cdn.jsdelivr.net`? Something re-added a CDN tag.
4. `php artisan test --filter=PerformanceBaseline` — compare **query counts** to the table in the plan.
