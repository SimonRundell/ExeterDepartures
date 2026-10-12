import { useEffect, useState } from 'react'
import { fetchConfig } from './api/client.js'
import DestinationPanel from './components/DestinationPanel.jsx'
import Footer from './components/Footer.jsx'
import TabBar from './components/TabBar.jsx'
import { useClock } from './hooks/useClock.js'
import { useDepartures } from './hooks/useDepartures.js'
import { useRotation } from './hooks/useRotation.js'
import './App.css'

/** Data older than this many milliseconds triggers the "out of date" banner. */
const STALE_AFTER_MS = 3 * 60 * 1000

/** Hour of the day (local) at which the page reloads itself to keep the Pi tidy. */
const RELOAD_HOUR = 3

/**
 * Format a time as HH:MM.
 * @param {Date} date - Date to format.
 * @returns {string} Time string.
 */
function formatTime(date) {
  return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

/**
 * Load the non-secret configuration, retrying every 10 seconds on failure.
 * @returns {{config: object|null, failed: boolean}} The config once loaded, and a failure flag.
 */
function useConfig() {
  const [config, setConfig] = useState(null)
  const [failed, setFailed] = useState(false)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    let cancelled = false
    fetchConfig().then(
      (data) => {
        if (cancelled) return
        setConfig(data)
        setFailed(false)
      },
      () => {
        if (!cancelled) setFailed(true)
      },
    )
    return () => {
      cancelled = true
    }
  }, [attempt])

  // After a failure, try again in 10 seconds.
  useEffect(() => {
    if (!failed) return undefined
    const timer = setTimeout(() => setAttempt((n) => n + 1), 10000)
    return () => clearTimeout(timer)
  }, [failed, attempt])

  return { config, failed }
}

/**
 * Schedule a full page reload at 03:00 local time.
 */
function useNightlyReload() {
  useEffect(() => {
    const next = new Date()
    next.setHours(RELOAD_HOUR, 0, 0, 0)
    if (next <= new Date()) next.setDate(next.getDate() + 1)
    const id = setTimeout(() => window.location.reload(), next - new Date())
    return () => clearTimeout(id)
  }, [])
}

/**
 * Root component: header, rotating tabs, active panel and footer.
 * @returns {JSX.Element} The departures board.
 */
export default function App() {
  const now = useClock()
  const { config, failed } = useConfig()
  useNightlyReload()

  const destinations = config?.destinations ?? []
  const ui = config?.ui ?? { pollSeconds: 30, rotateSeconds: 20, pauseOnTouchSeconds: 60, lateThresholdMinutes: 2 }

  const rotation = useRotation({
    count: destinations.length,
    rotateSeconds: ui.rotateSeconds,
    pauseSeconds: ui.pauseOnTouchSeconds,
  })
  const active = destinations[rotation.index]
  const next = destinations[(rotation.index + 1) % Math.max(destinations.length, 1)]
  const results = useDepartures(active?.crs, rotation.prefetchNext ? next?.crs : undefined, ui.pollSeconds)

  const result = active ? results[active.crs] : undefined
  const fetchedAt = result?.fetchedAt ?? null
  const isStale = Boolean(result?.data) && (result.data.stale || (fetchedAt !== null && now.getTime() - fetchedAt > STALE_AFTER_MS))

  return (
    <div className="board" onPointerDown={rotation.pause}>
      <header className="header">
        <h1 className="header__title">Trains from {config?.origin?.name ?? "Exeter St David's"}</h1>
        <p className="header__clock" aria-label="Current time">
          {now.toLocaleTimeString('en-GB')}
        </p>
      </header>

      {!config ? (
        <p className="banner banner--info" role="status">
          {failed ? 'Cannot reach the train service. Trying again shortly.' : 'Starting up…'}
        </p>
      ) : (
        <>
          <TabBar
            destinations={destinations}
            activeIndex={rotation.index}
            progress={rotation.progress}
            paused={rotation.paused}
            onSelect={rotation.select}
          />
          <main className="main">
            <DestinationPanel
              key={active.crs}
              destination={active}
              result={result}
              now={now}
              windows={config.windows}
              lateThreshold={ui.lateThresholdMinutes}
              isStale={isStale}
            />
          </main>
        </>
      )}

      <Footer lastUpdated={fetchedAt ? formatTime(new Date(fetchedAt)) : null} />
    </div>
  )
}
