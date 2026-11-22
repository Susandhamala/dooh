import { describe, expect, it } from 'vitest'
import { daysInRange, formatDate, formatPrice, formatRange, statusMeta } from './format'

describe('format', () => {
  it('formats prices in whole rupees that never wrap', () => {
    expect(formatPrice('1250.50')).toBe('रू 1,251')
    expect(formatPrice(150000, 'day')).toBe('रू 1,50,000/day')
    expect(formatPrice(null)).toBe('Price on request')
  })

  it('formats dates and ranges without timezone shifts', () => {
    expect(formatDate('2026-11-02')).toBe('2 Nov 2026')
    expect(formatRange('2026-11-02', '2026-11-08')).toBe('2 – 8 Nov 2026')
    expect(formatRange('2026-11-28', '2026-12-03')).toBe('28 Nov – 3 Dec 2026')
    expect(formatRange('2026-12-30', '2027-01-02')).toBe('30 Dec 2026 – 2 Jan 2027')
    expect(formatRange('2026-11-02', '2026-11-02')).toBe('2 Nov 2026')
  })

  it('counts inclusive days', () => {
    expect(daysInRange('2026-11-02', '2026-11-08')).toBe(7)
    expect(daysInRange('2026-11-02', '2026-11-02')).toBe(1)
    expect(daysInRange('2026-11-08', '2026-11-02')).toBe(0)
  })

  it('labels every status in words', () => {
    expect(statusMeta('awaiting_owner')).toEqual({ label: 'Awaiting owner', tone: 'warning' })
    expect(statusMeta('some_new_state')).toEqual({ label: 'Some new state', tone: 'neutral' })
  })
})
