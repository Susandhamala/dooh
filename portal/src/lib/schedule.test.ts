import { describe, expect, it } from 'vitest'
import { clock, loopStarts, loopTime, quote, toMinutes, toTime } from './schedule'

describe('schedule', () => {
  it('lists every loop that starts inside the window, like the API', () => {
    const t = loopStarts(90, 8 * 60, 9 * 60)
    expect(t).toHaveLength(40)
    expect([clock(t[1]!), clock(t.at(-1)!)]).toEqual(['08:01:30', '08:58:30'])
    // 08:15–08:45 on a 120 s loop: 08:16 … 08:44 → 15 loops.
    expect(loopStarts(120, 495, 525)).toHaveLength(15)
    // A loop that doesn't divide the hour leaves a gap, then restarts on the hour.
    expect(clock(loopStarts(70, 480, 600)[51]!)).toBe('09:00:00')
  })

  it('prices plays × slots × price, in exact paisa', () => {
    expect(quote({ days: 7, loopsPerDay: 60, pricePerPlay: '4.50' })).toEqual({ plays: 420, perPlay: 4.5, total: 1890 })
    // 34 s video (4 slots), twice a loop
    expect(quote({ days: 3, loopsPerDay: 60, repeats: 2, pricePerPlay: '2.00', slots: 4 })).toEqual({ plays: 360, perPlay: 8, total: 2880 })
    expect(quote({ days: 7, loopsPerDay: 1, pricePerPlay: '0.10' }).total).toBe(0.7)
  })

  it('reads and writes times', () => {
    expect(toMinutes('08:15')).toBe(495)
    expect(toTime(1440)).toBe('24:00')
    expect(loopTime(90)).toBe('1:30')
  })
})
