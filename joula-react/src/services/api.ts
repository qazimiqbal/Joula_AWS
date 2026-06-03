import axios, { AxiosInstance, AxiosError } from 'axios'
import { ApiResponse, User, Masjid, AddressRecord, LoginRequest, AuthResponse, PrayerTime, RegisterRequest, PendingUser, CreateAddressRequest, MissingCoordinatesRecord, PendingGeocodeRecord, SubscriptionInfo, OrgUsersResponse, ImportAddressesResponse, CreateMasjidRequest, PendingMasjidRecord, CreateTeamUserRequest } from '@/types'

const API_BASE_URL = import.meta.env.VITE_API_URL ?? (import.meta.env.DEV ? 'http://localhost:8000' : '/Joula/api')

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
  Comments?: string
}

interface LegacyMasjid {
  ID: string
  Name: string
  Coordinates?: string
  H_No?: string
  Apt_No?: string
  St_Name?: string
  City?: string
  State?: string
  Zip?: string
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
    comments: (item.Comments || '').trim(),
    createdAt: new Date().toISOString(),
  }
}

// Like mapLegacyAddress but doesn't discard records with missing/invalid coordinates
const mapLegacyAddressForList = (item: LegacyAddress): AddressRecord => {
  const coordinateParts = (item.Coordinates || '').split(',')
  const latitude = coordinateParts.length === 2 ? Number(coordinateParts[0].trim()) : Number.NaN
  const longitude = coordinateParts.length === 2 ? Number(coordinateParts[1].trim()) : Number.NaN

  const addressParts = [item.H_No, item.St_Name, item.City, item.State, item.Zip]
    .map((part) => (part || '').trim())
    .filter(Boolean)

  return {
    id: Number(item.ID),
    name: item.Name,
    address: addressParts.join(' '),
    latitude: Number.isFinite(latitude) ? latitude : undefined,
    longitude: Number.isFinite(longitude) ? longitude : undefined,
    city: item.City || '',
    state: (item.State || '').trim(),
    locality: (item.Locality || '').trim(),
    houseNo: (item.H_No || '').trim(),
    streetName: (item.St_Name || '').trim(),
    zip: (item.Zip || '').trim(),
    aptNo: (item.Apt_No || '').trim(),
    lastVisit: item.Last_Visit || '',
    comments: (item.Comments || '').trim(),
    createdAt: new Date().toISOString(),
  }
}

const mapLegacyMasjid = (item: LegacyMasjid): Masjid => {
  const coordinateParts = (item.Coordinates || '').split(',')
  const latitude = coordinateParts.length === 2 ? Number(coordinateParts[0].trim()) : Number.NaN
  const longitude = coordinateParts.length === 2 ? Number(coordinateParts[1].trim()) : Number.NaN

  const houseNo = (item.H_No || '').trim()
  const aptNo = (item.Apt_No || '').trim()
  const streetName = (item.St_Name || '').trim()
  const city = (item.City || '').trim()
  const state = (item.State || '').trim()
  const zip = (item.Zip || '').trim()

  const address = [aptNo, houseNo, streetName, city, state, zip]
    .filter(Boolean)
    .join(', ')

  return {
    id: Number(item.ID),
    name: (item.Name || '').trim(),
    address,
    latitude: Number.isFinite(latitude) ? latitude : undefined,
    longitude: Number.isFinite(longitude) ? longitude : undefined,
    city,
    state,
    houseNo,
    aptNo,
    streetName,
    zip,
    createdAt: new Date().toISOString(),
  }
}

class ApiService {
  // Google Geocoding via backend proxy
  async googleGeocodeAddress(address: string): Promise<{ lat: number; lng: number; raw?: any }> {
    const response = await this.api.get<{ success: boolean; lat?: number; lng?: number; raw?: any; message?: string }>(
      '/geocode.php',
      { params: { address } }
    )
    if (response.data.success && typeof response.data.lat === 'number' && typeof response.data.lng === 'number') {
      return { lat: response.data.lat, lng: response.data.lng, raw: response.data.raw }
    }
    throw new Error(response.data.message || 'Google geocoding failed')
  }

  async deleteMasjid(id: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/delete_masjid.php', { id })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to delete masjid')
    }
  }

  async deleteAddress(id: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/delete_address.php', { id })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to delete address')
    }
  }

  async updatePendingMasjid(
    id: number,
    data: {
      name: string
      houseNo: string
      aptNo?: string
      streetName: string
      city: string
      state: string
      zip: string
      coordinates?: string
    }
  ): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/masjid_review.php', {
      action: 'update',
      id,
      ...data,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update masjid')
    }
  }

  async updateMyData(type: 'masjid' | 'address', id: number, fields: Record<string, string>): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/my_data_update.php', {
      type,
      id,
      ...fields,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Update failed')
    }
  }
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
          const isLoginRequest = requestUrl.includes('/login.php') || requestUrl.includes('/google_login.php')
          const message = String((error.response.data as { message?: string } | undefined)?.message || '').toLowerCase()
          const looksLikeTokenFailure =
            message.includes('invalid token') ||
            message.includes('missing token') ||
            message.includes('auth token') ||
            message.includes('session expired') ||
            message.includes('not logged in')

          if (!isLoginRequest && looksLikeTokenFailure) {
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
    const response = await this.api.post<ApiResponse<AuthResponse>>('/login.php', credentials)
    console.log('[apiService.login] Full Axios response:', response)
    console.log('[apiService.login] response.data:', response.data)
    if (response.data && response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Login failed')
  }

  async googleLogin(idToken: string): Promise<AuthResponse> {
    const response = await this.api.post<ApiResponse<AuthResponse>>('/google_login.php', { idToken })
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Google login failed')
  }

  async logout(): Promise<void> {
    return
  }

  async register(userData: RegisterRequest): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/register.php', userData)
    if (!response.data.success) {
      throw new Error(response.data.message || 'Registration failed')
    }
  }

  // User endpoints
  async getUser(id: number): Promise<User> {
    const response = await this.api.get<ApiResponse<User>>('/user.php', { params: { id } })
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Failed to fetch user')
  }

  async updateUser(id: number, userData: Partial<User> & { password?: string }): Promise<User> {
    const response = await this.api.post<ApiResponse<User>>('/user.php', {
      id,
      ...userData,
    })
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Failed to update user')
  }

  async getPendingUsers(): Promise<PendingUser[]> {
    // Always send Authorization header (handled by interceptor)
    const response = await this.api.get<{ success: boolean; data: PendingUser[]; message?: string }>('/pending_users.php')
    if (response.data.data) {
      return response.data.data
    }
    throw new Error(response.data.message || 'Failed to fetch pending users')
  }

  async reviewPendingUser(userId: number, action: 'approve' | 'disapprove'): Promise<void> {
    // Always send Authorization header (handled by interceptor)
    const response = await this.api.post<{ success: boolean; message?: string }>('/pending_users.php', {
      userId,
      action,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update user status')
    }
  }

  // Masjid endpoints
  async getMasjids(params?: { state?: string; locality?: string; search?: string; limit?: number; page?: number; createdBy?: number; orgScoped?: boolean; includeOwnPending?: boolean; mine?: boolean }): Promise<Masjid[]> {
    const response = await this.api.get<{ success: boolean; data: LegacyMasjid[] }>('/masjids.php', {
      params: {
        state: params?.state,
        locality: params?.locality,
        search: params?.search,
        createdBy: params?.createdBy,
        orgScoped: params?.orgScoped ? '1' : undefined,
        includeOwnPending: params?.includeOwnPending ? '1' : undefined,
        mine: params?.mine ? '1' : undefined,
      },
    })

    let mapped = (response.data.data || []).map((item) => mapLegacyMasjid(item))

    const resolved = await Promise.all(
      mapped.map(async (masjid) => {
        if (typeof masjid.latitude === 'number' && typeof masjid.longitude === 'number') {
          return masjid
        }

        const parts = [masjid.aptNo, masjid.houseNo, masjid.streetName, masjid.city, masjid.state, masjid.zip]
          .map((value) => (value || '').trim())
          .filter(Boolean)

        if (parts.length === 0) {
          return masjid
        }

        try {
          const geo = await this.geocodeAddress(parts.join(' '))
          return {
            ...masjid,
            latitude: geo.lat,
            longitude: geo.lng,
          }
        } catch {
          return masjid
        }
      })
    )

    const search = params?.search?.trim().toLowerCase()
    const filtered = search
      ? resolved.filter(
          (masjid) =>
            masjid.name.toLowerCase().includes(search) || masjid.address.toLowerCase().includes(search)
        )
      : resolved

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
    radiusKm: number = 10,
    params?: { mine?: boolean }
  ): Promise<Masjid[]> {
    const masjids = await this.getMasjids(params)
    return masjids
      .filter((masjid) => typeof masjid.latitude === 'number' && typeof masjid.longitude === 'number')
      .map((masjid) => ({
        ...masjid,
        distance: toKm(latitude, longitude, masjid.latitude as number, masjid.longitude as number),
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
    const response = await this.api.get<{ success: boolean; data: string[] }>('/localities.php', {
      params: { state },
    })
    return response.data.data || []
  }

  async getAddresses(params?: {
    state?: string
    locality?: string
    search?: string
    limit?: number
    masjidId?: number
    mine?: boolean
    listAll?: boolean
  }): Promise<AddressRecord[]> {
    const response = await this.api.get<{ success: boolean; data: LegacyAddress[] }>('/addresses.php', {
      params: {
        state: params?.state,
        locality: params?.locality,
        search: params?.search,
        masjidId: params?.masjidId,
        mine: params?.mine ? '1' : undefined,
        listAll: params?.listAll ? '1' : undefined,
      },
    })

    const mapped = params?.listAll
      ? (response.data.data || []).map((item) => mapLegacyAddressForList(item))
      : (response.data.data || [])
          .map((item) => mapLegacyAddress(item))
          .filter((item): item is AddressRecord => item !== null)

    if (params?.limit && params.limit > 0) {
      return mapped.slice(0, params.limit)
    }

    return mapped
  }

  async searchAddressesByLocation(
    latitude: number,
    longitude: number,
    radiusKm: number = 10,
    params?: { mine?: boolean }
  ): Promise<AddressRecord[]> {
    const addresses = await this.getAddresses(params)
    return addresses
      .map((address) => {
        if (typeof address.latitude === 'number' && typeof address.longitude === 'number') {
          return {
            ...address,
            distance: toKm(latitude, longitude, address.latitude, address.longitude),
          }
        }
        return address
      })
      .filter((address) => typeof address.distance === 'number' && (address.distance || 0) <= radiusKm)
      .sort((a, b) => ((a.distance || 0) - (b.distance || 0)))
  }

  // Visit / Comments
  async getVisitData(id: number): Promise<{ comments: string; ethinicity: string; potential: string }> {
    const response = await this.api.get<{ success: boolean; data: { comments: string; ethinicity: string; potential: string } }>('/visit.php', { params: { id } })
    if (response.data.data) return response.data.data
    throw new Error('Failed to load visit data')
  }

  async updateVisit(
    id: number,
    data: { today: string; actionTaken: string; comments: string; ethinicity: string; potential: string }
  ): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/visit.php', { id, ...data })
    if (!response.data.success) throw new Error(response.data.message || 'Update failed')
  }

  async createAddress(data: CreateAddressRequest): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/address_create.php', data)
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to create address')
    }
  }

  async createMasjid(data: CreateMasjidRequest): Promise<void> {
    try {
      const response = await this.api.post<{ success: boolean; message?: string; error?: string; debug?: { values?: unknown; query?: string } }>('/masjid_create.php', data)
      if (!response.data.success) {
        const detail = response.data.error ? `: ${response.data.error}` : ''
        throw new Error((response.data.message || 'Failed to create masjid') + detail)
      }
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const payload = error.response?.data as { message?: string; error?: string } | undefined
        if (payload?.message || payload?.error) {
          const detail = payload?.error ? `: ${payload.error}` : ''
          throw new Error((payload?.message || 'Failed to create masjid') + detail)
        }
      }
      throw error
    }
  }

  async importAddresses(
    file: File,
    masjid: string,
    options?: { validateOnly?: boolean; ignoreErrors?: boolean }
  ): Promise<ImportAddressesResponse> {
    const form = new FormData()
    form.append('file', file)
    form.append('masjid', masjid)
    if (options?.validateOnly) {
      form.append('validateOnly', '1')
    }
    if (options?.ignoreErrors) {
      form.append('ignoreErrors', '1')
    }
    const response = await this.api.post<ImportAddressesResponse>('/address_import.php', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  }

  async getMissingCoordinates(): Promise<MissingCoordinatesRecord[]> {
    const response = await this.api.get<{ success: boolean; data: MissingCoordinatesRecord[] }>('/missing_coordinates.php')
    return response.data.data || []
  }

  async saveCoordinates(id: number, latitude: number, longitude: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/missing_coordinates.php', { id, latitude, longitude })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to save coordinates')
    }
  }

  async updateMissingCoordinatesAddress(
    id: number,
    payload: {
      name: string
      houseNo: string
      aptNo?: string
      streetName: string
      city: string
      state: string
      zip: string
      locality?: string
    }
  ): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/missing_coordinates.php', {
      action: 'update_address',
      id,
      ...payload,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update address')
    }
  }

  async deleteMissingCoordinatesAddress(id: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/missing_coordinates.php', {
      action: 'delete_address',
      id,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to delete address')
    }
  }

  async getGeocodeReviewList(createdBy?: number): Promise<PendingGeocodeRecord[]> {
    try {
      const response = await this.api.get<{ success: boolean; data: PendingGeocodeRecord[]; message?: string }>('/geocode_review.php', {
        params: createdBy ? { createdBy } : undefined,
      })
      if (!response.data.success) {
        throw new Error(response.data.message || 'Failed to load geocode review list')
      }
      return response.data.data || []
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        return []
      }
      throw error
    }
  }

  async approveGeocodedAddress(id: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/geocode_review.php', { id })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to approve address')
    }
  }

  async getAddressReviewList(createdBy?: number): Promise<PendingGeocodeRecord[]> {
    const response = await this.api.get<{ success: boolean; data: PendingGeocodeRecord[]; message?: string }>('/address_review.php', {
      params: createdBy ? { createdBy } : undefined,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to load address review list')
    }
    return response.data.data || []
  }

  async getMyPendingAddresses(): Promise<PendingGeocodeRecord[]> {
    const response = await this.api.get<{ success: boolean; data: PendingGeocodeRecord[]; message?: string }>('/my_pending_addresses.php')
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to load pending addresses')
    }
    return response.data.data || []
  }

  async approveAddress(id: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/address_review.php', { id })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to approve address')
    }
  }

  async approveAllAddresses(): Promise<number> {
    const response = await this.api.post<{ success: boolean; message?: string; approvedCount?: number }>('/address_review.php', {
      action: 'approve_all',
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to approve all addresses')
    }
    return Number(response.data.approvedCount || 0)
  }

  async updatePendingAddress(
    id: number,
    data: {
      name: string
      houseNo: string
      aptNo?: string
      streetName: string
      city: string
      state: string
      zip: string
      locality?: string
      comments?: string
      lastVisit?: string
      masjid?: string
      verified?: 'Y' | 'N'
      coordinates?: string
    }
  ): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/address_review.php', {
      action: 'update',
      id,
      ...data,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update address')
    }
  }

  async getMasjidReviewList(createdBy?: number): Promise<PendingMasjidRecord[]> {
    const response = await this.api.get<{ success: boolean; data: PendingMasjidRecord[]; message?: string }>('/masjid_review.php', {
      params: createdBy ? { createdBy } : undefined,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to load masjid review list')
    }
    return response.data.data || []
  }

  async getMyPendingMasjids(): Promise<PendingMasjidRecord[]> {
    const response = await this.api.get<{ success: boolean; data: PendingMasjidRecord[]; message?: string }>('/my_pending_masjids.php')
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to load pending masjids')
    }
    return response.data.data || []
  }

  async approveMasjid(id: number): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/masjid_review.php', { id })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to approve masjid')
    }
  }

  async adminGetUsers(): Promise<any[]> {
    const response = await this.api.get<{ success: boolean; data: any[]; message?: string }>('/admin_users.php', {
      params: { action: 'list' },
    })
    if (response.data.success) return response.data.data || []
    throw new Error(response.data.message || 'Failed to fetch users')
  }

  async adminGetUserMasjids(userId: number): Promise<any[]> {
    const response = await this.api.get<{ success: boolean; data: any[]; message?: string }>('/admin_users.php', {
      params: { action: 'masjids', userId },
    })
    if (response.data.success) return response.data.data || []
    throw new Error(response.data.message || 'Failed to fetch masjids')
  }

  async adminGetUserTeam(userId: number): Promise<any[]> {
    const response = await this.api.get<{ success: boolean; data: any[]; message?: string }>('/admin_users.php', {
      params: { action: 'team', userId },
    })
    if (response.data.success) return response.data.data || []
    throw new Error(response.data.message || 'Failed to fetch team')
  }

  async adminUpdateUser(userId: number, data: { email: string; phone: string; password?: string }): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/admin_users.php', {
      action: 'update_user',
      userId,
      ...data,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update user')
    }
  }

  async adminUpdateOrgLimits(orgId: number, data: { maxEditors: number; maxViewers: number; freeAccount: boolean }): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/admin_users.php?action=update_org_limits', {
      orgId,
      maxEditors: data.maxEditors,
      maxViewers: data.maxViewers,
      freeAccount: data.freeAccount,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update organization limits')
    }
  }

  // Only use Google geocoding for addresses
  async geocodeAddress(address: string): Promise<{ lat: number; lng: number }> {
    return this.googleGeocodeAddress(address);
  }

  async reverseGeocode(lat: number, lng: number): Promise<{ houseNo: string; streetName: string; city: string; state: string; zip: string }> {
    try {
      const response = await this.api.get<{
        success: boolean
        houseNo?: string
        streetName?: string
        city?: string
        state?: string
        zip?: string
        message?: string
      }>('/reverse_geocode.php', {
        params: { lat, lng },
      })

      if (!response.data.success) {
        throw new Error(response.data.message || 'Reverse geocoding failed')
      }

      return {
        houseNo: (response.data.houseNo || '').trim(),
        streetName: (response.data.streetName || '').trim(),
        city: (response.data.city || '').trim(),
        state: (response.data.state || '').trim(),
        zip: (response.data.zip || '').trim(),
      }
    } catch (error) {
      if (import.meta.env.DEV) {
        const fallback = await this.reverseGeocodeViaDevProxy(lat, lng)
        if (fallback) return fallback
      }
      throw error
    }
  }


  private async reverseGeocodeViaDevProxy(lat: number, lng: number): Promise<{ houseNo: string; streetName: string; city: string; state: string; zip: string } | null> {
    try {
      const response = await axios.get<{
        address?: {
          house_number?: string
          road?: string
          city?: string
          town?: string
          village?: string
          hamlet?: string
          state?: string
          postcode?: string
        }
      }>('/nominatim/reverse', {
        params: {
          format: 'json',
          addressdetails: 1,
          lat,
          lon: lng,
        },
        headers: { Accept: 'application/json' },
      })

      const address = response.data?.address
      if (!address) return null

      return {
        houseNo: (address.house_number || '').trim(),
        streetName: (address.road || '').trim(),
        city: (address.city || address.town || address.village || address.hamlet || '').trim(),
        state: (address.state || '').trim(),
        zip: (address.postcode || '').trim(),
      }
    } catch {
      return null
    }
  }

  // Prayer times
  async getPrayerTimes(_masjidId: number): Promise<PrayerTime[]> {
    return []
  }

  // ---------------------------------------------------------------
  // Subscription / Billing
  // ---------------------------------------------------------------
  async getSubscription(): Promise<SubscriptionInfo | null> {
    try {
      const response = await this.api.get('/subscription.php')
      return response.data?.data ?? null
    } catch {
      return null
    }
  }

  async createCheckoutSession(): Promise<string | null> {
    const response = await this.api.post('/subscription.php', { action: 'create_checkout' })
    return response.data?.checkoutUrl ?? null
  }

  async createBillingPortalSession(): Promise<string | null> {
    const response = await this.api.post('/subscription.php', { action: 'billing_portal' })
    return response.data?.portalUrl ?? null
  }

  // ---------------------------------------------------------------
  // Org Users (team management)
  // ---------------------------------------------------------------
  async getOrgUsers(): Promise<OrgUsersResponse | null> {
    try {
      const response = await this.api.get('/org_users.php')
      return response.data?.data ?? null
    } catch {
      return null
    }
  }

  async addOrgUser(userId: number, orgRole: 'editor' | 'viewer'): Promise<void> {
    await this.api.post('/org_users.php', { action: 'add_user', user_id: userId, org_role: orgRole })
  }

  async setOrgUserRole(userId: number, orgRole: 'editor' | 'viewer'): Promise<void> {
    await this.api.post('/org_users.php', { action: 'set_role', user_id: userId, org_role: orgRole })
  }

  async removeOrgUser(userId: number): Promise<void> {
    await this.api.post('/org_users.php', { action: 'remove_user', user_id: userId })
  }

  async createTeamUser(data: CreateTeamUserRequest): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/create_team_user.php', {
      username: data.username,
      email: data.email,
      phone: data.phone,
      orgRole: data.role,
    })
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to create team user')
    }
  }

  // ---------------------------------------------------------------
  // Free User Creation (super admin only)
  // ---------------------------------------------------------------
  async createFreeUser(data: { username: string; email: string; password: string; phone?: string }): Promise<void> {
    const response = await this.api.post<{ success: boolean; message?: string }>('/create_free_user.php', data)
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to create free user')
    }
  }
}

export default new ApiService()
