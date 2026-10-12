# Exeter St David's Departures Board

A wall display for a kiosk browser (Raspberry Pi) showing live trains from Exeter St David's to Exmouth, Plymouth, Okehampton, Barnstaple and Tiverton Parkway. One tab per destination, auto-rotating, with a card per service. See `DESIGN.md` for the full brief.

Train information comes from National Rail's Darwin Live Departure Boards feed. The API key stays on the server; the browser only talks to the PHP API in `/api`.

## Quick start (local, no API key needed)

1. Copy `.config.example.json` to `.config.json` (already done in a fresh checkout of this folder).
2. Serve the project root with PHP. Either use Laragon (Apache on `http://localhost/`, project reachable as `/exeter-departures/`), or for a quick test:

   ```bash
   php -S localhost:8081 -t .
   ```

3. In `.config.json`, set `devApiBase` to match, for example `http://localhost:8081/api`. This is the only place the frontend's API base URL is configured — there's no separate `.env` file.
4. Start Vite (if port 5173 is already in use, the dev server is already running, so just use it):

   ```bash
   npm install
   npm run dev
   ```

5. Open `http://localhost:5173/?mock=1` to use fixture data covering on time, late, very late, cancelled, delayed, no platform, non-stop and midnight rollover cases. Drop `?mock=1` to use live Darwin data.

The PHP built-in server ignores `.htaccess`, so `/.config.json` will be readable there. On Apache it is blocked.

## Real data

1. Register at <https://raildata.org.uk> and subscribe to the "Live Departure Board - Staff Version" API product.
2. The product is REST/JSON, authenticated with an `x-apikey` header. Put the key in `.config.json` under `darwin.apiKey`, and check `darwin.endpoint` against the exact URL shown on the Rail Data Marketplace product page if it differs from the example.
3. Visit `/api/health.php` then `/api/departures.php?dest=EXM`.

Requests are plain GETs sent with cURL, so no PHP `soap` extension is required — just `curl`.

## Tests

```bash
npm test                          # Vitest: status and time window logic
php tests/normalise_test.php      # PHP normaliser against a saved Darwin JSON fixture
npm run lint
```

## Deployment (Hostinger)

1. `npm run build`
2. Upload the contents of `dist/` to the web root, plus `api/`, `.htaccess` and `.config.json`. Set `allowedOrigins` in `.config.json` to the production origin.
3. Make sure `api/cache/` is writable.
4. Check `/api/health.php`, `/api/departures.php?dest=EXM`, and that `/.config.json` returns 403 or 404.

## Raspberry Pi kiosk

- Raspberry Pi OS with desktop, auto-login, timezone Europe/London, NTP on.
- Start Chromium on boot:

  ```bash
  chromium-browser --kiosk --noerrdialogs --disable-infobars --incognito https://YOUR-DOMAIN/
  ```

- Disable screen blanking: `xset s off`, `xset -dpms`, `xset s noblank` (or `raspi-config`).
- Optional nightly browser restart via cron or a systemd timer. The page also reloads itself at 03:00.

## Time windows

Darwin looks ahead at most 120 minutes, so the board cannot show 16:00 trains at 08:00. Up to 09:00 the morning section is live, from 09:00 to 14:00 both show a placeholder plus the next three trains, and from 14:00 the afternoon section is live. All times are set in `.config.json` under `windows`.

## Licence

Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International. See `LICENSE`.

Train running information is provided by National Rail Enquiries (www.nationalrail.co.uk). This project is not affiliated with or endorsed by National Rail.
