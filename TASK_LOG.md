# Task log: reverse the morning section to show arrivals

**Status: complete (2026-10-14). Arrival-direction tests, DESIGN.md and browser/live verification all done.**

## The ask

> The Morning queries should be reversed so that it should be FROM trains ARRIVING at Exeter St
> David's which call or originate from the destinations. This will be useful for seeing if there
> are delays for students coming into College.

Morning section = students travelling **into** college (arrivals at EXD from each town).
Afternoon section = students travelling **home** (departures from EXD to each town) — unchanged.

## Key finding: no arrivals-board API access

Darwin's `GetArrBoardWithDetails` (and every arrivals-board operation name tried —
`GetArrDepBoardWithDetails`, `GetArrivalBoardWithDetails`, `GetArrivalDepartureBoardWithDetails`)
returns `HTTP 500 "Unable to route the message to a Target Endpoint"` on our current Rail Data
Marketplace subscription. Only the departure-board operations shown in the original product
screenshot (`GetDepBoardWithDetails`, `GetDepartureBoardByCRS`, `GetDepartureBoardByTIPLOC`) are
routable.

**Workaround (verified working against live data):** instead of asking "what arrives at EXD from
Exmouth", ask "what departs Exmouth heading to EXD" — i.e. call `GetDepBoardWithDetails` with
`crs=EXM`, `filterCrs=EXD`, `filterType=to`. The response's `subsequentLocations` array includes
an entry for EXD with its own `sta`/`eta`/`platform` — that's the arrival time/platform at college.
Same underlying Darwin data, queried from the other end, using an operation we already have.

Confirmed live via:
```php
$board = fetchDepartureBoard($config, "EXM", "EXD", 3);
// subsequentLocations entries with crs === "EXD" have sta/eta/platform populated correctly
```

## What's implemented (staged, not committed)

- **`api/normalise.php`** — added `normaliseArrivalService()` / `normaliseArrivalBoard()`, the
  arrival-direction counterparts to the existing `normaliseService()`/`normaliseBoard()`. Reuses
  the existing `computeStatus()`/`sectionFor()`/`isPublicStop()`/`reasonText()` helpers unchanged
  (they're generic enough already). New object shape for arrivals: `direction: 'arrival'`,
  `scheduledArrival`/`expectedArrival` (time at EXD), `departureFromOrigin` (time leaving the
  town), `originStation` (true origin, may be beyond the tab's town — mirrors how `finalDestination`
  already works for departures). Existing departure-direction objects got one additive field:
  `direction: 'departure'`.
- **`api/departures.php`** — now fetches **two** boards per request instead of one: the arrival
  board (town → EXD) and the departure board (EXD → town), each in its own try/catch so one
  failing doesn't take down the other, merged into a single `services` array, one cache entry per
  destination as before (now backed by 2 upstream calls instead of 1 — still modest volume, see
  DESIGN.md §6.4 rate-limit note, which has **not** been updated yet).
- **`api/mock.php`** — refactored into `mockCases()` (shared on-time/late/cancelled/delayed/no
  platform/non-stop test cases) + `mockDepartureServices()` + `mockArrivalServices()`, merged by
  `mockBoard()`. Arrival mock cases are anchored to ~06:30 regardless of wall-clock time so the
  morning section always has something to show under `?mock=1`.
- **`src/utils/status.js`** — generalised `getStatusInfo()` to read `scheduledArrival`/
  `expectedArrival` as a fallback when `scheduledDeparture`/`expectedDeparture` aren't present, so
  it works for both directions. Also fixed a real latent bug found while doing this: the backend
  stopped emitting a `'delayed'` status string after an earlier fix, which made
  `service.status === 'delayed'` **dead code** — the "known delay, no estimate yet" case was
  silently showing "On time" with the reason text never surfaced. Now detected via
  `service.delayReason` truthiness directly, independent of the status string.
- **`src/utils/timeWindows.js`** — `buildSections()` now filters morning to
  `section === 'morning' && direction === 'arrival'` and afternoon to
  `section === 'afternoon' && direction === 'departure'`, so a stray early departure-direction
  entry can't leak into the morning section (it technically could before this guard, since
  `sectionFor()` doesn't know about direction). The midday "Next trains" placeholder is now
  scoped to `direction === 'departure'` only (unchanged user-facing behaviour from before this
  feature). Section headings changed from "Before 09:00"/"After 16:00" to **"Arrivals before
  09:00"/"Departures after 16:00"** for clarity.
- **`src/components/ServiceCard.jsx`** — direction-aware rendering: big time is
  `scheduledArrival` (arrivals) or `scheduledDeparture` (departures); secondary small time swaps
  between "dep. HH:MM" and "arr. HH:MM"; the "To"/"From" fact label and value swap accordingly.
  Preserves the existing calling-at list and operator-logo work (`b163254`) unchanged — arrival
  cards just don't populate `callingAt` (not built for that direction; low priority, see below).
- **Tests updated to match**: `src/utils/timeWindows.test.js` fixtures now carry a `direction`
  field; `src/utils/status.test.js` has a rewritten "delayed has no time" case plus a new case for
  the arrival field names. `npm test` passes (11/11) and `npm run lint` is clean as of the last
  run. **`tests/normalise_test.php` was NOT updated** — it still only exercises the departure
  direction; the new `normaliseArrivalService()`/`normaliseArrivalBoard()` have no PHP test
  coverage yet (task #20, not started).

## Completed after the pause

- `tests/normalise_test.php` now covers the arrival direction (new `api/fixtures/board_arrivals.json`): lateness across midnight, `departureFromOrigin`, `originStation`, cancelled, no platform, delay reason, sorting. 39 checks pass.
- `DESIGN.md` updated: sections, both service shapes, arrival query, 2x upstream calls.
- Verified in the browser with `?mock=1` (morning shows arrival cards) and against live Darwin (`dest=PLY` returned 5 arrivals and 5 departures).
- Still open: a `callingAt` list for arrival cards (not requested).

## Original not-done list (now resolved except the last item)

1. **`tests/normalise_test.php`** needs cases for the arrival direction (new fixture data or a
   second fixture file, covering: arrival status/lateness from `sta`/`eta`, `departureFromOrigin`,
   `originStation`, midnight rollover on the arrival side, cancelled arrival, no-platform arrival).
2. **`DESIGN.md`** needs updating: the data model section still only documents the departure
   direction; §6.2 needs a new subsection for the arrival-direction query/calculation; §6.4 (rate
   limits) needs the upstream-call-count note updated (now 2× per destination); the normalised
   service object example should probably show both shapes.
3. **`README.md`** — not reviewed for staleness against this change (likely fine, but not checked).
4. **Browser verification was interrupted mid-way.** `npm test`/`php tests/normalise_test.php`
   (old coverage only) pass, and a raw PHP smoke test against **live Darwin data** confirmed
   `normaliseArrivalBoard()` produces correct arrival times/platforms/status. `?mock=1` was
   smoke-tested via curl (looked correct — arrivals tagged `morning`, departures spread across
   sections). **Not yet confirmed in an actual rendered browser** that the morning section shows
   arrival cards with the right labels — I hit a `localhost` vs `127.0.0.1` DNS resolution quirk
   specific to the Claude Code preview browser (not a real app bug — `127.0.0.1` worked fine,
   `localhost` didn't resolve for `fetch()` in that sandboxed browser) and got interrupted before
   finishing the visual check. Worth a plain real-browser check at
   `http://localhost:5173/?mock=1` with a local PHP server to be sure.
5. **`callingAt` list for arrival cards** — departure cards show a "Calling at" list (stops still
   to come); arrival cards currently don't show an equivalent "called at" list of stops already
   passed. Not requested, low priority, flagged here in case it's wanted later.
6. **Not committed.** Everything above is staged (`git status` shows `api/departures.php`,
   `api/mock.php`, `api/normalise.php`, `src/components/ServiceCard.jsx`, `src/utils/status.js`,
   `src/utils/status.test.js`, `src/utils/timeWindows.js`, `src/utils/timeWindows.test.js`, plus
   `.claude/launch.json` — a Vite dev-server preview config, harmless but you may not want it
   committed).

## How to resume

1. `git diff --cached` to review what's staged.
2. Start the API locally (`php -S 127.0.0.1:8081 -t .`, note **not** `localhost` if testing inside
   a sandboxed preview browser) and `npm run dev`, point `.config.json`'s `devApiBase` at the
   local API, visit `/?mock=1`.
3. Check the morning section shows arrival-direction cards ("Arrives" label, platform/time at
   EXD, "From {town}"), afternoon still shows departure-direction cards unchanged.
4. Finish task #20 (tests + DESIGN.md), then re-verify against live data
   (`?dest=EXM` without `&mock=1`) before committing.
5. `.config.json`'s `devApiBase` has been reverted back to
   `https://exeterdepartures.toolsforteaching.co.uk/api` — don't forget to flip it back to a
   local value again while testing, and back to production before deploying.
