/** Today's date, hour and minute of the day in Nepal. */
export function nepalNow(at = new Date()): { day: string; hour: number; minute: number } {
  const p = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Kathmandu',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  })
    .formatToParts(at)
    .reduce<Record<string, string>>((acc, x) => ((acc[x.type] = x.value), acc), {})
  const hour = Number(p.hour)
  return { day: `${p.year}-${p.month}-${p.day}`, hour, minute: hour * 60 + Number(p.minute) }
}

/** YYYY-MM-DD in Nepal, `offset` days from today. */
export function nepalDay(offset = 0, at = new Date()): string {
  const [y, m, d] = nepalNow(at).day.split('-').map(Number)
  const t = new Date(Date.UTC(y!, m! - 1, d! + offset))
  return t.toISOString().slice(0, 10)
}

/**
 * Back-to-back slots an ad takes on a screen: one for an image, enough to cover a video otherwise
 * (half a second of slack, like the API). A 34 s video on 10 s slots takes 4.
 */
export function slotsFor(ad: { kind: string; duration_s: number | null }, spotSeconds: number): number {
  if (ad.kind !== 'video') return 1
  return Math.max(1, Math.ceil(Math.max(0, (ad.duration_s ?? 0) - 0.5) / spotSeconds))
}
