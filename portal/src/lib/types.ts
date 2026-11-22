export type Role = 'advertiser' | 'owner' | 'admin'
export type DocumentStatus = 'none' | 'pending' | 'approved' | 'rejected'

export interface User {
  id: string
  name: string
  email: string | null
  phone: string | null
  phone_masked: string | null
  email_verified: boolean
  phone_verified: boolean
  mfa_enabled: boolean
  roles: Role[]
  terms_accepted: boolean
  document_status: DocumentStatus
}

export interface Session {
  token: string
  expires_in: number
  mfa_verified: boolean
  mfa_required: boolean
  user: User
}

export interface Organisation {
  id: string
  name: string
  type: 'advertiser' | 'owner'
  verified: boolean
  status: string
  my_role?: 'admin' | 'editor' | 'viewer'
  created_at?: string
}

export interface Screen {
  id: string
  name: string
  description: string | null
  address: string | null
  lat: number
  lng: number
  width_px: number
  height_px: number
  orientation: 'landscape' | 'portrait'
  opens_at: string
  closes_at: string
  loop_seconds: number
  spot_seconds: number
  slots_per_loop: number
  price_per_play: string
  plays_per_hour: number
  bookable_hours: [number, number]
  audience_notes: string | null
  content_rules?: string[]
  status: 'draft' | 'active' | 'paused'
  /** Owner views only: the connected player. */
  device?: Device | null
}

/** A screen's player (Raspberry Pi), known by its client certificate. */
export interface Device {
  id: string
  online: boolean
  enrolled_at: string
  last_seen_at: string | null
  last_ip: string | null
  player_version: string | null
  cert_expires_at: string
}

export interface Availability {
  slots_per_loop: number
  plays_per_hour: number
  hours: number[]
  /** days[date][i] = free slots in hours[i] */
  days: Record<string, number[]>
}

export type CreativeStatus = 'processing' | 'failed' | 'in_review' | 'approved' | 'rejected'

export interface Creative {
  id: string
  name: string
  category: string
  kind: 'image' | 'video'
  width_px: number
  height_px: number
  orientation: 'landscape' | 'portrait'
  duration_s: number | null
  size_bytes: number
  status: CreativeStatus
  check: { errors: string[]; notes: string[] }
  reject_reason: string | null
  reject_label: string | null
  review_note: string | null
  reviewed_at: string | null
  media_url: string | null
  thumb_url: string | null
  created_at: string
  // Review team only
  organisation?: { id: string; name: string }
  uploader?: { name: string; phone: string | null; document_status: DocumentStatus } | null
  declared_at?: string
  sha256?: string | null
  can_approve?: boolean
}

export interface Resolution {
  width: number
  height: number
  label: string
}

/** When a booked ad runs. The screen packs each loop, so it sets the exact second. */
export interface BookingSchedule {
  loop_seconds: number
  spot_seconds: number
  slot_count: number
  /** Times the ad plays in every loop. */
  repeats: number
  /** How long one play takes (its slots). */
  length_s: number
  plays_per_day: number
  /** "HH:MM:SS" of the first loops it plays in each day, and the last one. */
  first_loops: string[]
  last_loop: string | null
}

export interface Booking {
  id: string
  screen?: { id: string; name: string; address: string | null; loop_seconds: number; spot_seconds: number }
  creative?: { id: string; name: string; kind: string; status: CreativeStatus } | null
  start_date: string
  end_date: string
  /** Nepal time, "HH:MM"; the ad plays in every loop that starts inside [start_time, end_time). */
  start_time: string
  end_time: string
  /** When the ad runs, to the second. */
  schedule?: BookingSchedule
  status: string
  /** Back-to-back slots the ad takes in the loop; price_per_play covers all of them. */
  slot_count?: number
  /** Times the ad plays in every loop. */
  repeats?: number
  price_per_play: string
  plays_total: number
  plays_done: number
  /** Paid in full when booked. */
  price: string
  /** What cancelling now would refund: the plays still to come. */
  refundable: string
  created_at: string
  /** Admin panel only. */
  organisation?: { id: string; name: string }
}

export interface AdminUser {
  id: string
  name: string
  phone: string | null
  email: string | null
  status: 'active' | 'suspended'
  roles: Role[]
  document_status: DocumentStatus
  organisations: { id: string; name: string; type: 'advertiser' | 'owner' }[]
  balance: string
  bookings: number
  created_at: string
}

export interface AdminOverview {
  users: { total: number; new_week: number; suspended: number }
  documents: Record<'pending' | 'approved' | 'rejected', number>
  ads: Record<'processing' | 'failed' | 'in_review' | 'approved' | 'rejected', number>
  bookings: Record<'scheduled' | 'live' | 'completed' | 'cancelled' | 'rejected', number>
  screens: Record<'draft' | 'active' | 'paused', number>
  money: { loaded: string; paid: string; balances: string }
  daily: { date: string; amount: string }[]
  recent_bookings: Booking[]
  recent_users: AdminUser[]
}

export interface Wallet {
  balance: string
  reserved: string
  available: string
  min_topup: number
  max_topup: number
  gateways: string[]
}

export interface WalletTransaction {
  id: string
  type: 'topup' | 'charge' | 'refund' | 'hold' | 'release'
  amount: string
  balance_after: string
  available_after: string
  description: string
  reference: string | null
  created_at: string
}

export interface IdentityDocument {
  id: string
  type: string
  type_label: string
  number: string
  status: 'pending' | 'approved' | 'rejected'
  review_note: string | null
  submitted_at: string
  reviewed_at: string | null
  // Review team only
  user?: { id: string; name: string; phone: string | null; email: string | null }
  mime?: string
  file_url?: string | null
}

export interface Terms {
  version: string
  title: string
  intro: string
  prohibited: { title: string; body: string }[]
  rules: string[]
  legal: string
  declaration: string
  sha256: string
}

export const CONTENT_RULES: Record<string, string> = {
  no_alcohol: 'No alcohol',
  no_tobacco: 'No tobacco',
  no_political: 'No political ads',
  no_gambling: 'No gambling',
  no_religious: 'No religious ads',
  no_adult: 'No adult content',
}

export const CATEGORIES: Record<string, string> = {
  retail: 'Shops and retail',
  food_drink: 'Food and drink',
  services: 'Services',
  education: 'Education',
  health: 'Health',
  real_estate: 'Real estate',
  automotive: 'Vehicles',
  finance: 'Banking and finance',
  events: 'Events',
  travel: 'Travel and tourism',
  technology: 'Technology',
  public_service: 'Public service',
  alcohol: 'Alcohol',
  tobacco: 'Tobacco',
  political: 'Political',
  religious: 'Religious',
  gambling: 'Gambling',
  other: 'Other',
}

export const DOCUMENT_TYPES: Record<string, string> = {
  citizenship: 'Citizenship certificate',
  national_id: 'National ID card',
  passport: 'Passport',
  driving_licence: 'Driving licence',
  company_registration: 'Company registration certificate',
  pan_vat: 'PAN / VAT certificate',
}

export const GATEWAYS: Record<string, string> = { esewa: 'eSewa', khalti: 'Khalti', bank: 'Bank transfer' }

/** Screen owner dashboard: GET /orgs/{org}/owner/overview */
export interface OwnerOverview {
  screens: number
  published: number
  players: number
  players_online: number
  live: number
  upcoming: number
  today: number
  /** Rupees from plays already shown. */
  earned: string
}

/** One screen's day for its owner: GET /orgs/{org}/screens/{screen}/schedule */
export interface OwnerSchedule {
  date: string
  loop_seconds: number
  spot_seconds: number
  slots_per_loop: number
  bookings: {
    id: string
    status: string
    advertiser: string | null
    ad: string | null
    kind: 'image' | 'video' | null
    thumb: string | null
    start_time: string
    end_time: string
    starts_on: string
    ends_on: string
    slot_count: number
    repeats: number
    plays_per_day: number
  }[]
  /** Each hour of the day: ads in play order, with the first slot of each play. */
  hours: { hour: number; used: number; ads: { booking: string; slots: number[]; slot_count: number }[] }[]
}
