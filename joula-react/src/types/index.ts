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
}

export interface RegisterRequest {
  username: string
  password: string
  email: string
  phone: string
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

export interface CreateAddressRequest {
  name: string
  halaqa: string
  houseNo: string
  aptNo?: string
  streetName: string
  city: string
  state: string
  zip: string
  locality: string
  verified: 'Y' | 'N'
  masjid?: string
  lastVisit?: string
  comments?: string
  latitude?: number
  longitude?: number
}
