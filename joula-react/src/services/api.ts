import axios, { AxiosInstance, AxiosError } from 'axios'
import { ApiResponse, User, Masjid, AddressRecord, LoginRequest, AuthResponse, PrayerTime } from '@/types'

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
  Locality?: string
}

interface LegacyAddress {
  ID: string
  Name: string
  City: string
  Coordinates: string
  H_No: string
  St_Name: string
  State: string
  Zip: string
  Locality?: string
  Last_Visit?: string
  Apt_No?: string
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
    city: item.City || '',
    state: item.State || '',
    locality: item.Locality || '',
    createdAt: new Date().toISOString(),
  }
}

const mapLegacyAddress = (item: LegacyAddress): AddressRecord | null => {
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
    city: item.City || '',
    state: (item.State || '').trim(),
    locality: (item.Locality || '').trim(),
    houseNo: (item.H_No || '').trim(),
    streetName: (item.St_Name || '').trim(),
    zip: (item.Zip || '').trim(),
    aptNo: (item.Apt_No || '').trim(),
    lastVisit: item.Last_Visit || '',
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

  // Localities for area selection
  async getLocalities(state: string): Promise<string[]> {
    try {
      const response = await this.api.get<{ success: boolean; data: string[] }>('/api/localities.php', {
        params: { state },
      })
      return response.data.data || []
    } catch {
      // Fallback for environments where /api/localities.php is not deployed yet.
      const all = await this.getLegacyAddresses()
      const targetState = state.trim().toLowerCase()
      const inState = all.filter((item) => (item.state || '').trim().toLowerCase() === targetState)

      const localityValues = Array.from(
        new Set(
          inState
            .map((item) => (item.locality || '').trim())
            .filter(Boolean)
        )
      )

      // When legacy payload omits Locality field, fall back to City values.
      if (localityValues.length === 0) {
        return Array.from(
          new Set(
            inState
              .map((item) => (item.city || '').trim())
              .filter(Boolean)
          )
        ).sort()
      }

      return localityValues.sort()
    }
  }

  private async getLegacyAddresses(locality: string = 'All', area: string = 'All'): Promise<AddressRecord[]> {
    const response = await this.api.get<LegacyAddress[]>('/getaddressdata.php', {
      params: {
        locality,
        area,
      },
    })

    return (response.data || [])
      .map((item) => mapLegacyAddress(item))
      .filter((item): item is AddressRecord => item !== null)
  }

  async getAddresses(params?: {
    state?: string
    locality?: string
    search?: string
    limit?: number
  }): Promise<AddressRecord[]> {
    let mapped: AddressRecord[] = []

    try {
      const response = await this.api.get<{ success: boolean; data: LegacyAddress[] }>('/api/addresses.php', {
        params: {
          state: params?.state,
          locality: params?.locality,
          search: params?.search,
        },
      })

      mapped = (response.data.data || [])
        .map((item) => mapLegacyAddress(item))
        .filter((item): item is AddressRecord => item !== null)
    } catch {
      // Fallback for environments where /api/addresses.php is not deployed yet.
      if (params?.locality && params.locality !== 'All') {
        mapped = await this.getLegacyAddresses(params.locality, 'All')
      } else {
        mapped = await this.getLegacyAddresses()
      }

      if (params?.state) {
        const targetState = params.state.trim().toLowerCase()
        mapped = mapped.filter((item) => (item.state || '').trim().toLowerCase() === targetState)
      }

      if (params?.search) {
        const search = params.search.trim().toLowerCase()
        mapped = mapped.filter(
          (item) =>
            item.name.toLowerCase().includes(search) ||
            item.address.toLowerCase().includes(search) ||
            (item.city || '').toLowerCase().includes(search)
        )
      }
    }

    if (params?.limit && params.limit > 0) {
      return mapped.slice(0, params.limit)
    }

    return mapped
  }

  async searchAddressesByLocation(
    latitude: number,
    longitude: number,
    radiusKm: number = 10
  ): Promise<AddressRecord[]> {
    const addresses = await this.getAddresses()
    return addresses
      .map((address) => ({
        ...address,
        distance: toKm(latitude, longitude, address.latitude, address.longitude),
      }))
      .filter((address) => (address.distance || 0) <= radiusKm)
      .sort((a, b) => (a.distance || 0) - (b.distance || 0))
  }

  // Prayer times
  async getPrayerTimes(_masjidId: number): Promise<PrayerTime[]> {
    return []
  }
}

export default new ApiService()
