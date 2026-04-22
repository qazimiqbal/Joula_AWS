export interface User {
  id: number
  name: string
  email: string
  password: string
  phone?: string
  role: 'user' | 'admin'
  createdAt: Date
  updatedAt: Date
}

export interface Masjid {
  id: number
  name: string
  address: string
  latitude: number
  longitude: number
  phone?: string
  website?: string
  members?: number
  createdAt: Date
  updatedAt: Date
}

export interface PrayerTime {
  id: number
  masjidId: number
  date: string
  fajr: string
  dhuhr: string
  asr: string
  maghrib: string
  isha: string
}

export interface ApiResponse<T> {
  success: boolean
  data?: T
  message?: string
  error?: string
}

export interface JwtPayload {
  id: number
  email: string
  role: string
}

export interface AuthRequest {
  email: string
  password: string
}
