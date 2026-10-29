export type Role = 'advertiser' | 'owner' | 'admin'

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  email_verified: boolean
  phone_verified: boolean
  mfa_enabled: boolean
  roles: Role[]
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
  slot_price: string
  audience_notes: string | null
  content_rules?: string[]
  status: 'draft' | 'active' | 'paused'
}

export interface Campaign {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

export interface Booking {
  id: string
  screen?: { id: string; name: string }
  slot_index: number
  start_date: string
  end_date: string
  status: string
  price: string
  hold_expires_at: string | null
}

export const CONTENT_RULES: Record<string, string> = {
  no_alcohol: 'No alcohol',
  no_tobacco: 'No tobacco',
  no_political: 'No political ads',
  no_gambling: 'No gambling',
  no_religious: 'No religious ads',
  no_adult: 'No adult content',
}
