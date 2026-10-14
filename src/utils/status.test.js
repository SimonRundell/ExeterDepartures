import { describe, expect, it } from 'vitest'
import { getStatusInfo } from './status.js'

const base = { status: 'ontime', cancelled: false, minutesLate: 0, scheduledDeparture: '07:42', expectedDeparture: '07:42' }

describe('getStatusInfo', () => {
  it('on time', () => expect(getStatusInfo(base).kind).toBe('ontime'))
  it('within threshold but different time shows due at', () => {
    const info = getStatusInfo({ ...base, minutesLate: 2, expectedDeparture: '07:44' }, 2)
    expect(info).toMatchObject({ kind: 'due', label: 'Due at 07:44' })
  })
  it('late is amber then red from 10 mins', () => {
    expect(getStatusInfo({ ...base, minutesLate: 7, expectedDeparture: '07:49' }).kind).toBe('late')
    expect(getStatusInfo({ ...base, minutesLate: 10, expectedDeparture: '07:52' }).kind).toBe('verylate')
  })
  it('late label text', () => {
    expect(getStatusInfo({ ...base, minutesLate: 7, expectedDeparture: '07:49' }).label).toBe('Late by 7 mins')
  })
  it('cancelled carries reason', () => {
    const info = getStatusInfo({ ...base, cancelled: true, status: 'cancelled', cancelReason: 'Crew' })
    expect(info).toMatchObject({ kind: 'cancelled', detail: 'Crew' })
  })
  it('delayed has no time', () => {
    const info = getStatusInfo({ ...base, delayReason: 'Signalling fault' })
    expect(info).toMatchObject({ kind: 'delayed', label: 'Delayed', detail: 'Signalling fault' })
  })
  it('arrival direction uses scheduledArrival/expectedArrival', () => {
    const arrival = {
      status: 'ontime',
      cancelled: false,
      minutesLate: 7,
      scheduledArrival: '08:19',
      expectedArrival: '08:26',
    }
    expect(getStatusInfo(arrival)).toMatchObject({ kind: 'late', label: 'Late by 7 mins', detail: 'Due at 08:26' })
  })
})
