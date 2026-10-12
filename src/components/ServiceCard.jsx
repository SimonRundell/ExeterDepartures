import StatusBadge from './StatusBadge.jsx'
import { getStatusInfo } from '../utils/status.js'

/**
 * Describe the number of intermediate stops.
 * @param {number|null} stops - Stops between origin and the tab destination.
 * @returns {string|null} "Direct", "1 stop", "4 stops", or null when unknown.
 */
function describeStops(stops) {
  if (stops === null || stops === undefined) return null
  if (stops === 0) return 'Direct'
  return `${stops} stop${stops === 1 ? '' : 's'}`
}

/**
 * One train service.
 * @param {object} props - Component props.
 * @param {object} props.service - Normalised service from the API.
 * @param {string} props.destinationName - Name of the tab destination.
 * @param {number} props.lateThreshold - Minutes late treated as on time.
 * @returns {JSX.Element} The card.
 */
export default function ServiceCard({ service, destinationName, lateThreshold }) {
  const info = getStatusInfo(service, lateThreshold)
  const journey = [
    service.durationMinutes != null ? `${service.durationMinutes} min` : null,
    describeStops(service.stopsBetween),
  ].filter(Boolean)

  return (
    <article className={`card card--${info.kind}`}>
      <header className="card__top">
        <p className="card__time">
          <span className="visually-hidden">Departs </span>
          <span className={info.kind === 'cancelled' ? 'is-struck' : undefined}>{service.scheduledDeparture}</span>
        </p>
        {service.arrivalAtTabDestination && (
          <p className="card__arrival">
            <span className="visually-hidden">Arrives {destinationName} </span>
            <span aria-hidden="true">arr. </span>
            {service.arrivalAtTabDestination}
          </p>
        )}
      </header>

      <StatusBadge info={info} />
      {info.detail && <p className="card__detail">{info.detail}</p>}

      <dl className="card__facts">
        <div className="card__fact card__fact--platform">
          <dt>Platform</dt>
          <dd className="card__platform">{service.platform ?? 'TBC'}</dd>
        </div>
        <div className="card__fact card__fact--to">
          <dt>To</dt>
          <dd>{service.finalDestination}</dd>
        </div>
        <div className="card__fact card__fact--journey">
          <dt>Journey</dt>
          <dd>{journey.length ? journey.join(' · ') : 'Not available'}</dd>
        </div>
        <div className="card__fact card__fact--operator">
          <dt>Operator</dt>
          <dd>{service.operator}</dd>
        </div>
      </dl>
    </article>
  )
}
