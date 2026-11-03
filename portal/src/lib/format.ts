/** Whole rupees with a non-breaking space, the same as rentallnepal's formatPrice. */
export function formatPrice(value: string | number | null | undefined, unit: string | null = null): string {
  if (value === null || value === undefined || value === '') return 'Price on request'
  return 'रू ' + Math.round(Number(value)).toLocaleString('en-IN') + (unit ? `/${unit}` : '')
}

/** "YYYY-MM-DD" as a local calendar date (no timezone shift). */
function parseDay(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1)
}

/** 12 Nov 2026 */
export function formatDate(iso: string): string {
  return parseDay(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

/** 12 – 18 Nov 2026, 28 Nov – 3 Dec 2026, or 30 Dec 2026 – 2 Jan 2027 */
export function formatRange(start: string, end: string): string {
  const a = parseDay(start)
  const b = parseDay(end)
  if (start === end) return formatDate(start)
  const sameYear = a.getFullYear() === b.getFullYear()
  const sameMonth = sameYear && a.getMonth() === b.getMonth()
  const from = a.toLocaleDateString('en-GB', {
    day: 'numeric',
    ...(sameMonth ? {} : { month: 'short' }),
    ...(sameYear ? {} : { year: 'numeric' }),
  })
  return `${from} – ${formatDate(end)}`
}

/** Days in an inclusive date range; 0 when the range is backwards. */
export function daysInRange(start: string, end: string): number {
  const ms = parseDay(end).getTime() - parseDay(start).getTime()
  return ms < 0 ? 0 : Math.round(ms / 86_400_000) + 1
}

export function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

/** 12 Nov, 14:05 */
export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

/** 8 → "08:00" */
export function formatHour(h: number): string {
  return `${String(h % 24).padStart(2, '0')}:00`
}

/** "08:00–10:00" */
export function formatHours(start: number, end: number): string {
  return `${formatHour(start)}–${end === 24 ? '24:00' : formatHour(end)}`
}

/** Rupees with paisa when there are any: रू 2, रू 2.50, रू 1,234.50 */
export function formatRupees(value: string | number): string {
  const n = Number(value)
  const opts = Number.isInteger(n) ? {} : { minimumFractionDigits: 2, maximumFractionDigits: 2 }
  return 'रू ' + n.toLocaleString('en-IN', opts)
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

/** What a booking costs: the same sum the API uses. */
export function quote(o: { days: number; startHour: number; endHour: number; playsPerHour: number; pricePerPlay: string | number }) {
  const hours = Math.max(0, o.endHour - o.startHour)
  const playsPerDay = hours * o.playsPerHour
  const plays = Math.max(0, o.days) * playsPerDay
  const paisa = Math.round(Number(o.pricePerPlay) * 100)
  return { hours, playsPerDay, plays, total: (plays * paisa) / 100 }
}

export type Tone = 'neutral' | 'plum' | 'success' | 'warning' | 'danger' | 'teal'

/** Every status the API sends, as words and a colour. Status is never shown by colour alone. */
const STATUSES: Record<string, { label: string; tone: Tone }> = {
  // Bookings
  draft: { label: 'Draft', tone: 'neutral' },
  checking: { label: 'Ad in review', tone: 'warning' },
  awaiting_owner: { label: 'Awaiting owner', tone: 'warning' },
  approved: { label: 'Approved', tone: 'success' },
  scheduled: { label: 'Scheduled', tone: 'teal' },
  live: { label: 'Live', tone: 'success' },
  completed: { label: 'Completed', tone: 'neutral' },
  rejected: { label: 'Rejected', tone: 'danger' },
  cancelled: { label: 'Cancelled', tone: 'neutral' },
  // Ads and documents
  processing: { label: 'Checking file', tone: 'plum' },
  failed: { label: 'File problem', tone: 'danger' },
  in_review: { label: 'In review', tone: 'warning' },
  pending: { label: 'Being checked', tone: 'warning' },
  // Screens and organisations
  active: { label: 'Active', tone: 'success' },
  paused: { label: 'Paused', tone: 'neutral' },
  suspended: { label: 'Suspended', tone: 'danger' },
}

export function statusMeta(status: string): { label: string; tone: Tone } {
  const known = STATUSES[status]
  if (known) return known
  const words = status.replace(/_/g, ' ')
  return { label: words.charAt(0).toUpperCase() + words.slice(1), tone: 'neutral' }
}
