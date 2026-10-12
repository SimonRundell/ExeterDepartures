import SectionGroup from './SectionGroup.jsx'
import { buildSections } from '../utils/timeWindows.js'

/**
 * Tab panel for one destination: stale banner, station messages, sections.
 * @param {object} props - Component props.
 * @param {{crs: string, name: string}} props.destination - The destination.
 * @param {import('../hooks/useDepartures.js').DestinationResult|undefined} props.result - Latest result for this destination.
 * @param {Date} props.now - Current time.
 * @param {object} props.windows - Configured time windows.
 * @param {number} props.lateThreshold - Minutes late treated as on time.
 * @param {boolean} props.isStale - True when data is old enough to warn about.
 * @returns {JSX.Element} The panel.
 */
export default function DestinationPanel({ destination, result, now, windows, lateThreshold, isStale }) {
  const data = result?.data ?? null
  const loading = !data && !result?.error
  const sections = buildSections(data?.services ?? [], now, windows)

  return (
    <div role="tabpanel" id={`panel-${destination.crs}`} aria-labelledby={`tab-${destination.crs}`} className="panel">
      {isStale && (
        <p className="banner banner--warn" role="status">
          Data may be out of date
        </p>
      )}
      {!data && result?.error && (
        <p className="banner banner--warn" role="status">
          Train information is not available right now. Trying again shortly.
        </p>
      )}
      {data?.messages?.length > 0 && (
        <ul className="messages">
          {data.messages.map((m) => (
            <li key={m}>{m}</li>
          ))}
        </ul>
      )}
      {sections.map((section) => (
        <SectionGroup
          key={section.key}
          section={section}
          destinationName={destination.name}
          lateThreshold={lateThreshold}
          loading={loading}
        />
      ))}
    </div>
  )
}
