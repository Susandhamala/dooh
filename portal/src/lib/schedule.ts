/**
 * When an ad plays. Mirrors api/app/Support/PlaySchedule.php.
 *
 * A screen runs a loop of `loop` seconds that restarts at the top of every hour (Nepal time). An ad
 * plays once (or as many times as booked) in every loop that starts inside its window
 * [startMinute, endMinute). The screen packs the ads in each loop back to back, so there are no
 * gaps; it decides the exact second.
 */
export function loopStarts(loop: number, startMinute: number, endMinute: number): number[] {
  const from = startMinute * 60
  const to = endMinute * 60
  const loops = Math.floor(3600 / loop)
  const times: number[] = []
  for (let h = Math.floor(startMinute / 60); h * 3600 < to; h++) {
    for (let k = 0; k < loops; k++) {
      const t = h * 3600 + k * loop
      if (t >= from && t < to) times.push(t)
    }
  }
  return times
}

/** What a booking costs: every play, times the slots the ad takes, at the screen's price. Same sum as the API. */
export function quote(o: { days: number; loopsPerDay: number; repeats?: number; pricePerPlay: string | number; slots?: number }) {
  const plays = Math.max(0, o.days) * o.loopsPerDay * (o.repeats ?? 1)
  const paisa = Math.round(Number(o.pricePerPlay) * 100) * (o.slots ?? 1)
  return { plays, perPlay: paisa / 100, total: (plays * paisa) / 100 }
}

/** Minutes after midnight for "HH:MM" ("24:00" allowed). */
export function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}

/** "HH:MM" for minutes after midnight. */
export function toTime(minutes: number): string {
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
}

/** "HH:MM:SS" for seconds after midnight. */
export function clock(seconds: number): string {
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`
}

/** "m:ss" for a position inside the loop. */
export function loopTime(seconds: number): string {
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

/** "2 min", "90 s": a loop length people can read. */
export function duration(seconds: number): string {
  return seconds % 60 === 0 ? `${seconds / 60} min` : `${seconds} s`
}
