import React, { createContext, useContext, useState, useEffect, useRef, useCallback } from 'react'
import axios from 'axios'
import { User, SubscriptionInfo } from '@/types'
import apiService from '@services/api'

const INACTIVITY_TIMEOUT_MS = 5 * 60 * 1000 // 5 minutes
const ACTIVITY_EVENTS = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click']

interface AuthContextType {
  user: User | null
  isAuthenticated: boolean
  loading: boolean
  subscription: SubscriptionInfo | null
  login: (email: string, password: string) => Promise<void>
  loginWithGoogle: (idToken: string) => Promise<void>
  logout: () => void
  setUser: (user: User | null) => void
  setSubscription: (sub: SubscriptionInfo | null) => void
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [subscription, setSubscription] = useState<SubscriptionInfo | null>(null)
  const [loading, setLoading] = useState(true)
  const inactivityTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  const doLogout = useCallback(() => {
    setUser(null)
    setSubscription(null)
    localStorage.removeItem('authToken')
    localStorage.removeItem('user')
    localStorage.removeItem('subscription')
  }, [])

  const resetInactivityTimer = useCallback(() => {
    if (inactivityTimer.current) clearTimeout(inactivityTimer.current)
    inactivityTimer.current = setTimeout(() => {
      doLogout()
    }, INACTIVITY_TIMEOUT_MS)
  }, [doLogout])

  // Attach/detach activity listeners based on auth state
  useEffect(() => {
    if (!user) {
      if (inactivityTimer.current) clearTimeout(inactivityTimer.current)
      ACTIVITY_EVENTS.forEach((evt) => window.removeEventListener(evt, resetInactivityTimer))
      return
    }
    ACTIVITY_EVENTS.forEach((evt) => window.addEventListener(evt, resetInactivityTimer, { passive: true }))
    resetInactivityTimer()
    return () => {
      if (inactivityTimer.current) clearTimeout(inactivityTimer.current)
      ACTIVITY_EVENTS.forEach((evt) => window.removeEventListener(evt, resetInactivityTimer))
    }
  }, [user, resetInactivityTimer])

  useEffect(() => {
    // Check if user is already logged in
    const storedUser = localStorage.getItem('user')
    if (storedUser) {
      try {
        setUser(JSON.parse(storedUser))
      } catch (error) {
        console.error('Failed to parse stored user:', error)
      }
    }
    const storedSub = localStorage.getItem('subscription')
    if (storedSub) {
      try {
        setSubscription(JSON.parse(storedSub))
      } catch { /* ignore */ }
    }
    setLoading(false)
  }, [])

  useEffect(() => {
    const token = localStorage.getItem('authToken')
    if (!user || !token) return

    let cancelled = false
    const refreshSubscription = async () => {
      try {
        const latest = await apiService.getSubscription()
        if (cancelled) return
        setSubscription(latest)
        if (latest) {
          localStorage.setItem('subscription', JSON.stringify(latest))
        } else {
          localStorage.removeItem('subscription')
        }
      } catch {
        // Keep existing subscription state if refresh fails.
      }
    }

    refreshSubscription()
    return () => {
      cancelled = true
    }
  }, [user])

  const login = async (email: string, password: string) => {
    setLoading(true)
    try {
      console.log('[AuthContext] Calling apiService.login with', email)
      const response = await apiService.login({ email, password })
      console.log('[AuthContext] apiService.login response:', response)
      setUser(response.user)
      localStorage.setItem('authToken', response.token)
      localStorage.setItem('user', JSON.stringify(response.user))

      const sub = response.subscription ?? null
      setSubscription(sub)
      if (sub) {
        localStorage.setItem('subscription', JSON.stringify(sub))
      } else {
        localStorage.removeItem('subscription')
      }
      console.log('[AuthContext] Login state updated, user:', response.user)
    } catch (error) {
      console.error('[AuthContext] Login error:', error)
      if (axios.isAxiosError(error)) {
        const responseData = error.response?.data as { message?: string } | undefined
        if (responseData?.message) {
          throw new Error(responseData.message)
        }
      }
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        throw new Error('Login endpoint was not found. Upload Joula/api/login.php and verify VITE_API_URL.')
      }
      throw error
    } finally {
      setLoading(false)
      console.log('[AuthContext] Login finished, loading:', loading)
    }
  }

  const loginWithGoogle = async (idToken: string) => {
    setLoading(true)
    try {
      const response = await apiService.googleLogin(idToken)
      setUser(response.user)
      localStorage.setItem('authToken', response.token)
      localStorage.setItem('user', JSON.stringify(response.user))
      const sub = response.subscription ?? null
      setSubscription(sub)
      if (sub) {
        localStorage.setItem('subscription', JSON.stringify(sub))
      } else {
        localStorage.removeItem('subscription')
      }
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const responseData = error.response?.data as { message?: string } | undefined
        if (responseData?.message) {
          throw new Error(responseData.message)
        }
      }
      throw error
    } finally {
      setLoading(false)
    }
  }

  const logout = useCallback(() => {
    doLogout()
  }, [doLogout])

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: !!user, loading, subscription, login, loginWithGoogle, logout, setUser, setSubscription }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
