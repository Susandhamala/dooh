/** YYYY-MM-DD for today + n days, in local time. */
export function isoDay(offset = 0): string {
  const d = new Date()
  d.setDate(d.getDate() + offset)
  return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-')
}
