# Exeter St David's Departures Board: Design Document

Design brief for development in Claude Code. Author: Simon Rundell, Programme Leader, Faculty of Cybersecurity and Information Technologies, Exeter College.

## 1. Purpose

A wall display, run in a kiosk browser on a Raspberry Pi, that shows live train information from Exeter St David's for students travelling at the start and end of the college day. One tab per destination, auto-rotating, with cards for each service.

Destinations (one tab each, matching the National Rail URLs supplied):

| Tab | Station | CRS code |
|---|---|---|
| Exmouth | Exmouth | EXM |
| Plymouth | Plymouth | PLY |
| Okehampton | Okehampton | OKE |
| Barnstaple | Barnstaple | BNP |
| Tiverton Parkway | Tiverton Parkway | TVP |

Origin: Exeter St David's, CRS `EXD`.

## 2. Key decisions

1. **Data source: the official National Rail Darwin feed, not page scraping.** The nationalrail.co.uk pages are rendered by JavaScript, are brittle to parse and their terms do not invite automated scraping. The Live Departure Boards Staff Web Service (LDBSVWS), subscribed to as an API product on the Rail Data Marketplace, returns the same live data legitimately as plain JSON over HTTPS. A free API key is required (see section 9).
2. **Server-side fetch.** The API key must never reach the browser. React calls our PHP REST API with Axios, the PHP API calls Darwin and caches the result.
3. **Two sections per tab:** Morning (before 09:00) and Afternoon (after 16:00). See the limitation in section 5.
4. **Kiosk behaviour:** tabs auto-rotate, with a visible progress indicator, and a click or touch pauses rotation briefly.
5. **Hosting:** Hostinger runs the PHP API and the built React app. The Pi only runs a browser.

## 3. Stack and conventions

Follows the standard house stack.

- React + Vite + JavaScript (no TypeScript), scaffolded with `npm create vite@latest`. React code in the project root, backend in `/api`.
- Axios for all HTTP calls from the frontend.
- PHP REST API in `/api`. Configuration read from `.config.json`.
- Single CSS file (`src/App.css`, imported once). No inline CSS unless unavoidable.
- JSDoc on every function and component in JavaScript, PHPDoc on every PHP function and file.
- After scaffolding, install the react-doctor skill: `npx -y react-doctor@latest install`
- Licence: Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0). No reference to any private company in the code, README or footer.

No database is required. The API is stateless apart from a short-lived file cache.

## 4. Project structure

```
exeter-departures/
  .htaccess                 root rewrite rules and .config.json block
  .config.json              API key, origin, destinations, timings (never served)
  .config.example.json      safe template for the repo
  index.html
  package.json
  vite.config.js
  src/
    main.jsx
    App.jsx
    App.css                 the only stylesheet
    api/client.js           Axios instance and fetchDepartures()
    hooks/useDepartures.js  polling, error and stale-data handling
    hooks/useRotation.js    tab auto-rotation and pause logic
    utils/status.js         on time / late / due at logic
    utils/timeWindows.js    morning and afternoon bucketing
    components/
      TabBar.jsx
      DestinationPanel.jsx
      SectionGroup.jsx      "Morning" or "Afternoon" heading plus cards
      ServiceCard.jsx
      StatusBadge.jsx
      Footer.jsx            National Rail credit, licence, last updated
  api/
    cors.php                shared CORS, included first in every endpoint
    config.php              loads and validates .config.json
    ldbws.php               Darwin LDBSVWS REST client
    cache.php               tiny file cache with TTL
    departures.php          GET ?dest=EXM
    destinations.php        GET list of configured tabs
    health.php              GET basic status for monitoring
    cache/                  runtime cache files (not web readable)
  README.md
```

## 5. Data model and the time window limitation

Darwin departure boards look ahead a maximum of 120 minutes (`timeWindow`), and `timeOffset` can shift that window by up to 119 minutes either way. That means a live board cannot show 16:00 trains at 08:00. The design handles this honestly:

- Each tab has two sections, **Before 09:00** and **After 16:00**.
- A section fills with live services when its time falls inside the live window. Otherwise it shows a calm placeholder such as "Evening services appear from 14:00".
- Behaviour by clock time, all configurable in `.config.json`:
  - Up to 09:00: Morning section live, Afternoon placeholder.
  - 09:00 to 14:00: both placeholders with a note that the next departures are not yet in the live window. Optionally show the next three services regardless, in a third "Next trains" section.
  - From 14:00: Afternoon section live (the 2 hour window reaches 16:00), filtered to departures at or after 16:00.
- Phase 2 option (out of scope for the first build): load the scheduled timetable from the Rail Data Marketplace timetable feed so both sections can show planned trains all day, with live status layered on when available.

### Normalised service object (API output)

```json
{
  "id": "rid from Darwin",
  "scheduledDeparture": "07:42",
  "expectedDeparture": "07:49",
  "status": "late",
  "minutesLate": 7,
  "cancelled": false,
  "cancelReason": null,
  "delayReason": null,
  "platform": "3",
  "platformConfirmed": true,
  "finalDestination": "Paignton",
  "operator": "Great Western Railway",
  "operatorCode": "GW",
  "arrivalAtTabDestination": "08:19",
  "durationMinutes": 37,
  "stopsBetween": 4,
  "callingPoints": ["St Thomas", "Newton Abbot"],
  "section": "morning"
}
```

Response envelope:

```json
{
  "destination": { "crs": "PLY", "name": "Plymouth" },
  "generatedAt": "2026-10-12T07:31:05+01:00",
  "fromCache": false,
  "stale": false,
  "services": [ ... ],
  "messages": [ "NRCC station messages, if any" ]
}
```

## 6. Backend design (PHP)

### 6.1 `api/cors.php`

Included at the top of every endpoint, never inline. Requirements:

- Echo back the request `Origin` header when it matches `localhost` on any port (for Vite dev).
- In production, allow the configured production origin from `.config.json`.
- `Content-Type: application/json; charset=utf-8`
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Authorization, Content-Type`
- Return HTTP 200 and `exit()` for `OPTIONS` preflight.

Every endpoint begins:

```php
require_once __DIR__ . '/cors.php';   // always first
require_once __DIR__ . '/config.php';
```

### 6.2 `api/departures.php?dest=EXM`

1. Validate `dest` against the configured destination list. Reject anything else with 400.
2. Check the file cache. If the entry is younger than `cacheSeconds` (default 30), return it.
3. Call `GetDepBoardWithDetails/{crs}/{time}` (a GET request, `time` formatted `yyyyMMddTHHmmss`) with:
   - `crs` path segment = `EXD`
   - `filterCrs` = the destination CRS, `filterType` = `to`
   - `numRows` = 1 to 9 (the operation rejects 10 or more)
   - `timeWindow` = 120
   - `x-apikey` header carrying the API key
4. Normalise each service (section 5 object). Calculations:
   - **Status.** If `isCancelled`, status `cancelled`. If `etd` is "On time", status `ontime`. If `etd` is a time, compute minutes late against `std`, status `late` (or `ontime` if 0 or 1 minute, configurable). If `etd` is "Delayed", status `delayed` with no time. Overnight rollover must be handled (23:58 scheduled, 00:03 expected).
   - **Arrival at the tab destination and duration.** Find the destination in the service's `subsequentLocations`, take its `etd` if present, otherwise `std`. Duration = that time minus scheduled departure, with midnight rollover.
   - **Stops.** Count of calling points between Exeter St David's and the tab destination, excluding both ends.
   - **Final destination.** The last location of the service, which may be beyond the tab station.
   - **Platform.** Blank values become `null` and the frontend shows "TBC".
5. Sort by scheduled departure, tag each with `section`, store in cache, return.
6. On Darwin failure, return the last cached copy with `stale: true` and HTTP 200, or HTTP 502 with a clear JSON error if there is none.

### 6.3 Other files

- `config.php`: reads `.config.json` from the project root (next to `/api`), falling back to one directory above for hosts with a dedicated document root. The project root is checked first since on shared hosting the directory above may belong to a sibling site. Validates required keys and fails with a generic message (never echoing the API key).
- `ldbws.php`: a small REST client built on cURL. LDBSVWS is accessed as a plain JSON-over-HTTPS API product on the Rail Data Marketplace, authenticated with an `x-apikey` header — no SOAP extension or hand-built XML envelope involved.
- `cache.php`: JSON files in `api/cache/`, keyed by destination, atomic write. `api/cache/.htaccess` contains `Require all denied`.
- `health.php`: returns server time and cache age per destination, no secrets.

### 6.4 Rate limits and politeness

Five destinations polled every 30 seconds from one Pi is about 10 upstream calls a minute. Because the server caches, the call count stays the same however many browsers connect. Keep `cacheSeconds` at 30 or more.

## 7. Configuration (`.config.json`)

```json
{
  "darwin": {
    "apiKey": "REPLACE_WITH_API_KEY",
    "endpoint": "https://api1.raildata.org.uk/1010-live-departure-board---staff-version1_0/LDBSVWS/api/20220120"
  },
  "origin": { "crs": "EXD", "name": "Exeter St David's" },
  "destinations": [
    { "crs": "EXM", "name": "Exmouth" },
    { "crs": "PLY", "name": "Plymouth" },
    { "crs": "OKE", "name": "Okehampton" },
    { "crs": "BNP", "name": "Barnstaple" },
    { "crs": "TVP", "name": "Tiverton Parkway" }
  ],
  "windows": {
    "morningEnd": "09:00",
    "afternoonStart": "16:00",
    "afternoonLiveFrom": "14:00"
  },
  "cacheSeconds": 30,
  "allowedOrigins": ["https://YOUR-PRODUCTION-DOMAIN"],
  "devApiBase": "http://localhost/api",
  "ui": {
    "pollSeconds": 30,
    "rotateSeconds": 20,
    "pauseOnTouchSeconds": 60,
    "lateThresholdMinutes": 2
  }
}
```

The frontend must not read this file directly over HTTP — it stays blocked by `.htaccess` in both dev and production. The one exception is `devApiBase`, which `vite.config.js` reads from disk (Node, at `vite dev` start) and bakes into the dev bundle, since Vite's dev server and the PHP API run on different ports; production builds ignore it and default to a relative path. `api/destinations.php` returns the rest of the non-secret parts (destinations, windows, ui) over HTTP so the Pi display picks up changes without a rebuild. Verify the exact endpoint URL and API key process on the Rail Data Marketplace when subscribing, as these change from time to time.

## 8. Frontend design

### 8.1 Layout (designed for a 1080p wall display, readable from 5 metres)

- **Header:** "Trains from Exeter St David's", live clock (large), last updated time.
- **Tab bar:** five large tabs, active tab highlighted, thin progress bar under the bar showing time until the next rotation. A small "Paused" label when a user has interacted.
- **Body:** two stacked groups, "Before 09:00" and "After 16:00", each a responsive grid of cards.
- **Footer:** "Train information from National Rail" with a link to nationalrail.co.uk, plus a short licence line.

### 8.2 ServiceCard content (the requested fields)

| Field | Display |
|---|---|
| Time | Scheduled departure in large type, with arrival at this tab's destination in smaller type |
| Status | Badge: **On time** (green), **Late by X mins** (amber, red from 10 mins), **Due at HH:MM** (shows the expected time when it differs), **Cancelled** (red, with reason if given), **Delayed** (amber, no time yet) |
| Platform | Large platform number, "TBC" when unconfirmed |
| Final destination | "To Paignton" |
| Journey | Duration ("37 min"), stops ("4 stops" or "Direct" for non-stop), rail company name |

Status must never rely on colour alone. Pair every colour with text and an icon, and keep contrast at WCAG AA or better, as some students will have colour vision deficiency.

### 8.3 Behaviour

- `useDepartures(dest)` polls `GET /api/departures.php?dest=XXX` every `pollSeconds` via Axios, and keeps showing the last good data if a request fails, with a discreet "Data may be out of date" banner once data is older than 3 minutes.
- Poll only the visible tab, but prefetch the next tab a few seconds before rotation so switches are instant.
- `useRotation` advances tabs every `rotateSeconds`, pauses on touch or click for `pauseOnTouchSeconds`, and respects the Page Visibility API.
- Empty states: a friendly message such as "No direct trains in this window" (important for Okehampton, which has a far lighter service than the other lines).
- A section with no live data shows its placeholder text rather than being hidden, so students understand why.
- Cards use semantic HTML (`article`, `dl` for label and value pairs) and the tab bar uses proper ARIA tab roles.
- A subtle once-a-day full reload (for example 03:00) prevents long-running memory growth on the Pi.

### 8.4 CSS

One stylesheet. Use CSS custom properties for colours and spacing, a `clamp()` based type scale, and CSS grid with `auto-fill` for cards. Prefer a dark theme with high contrast for a wall display. Font: Trebuchet MS with a sensible sans-serif fallback.

## 9. Credentials and data licensing

1. Register on the Rail Data Marketplace (raildata.org.uk) and subscribe to the "Live Departure Board - Staff Version" API product. Registration is free.
2. The product is accessed over plain REST/JSON, not SOAP. Authentication is a single `x-apikey` header — check the product's "Try it" panel on the portal to confirm which of the issued credentials (typically the Consumer key) is the value that belongs there.
3. Store the API key in `.config.json` only.
4. Review the feed's terms and National Rail's attribution requirements on registration and follow them. The footer must credit National Rail as the source.

## 10. Security

- `.config.json` must never be served. The root `.htaccess` below includes an explicit deny block.
- The API key exists only on the server. No API key in the Vite build.
- `dest` is validated against a whitelist, never interpolated into anything unchecked.
- No user input is written to disk. Cache filenames come from the whitelist.
- Add `Header set X-Content-Type-Options "nosniff"` and a `Referrer-Policy` header in `.htaccess`.

### Root `.htaccess`

```apache
RewriteEngine On

# Never expose configuration or cache files to the browser.
<FilesMatch "^\.config(\.example)?\.json$">
    Require all denied
</FilesMatch>
RewriteRule ^\.config - [F,L]
RewriteRule ^api/cache/ - [F,L]

# Let real files/directories (including /api/*) resolve normally.
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Everything else falls back to index.html so React Router can handle
# client-side routes (e.g. a hard refresh).
RewriteRule ^ index.html [L]
```

Test after deployment by requesting `/.config.json` in a browser. It must return 403 or 404.

## 11. Local development

Per the standard Laragon setup:

- Apache and PHP on `http://localhost/` via Laragon, project placed so `/api` resolves there.
- Vite serves only the React frontend on its own port. No Vite proxy. The Axios base URL comes from `devApiBase` in `.config.json`, for example `http://localhost/exeter-departures/api` — `vite.config.js` reads it at dev-server start and injects it into the bundle. There is no separate `.env` file.
- If port 5173 is already in use, the dev server is already running. Use it for testing and do not kill it and start another.
- Provide a mock mode (`?mock=1` on the API, or a fixtures file) so the UI can be developed and tested without an API key, and to cover late, cancelled, no platform, non-stop, empty and midnight rollover cases.

## 12. Deployment

**Hostinger**

1. `npm run build`.
2. Upload the contents of `dist/` to the web root, plus the `/api` folder, `.htaccess` and `.config.json`.
3. Confirm the PHP `curl` extension is enabled, and that `api/cache/` is writable.
4. Visit `/api/health.php` and `/api/departures.php?dest=EXM` to verify before pointing the Pi at it.

**Raspberry Pi (kiosk)**

- Raspberry Pi OS with desktop, auto-login, and Chromium started in kiosk mode on boot, for example:
  `chromium-browser --kiosk --noerrdialogs --disable-infobars --incognito https://YOUR-DOMAIN/`
- Disable screen blanking (`xset s off`, `xset -dpms`, `xset s noblank`) or the equivalent in `raspi-config`.
- Enable NTP so the clock is right, and set the timezone to Europe/London so BST changes are handled.
- A cron or systemd timer to restart the browser each night is a cheap safeguard.

## 13. Testing and acceptance

Unit tests (Vitest) for `status.js` and `timeWindows.js`. PHP normaliser tests against a saved Darwin JSON fixture.

Acceptance checklist:

- [ ] Five tabs, names and order as in section 1, rotating automatically
- [ ] Each card shows time, status (on time, late by X, due at), platform, final destination, duration, stops and operator
- [ ] Morning and Afternoon sections behave as in section 5, with clear placeholders
- [ ] Cancelled, delayed, platform TBC and non-stop services render correctly
- [ ] Display stays usable when the API fails, and recovers without a reload
- [ ] `/.config.json` is not reachable in a browser
- [ ] National Rail credit and licence line visible on every screen
- [ ] No inline CSS, one stylesheet, JSDoc and PHPDoc throughout
- [ ] react-doctor installed and its findings addressed
- [ ] Readable from the back of the room at 1080p

## 14. Suggested build order for Claude Code

1. Scaffold with `npm create vite@latest`, install Axios, run `npx -y react-doctor@latest install`.
2. Build `api/cors.php`, `config.php`, `cache.php` and a mock-mode `departures.php`.
3. Build the frontend against mock data: cards, status logic, tabs, rotation.
4. Add the real Darwin client and normaliser, then test with live data.
5. Add stale data handling, placeholders and accessibility polish.
6. Write the README, `.htaccess`, deployment notes and Pi kiosk instructions.

## 15. Credits and licence

Train running information is provided by National Rail Enquiries (www.nationalrail.co.uk) via the Darwin Live Departure Boards feed. This project is not affiliated with or endorsed by National Rail.

Licence summary: this work is released under Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International. You may share and adapt it, provided you give appropriate credit, do not use it for commercial purposes, and distribute any adaptations under the same licence. Full terms: https://creativecommons.org/licenses/by-nc-sa/4.0/
