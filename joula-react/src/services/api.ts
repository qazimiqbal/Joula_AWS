import axios, { AxiosInstance, AxiosError } from 'axios'
import { ApiResponse, User, Masjid, LoginRequest, AuthResponse, PrayerTime } from '@/types'

const API_BASE_URL = import.meta.env.VITE_API_URL || '/mobile'

interface LegacyMasjid {
  ID: string
  Name: string
  City: string
  Coordinates: string
  H_No: string
  St_Name: string
  State: string
  Zip: string
}

const toKm = (lat1: number, lon1: number, lat2: number, lon2: number): number => {
  const earthRadiusKm = 6371
  const dLat = ((lat2 - lat1) * Math.PI) / 180
  const dLon = ((lon2 - lon1) * Math.PI) / 180
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return earthRadiusKm * c
}

const mapLegacyMasjid = (item: LegacyMasjid): Masjid | null => {
  const coordinateParts = (item.Coordinates || '').split(',')
  if (coordinateParts.length !== 2) {
    return null
  }

  const latitude = Number(coordinateParts[0].trim())
  const longitude = Number(coordinateParts[1].trim())
  if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
    return null
  }

  const addressParts = [item.H_No, item.St_Name, item.City, item.State, item.Zip]
    .map((part) => (part || '').trim())
    .filter(Boolean)

  return {
    id: Number(item.ID),
    name: item.Name,
    address: addressParts.join(' '),
    latitude,
    longitude,
    createdAt: new Date().toISOString(),
  }
}

class ApiService {
  private api: AxiosInstance

  constructor() {
    this.api = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
      },
    })

    // Add request interceptor to include auth token
    this.api.interceptors.request.use((config) => {
      const token = localStorage.getItem('authToken')
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }
      return config
    })

    // Add response interceptor for error handling
    this.api.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          const requestUrl = error.config?.url || ''
          const isLoginRequest = requestUrl.includes('/api/login.php')

          if (!isLoginRequest) {
            localStorage.removeItem('authToken')
            localStorage.removeItem('user')
            window.location.href = import.meta.env.BASE_URL
          }
        }
        return Promise.reject(error)
      }
    )
  }

  // Auth endpoints
  async login(credentials: LoginRequest): Promise<AuthResponse> {
    const response = await this.api.post<ApiResponse<AuthResponse>>('/api/login.php', credentials)
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Login failed')
  }

  async logout(): Promise<void> {
    return
  }

  async register(_userData: Partial<User>): Promise<User> {
    throw new Error('Registration is not available in PHP adapter mode')
  }

  // User endpoints
  async getUser(id: number): Promise<User> {
    const response = await this.api.get<ApiResponse<User>>('/api/user.php', { params: { id } })
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Failed to fetch user')
  }

  async updateUser(id: number, userData: Partial<User>): Promise<User> {
    const response = await this.api.post<ApiResponse<User>>('/api/user.php', {
      id,
      ...userData,
    })
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Failed to update user')
  }

  // Masjid endpoints
  async getMasjids(params?: { search?: string; limit?: number; page?: number }): Promise<Masjid[]> {
    const response = await this.api.get<LegacyMasjid[]>('/getmasjiddata.php')
    const mapped = response.data
      .map((item) => mapLegacyMasjid(item))
      .filter((item): item is Masjid => item !== null)

    const search = params?.search?.trim().toLowerCase()
    const filtered = search
      ? mapped.filter(
          (masjid) =>
            masjid.name.toLowerCase().includes(search) || masjid.address.toLowerCase().includes(search)
        )
      : mapped

    if (params?.limit && params.limit > 0) {
      return filtered.slice(0, params.limit)
    }

    return filtered
  }

  async getMasjid(id: number): Promise<Masjid> {
    const masjids = await this.getMasjids()
    const masjid = masjids.find((item) => item.id === id)
    if (masjid) {
      return masjid
    }
    throw new Error('Failed to fetch masjid')
  }

  async searchMasjidsByLocation(
    latitude: number,
    longitude: number,
    radiusKm: number = 10
  ): Promise<Masjid[]> {
    const masjids = await this.getMasjids()
    return masjids
      .map((masjid) => ({
        ...masjid,
        distance: toKm(latitude, longitude, masjid.latitude, masjid.longitude),
      }))
      .filter((masjid) => (masjid.distance || 0) <= radiusKm)
      .sort((a, b) => (a.distance || 0) - (b.distance || 0))
  }

  async calculateDistance(
    lat1: number,
    lon1: number,
    lat2: number,
    lon2: number
  ): Promise<number> {
    return toKm(lat1, lon1, lat2, lon2)
  }

  // Prayer times
  async getPrayerTimes(_masjidId: number): Promise<PrayerTime[]> {
    return []
  }
}

export default new ApiService()
