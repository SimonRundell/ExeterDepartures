/**
 * Convert "HH:MM" to minutes since midnight.
 * @param {string} hhmm - Time string.
 * @returns {number} Minutes since midnight.
 */
export function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

/**
 * Which phase of the day the display is in.
 * - morning: before morningEnd, morning section is live.
 * - midday: between morningEnd and afternoonLiveFrom, nothing in the live window.
 * - afternoon: from afternoonLiveFrom, afternoon section is live.
 * @param {Date} now - Current time.
 * @param {{morningEnd: string, afternoonLiveFrom: string}} windows - Configured windows.
 * @returns {'morning'|'midday'|'afternoon'} The current mode.
 */
export function getMode(now, windows) {
  const minutes = now.getHours() * 60 + now.getMinutes()
  if (minutes < toMinutes(windows.morningEnd)) return 'morning'
  if (minutes < toMinutes(windows.afternoonLiveFrom)) return 'midday'
  return 'afternoon'
}

/**
 * @typedef {object} SectionSpec
 * @property {string} key - Stable key.
 * @property {string} title - Heading text.
 * @property {object[]} services - Services to show (empty when a placeholder applies).
 * @property {string|null} placeholder - Message shown when the section is not live.
 * @property {string} emptyMessage - Message shown when the section is live but empty.
 */

const EMPTY = 'No direct trains in this window'

/**
 * Split services into the sections to display for the current time.
 * @param {object[]} services - Normalised services from the API.
 * @param {Date} now - Current time.
 * @param {{morningEnd: string, afternoonStart: string, afternoonLiveFrom: string}} windows - Configured windows.
 * @returns {SectionSpec[]} Sections in display order.
 */
export function buildSections(services, now, windows) {
  const mode = getMode(now, windows)
  const morning = { key: 'morning', title: `Arrivals before ${windows.morningEnd}`, services: [], placeholder: null, emptyMessage: EMPTY }
  const afternoon = { key: 'afternoon', title: `Departures after ${windows.afternoonStart}`, services: [], placeholder: null, emptyMessage: EMPTY }
  const sections = [morning, afternoon]

  if (mode === 'morning') {
    morning.services = services.filter((s) => s.section === 'morning' && s.direction === 'arrival')
    afternoon.placeholder = `Afternoon services appear from ${windows.afternoonLiveFrom}`
  } else if (mode === 'afternoon') {
    afternoon.services = services.filter((s) => s.section === 'afternoon' && s.direction === 'departure')
    morning.placeholder = 'Morning services appear again in the early hours'
  } else {
    morning.placeholder = 'Morning services appear again in the early hours'
    afternoon.placeholder = `Afternoon services appear from ${windows.afternoonLiveFrom}`
    const next = services.filter((s) => s.direction === 'departure').slice(0, 3)
    sections.push({ key: 'next', title: 'Next trains', services: next, placeholder: null, emptyMessage: EMPTY })
  }
  return sections
}
