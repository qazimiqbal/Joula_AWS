// User types
export interface User {
  id: number
  name: string
  email: string
  phone?: string
  role: 'user' | 'admin'
  permissionLevel?: number
  createdAt: string
}

// Masjid types
export interface Masjid {
  id: number
  name: string
  address: string
  latitude: number
  longitude: number
  city?: string
  phone?: string
  website?: string
  members?: number
  prayerTimes?: PrayerTime[]
  distance?: number
  state?: string
  locality?: string
  createdAt: string
}

export interface AddressRecord {
  id: number
  name: string
  address: string
  latitude: number
  longitude: number
  city?: string
  state?: string
  locality?: string
  houseNo?: string
  streetName?: string
  zip?: string
  aptNo?: string
  distance?: number
  lastVisit?: string
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
}
