import { describe, expect, it } from 'vitest'
import { buildSections, getMode } from './timeWindows.js'

const windows = { morningEnd: '09:00', afternoonStart: '16:00', afternoonLiveFrom: '14:00' }
const at = (h, m = 0) => new Date(2026, 9, 12, h, m)
const services = [
  { id: 'a', section: 'morning', direction: 'arrival' },
  { id: 'b', section: 'other', direction: 'departure' },
  { id: 'c', section: 'afternoon', direction: 'departure' },
]

describe('getMode', () => {
  it('switches at the configured boundaries', () => {
    expect(getMode(at(8, 59), windows)).toBe('morning')
    expect(getMode(at(9), windows)).toBe('midday')
    expect(getMode(at(13, 59), windows)).toBe('midday')
    expect(getMode(at(14), windows)).toBe('afternoon')
    expect(getMode(at(0, 5), windows)).toBe('morning')
  })
})

describe('buildSections', () => {
  it('morning: live morning, afternoon placeholder', () => {
    const [m, a] = buildSections(services, at(7), windows)
    expect(m.services.map((s) => s.id)).toEqual(['a'])
    expect(a.placeholder).toMatch(/14:00/)
  })
  it('midday: two placeholders and a next trains section', () => {
    const s = buildSections(services, at(11), windows)
    expect(s).toHaveLength(3)
    expect(s[0].placeholder).not.toBeNull()
    expect(s[1].placeholder).not.toBeNull()
    expect(s[2].key).toBe('next')
  })
  it('afternoon: only services from 16:00', () => {
    const [, a] = buildSections(services, at(15), windows)
    expect(a.services.map((s) => s.id)).toEqual(['c'])
  })
})
