import React, { createContext, useContext, useState, useEffect } from 'react'
import axios from 'axios'
import { User, SubscriptionInfo } from '@/types'
import apiService from '@services/api'

interface AuthContextType {
  user: User | null
  isAuthenticated: boolean
  loading: boolean
  subscription: SubscriptionInfo | null
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  setUser: (user: User | null) => void
  setSubscription: (sub: SubscriptionInfo | null) => void
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [subscription, setSubscription] = useState<SubscriptionInfo | null>(null)
  const [loading, setLoading] = useState(true)

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

  const login = async (email: string, password: string) => {
    setLoading(true)
    try {
      const response = await apiService.login({ email, password })
      
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
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        throw new Error('Login endpoint was not found. Upload Joula/api/login.php and verify VITE_API_URL.')
      }
      throw error
    } finally {
      setLoading(false)
    }
  }

  const logout = () => {
    setUser(null)
    setSubscription(null)
    localStorage.removeItem('authToken')
    localStorage.removeItem('user')
    localStorage.removeItem('subscription')
  }

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: !!user, loading, subscription, login, logout, setUser, setSubscription }}>
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
