/**
 * Minutes late from which a delay is shown in red rather than amber.
 * @type {number}
 */
export const SEVERE_LATE_MINUTES = 10

/**
 * @typedef {object} StatusInfo
 * @property {'ontime'|'due'|'late'|'verylate'|'cancelled'|'delayed'} kind - Drives the badge colour class.
 * @property {string} label - Text shown in the badge.
 * @property {string} icon - Symbol paired with the colour so status never relies on colour alone.
 * @property {string|null} detail - Optional extra line, such as a cancellation reason.
 */

/**
 * Work out how a service's status should be displayed. Works for both
 * directions: departures (scheduledDeparture/expectedDeparture) and
 * arrivals (scheduledArrival/expectedArrival).
 * @param {object} service - Normalised service from the API.
 * @param {number} [lateThreshold=2] - Minutes late at or below which a train counts as on time.
 * @returns {StatusInfo} Display information for the badge.
 */
export function getStatusInfo(service, lateThreshold = 2) {
  if (service.cancelled || service.status === 'cancelled') {
    return { kind: 'cancelled', label: 'Cancelled', icon: '✕', detail: service.cancelReason ?? null }
  }

  const scheduled = service.scheduledDeparture ?? service.scheduledArrival
  const expected = service.expectedDeparture ?? service.expectedArrival
  const late = service.minutesLate ?? 0

  if (late <= lateThreshold && service.delayReason) {
    // Darwin has flagged a delay but hasn't published a revised time yet.
    return { kind: 'delayed', label: 'Delayed', icon: '…', detail: service.delayReason }
  }
  if (late > lateThreshold) {
    const kind = late >= SEVERE_LATE_MINUTES ? 'verylate' : 'late'
    return {
      kind,
      label: `Late by ${late} min${late === 1 ? '' : 's'}`,
      icon: '!',
      detail: `Due at ${expected}`,
    }
  }
  if (expected && expected !== scheduled) {
    return { kind: 'due', label: `Due at ${expected}`, icon: '◷', detail: null }
  }
  return { kind: 'ontime', label: 'On time', icon: '✓', detail: null }
}
