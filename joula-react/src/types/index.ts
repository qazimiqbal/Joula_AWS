// User types
export interface User {
  id: number
  name: string
  email: string
  phone?: string
  role: 'user' | 'admin'
  permissionLevel?: number
  orgRole?: 'org_admin' | 'admin' | 'editor' | 'viewer'
  isFreeUser?: boolean
  createdAt: string
}

// Masjid types
export interface Masjid {
  id: number
  name: string
  address: string
  latitude?: number
  longitude?: number
  city?: string
  phone?: string
  website?: string
  members?: number
  prayerTimes?: PrayerTime[]
  distance?: number
  state?: string
  houseNo?: string
  aptNo?: string
  streetName?: string
  zip?: string
  locality?: string
  createdAt: string
}

export interface AddressRecord {
  id: number
  name: string
  address: string
  latitude?: number | null
  longitude?: number | null
  city?: string
  state?: string
  locality?: string
  houseNo?: string
  streetName?: string
  zip?: string
  aptNo?: string
  distance?: number
  lastVisit?: string
  comments?: string
  createdAt: string
}

export interface PrayerTime {
  date: string
  fajr: string
  dhuhr: string
  asr: string
  maghrib: string
  isha: string
}

// API Response types
export interface ApiResponse<T> {
  success: boolean
  data?: T
  message?: string
  error?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
}

// Auth types
export interface LoginRequest {
  email: string
  password: string
}

export interface AuthResponse {
  token: string
  user: User
  subscription?: SubscriptionInfo | null
}

// Subscription / Billing types
export type PlanStatus = 'trial' | 'active' | 'past_due' | 'cancelled' | 'expired'

export interface SubscriptionInfo {
  orgId: number
  orgName?: string
  orgRole: 'org_admin' | 'admin' | 'editor' | 'viewer'
  planStatus: PlanStatus
  trialEndsAt: string
  trialDaysLeft: number
  hasPaymentMethod?: boolean
  freeAccount?: boolean
  maxEditors: number
  maxViewers: number
  monthlyPriceCents?: number
  stripePublishableKey?: string
}

export interface OrgUser {
  id: number
  username: string
  email: string
  phone: string
  orgRole: 'org_admin' | 'admin' | 'editor' | 'viewer'
  status: string
}

export interface OrgUsersResponse {
  users: OrgUser[]
  editorCount: number
  viewerCount: number
  maxEditors: number
  maxViewers: number
}

export interface RegisterRequest {
  username: string
  password: string
  email: string
  phone: string
}

export interface CreateTeamUserRequest {
  username: string
  email: string
  phone: string
  role: 'editor' | 'viewer'
}

export interface PendingUser {
  id: number
  username: string
  email: string
  phone: string
  createdAt: string
}

export interface MissingCoordinatesRecord {
  id: number
  name: string
  houseNo: string
  aptNo?: string
  streetName: string
  city: string
  state: string
  zip: string
  locality?: string
}

export interface PendingGeocodeRecord {
  id: number
  name: string
  houseNo: string
  aptNo?: string
  streetName: string
  city: string
  state: string
  zip: string
  locality?: string
  latitude?: number | null
  longitude?: number | null
  uploadedBy?: number | null
  submittedBy?: string
  comments?: string
  lastVisit?: string
  verified?: 'Y' | 'N'
  masjid?: string
  coordinates?: string
}

export interface PendingMasjidRecord {
  id: number
  name: string
  houseNo: string
  aptNo?: string
  streetName: string
  city: string
  state: string
  zip: string
  createdBy?: number | null
  submittedBy?: string
  Coordinates?: string
}

export interface CreateMasjidRequest {
  name: string
  houseNo: string
  aptNo?: string
  streetName: string
  city: string
  state: string
  zip: string
  latitude?: number
  longitude?: number
}

export interface CreateAddressRequest {
  name: string
  halaqa?: string
  houseNo: string
  aptNo?: string
  streetName: string
  city: string
  state: string
  zip: string
  locality?: string
  verified?: 'Y' | 'N'
  masjid?: string
  lastVisit?: string
  comments?: string
  coordinates?: string
  latitude?: number
  longitude?: number
}

export interface ImportAddressesResponse {
  success: boolean
  inserted: number
  skipped: number
  errors: Array<{ row: number; message: string }>
  message: string
  canImport?: boolean
  validationOnly?: boolean
}
