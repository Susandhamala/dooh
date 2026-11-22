import { describe, expect, it } from 'vitest'
import { nepalDay, nepalNow, slotsFor } from './hours'

describe('Nepal time', () => {
  it('reads the date and minute of the day in Kathmandu (UTC+5:45)', () => {
    // 2027-01-10 20:30 UTC is 02:15 on the 11th in Kathmandu.
    const at = new Date(Date.UTC(2027, 0, 10, 20, 30))
    expect(nepalNow(at)).toEqual({ day: '2027-01-11', hour: 2, minute: 135 })
    expect(nepalDay(0, at)).toBe('2027-01-11')
    expect(nepalDay(21, at)).toBe('2027-02-01')
  })
})

describe('longer ads', () => {
  it('takes as many slots as the video needs', () => {
    expect(slotsFor({ kind: 'image', duration_s: null }, 10)).toBe(1)
    expect(slotsFor({ kind: 'video', duration_s: 10.3 }, 10)).toBe(1)
    expect(slotsFor({ kind: 'video', duration_s: 34 }, 10)).toBe(4)
  })
})
