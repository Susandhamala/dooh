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

export interface Booking {
  id: string
  screen?: { id: string; name: string; address: string | null }
  creative?: { id: string; name: string; kind: string; status: CreativeStatus } | null
  start_date: string
  end_date: string
  start_hour: number
  end_hour: number
  status: string
  price_per_play: string
  plays_total: number
  plays_done: number
  price: string
  spent: string
  held: string
  created_at: string
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
  type: 'topup' | 'hold' | 'charge' | 'release'
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
