import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  TextField,
  Typography,
} from '@mui/material'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'

const AccountSettings: React.FC = () => {
  const { user, setUser } = useAuth()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [formData, setFormData] = useState({
    email: user?.email || '',
    phone: user?.phone || '',
    password: '',
    confirmPassword: '',
  })

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!user) return

    setError('')
    setSuccess('')

    if (!formData.email.trim()) {
      setError('Email is required')
      return
    }

    if (formData.password || formData.confirmPassword) {
      if (formData.password !== formData.confirmPassword) {
        setError('New password and re-entered password do not match')
        return
      }

      if (formData.password.trim().length < 4) {
        setError('New password must be at least 4 characters')
        return
      }
    }

    setLoading(true)

    try {
      const updatedUser = await apiService.updateUser(user.id, {
        email: formData.email.trim(),
        phone: formData.phone.trim(),
        password: formData.password.trim() || undefined,
      })
      setUser(updatedUser)
      localStorage.setItem('user', JSON.stringify(updatedUser))
      setFormData((prev) => ({ ...prev, password: '', confirmPassword: '' }))
      setSuccess('Account information updated successfully')
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to update account information')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box sx={{ maxWidth: 520, mx: 'auto' }}>
      <Typography variant="h4" gutterBottom sx={{ mb: 3 }}>
        Account Settings
      </Typography>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      <Paper elevation={1} sx={{ p: 3 }}>
        <form onSubmit={handleSubmit}>
          <TextField
            fullWidth
            label="Email"
            name="email"
            type="email"
            value={formData.email}
            onChange={handleChange}
            margin="normal"
            disabled={loading}
          />
          <TextField
            fullWidth
            label="Phone"
            name="phone"
            value={formData.phone}
            onChange={handleChange}
            margin="normal"
            disabled={loading}
          />
          <TextField
            fullWidth
            label="New Password"
            name="password"
            type="password"
            value={formData.password}
            onChange={handleChange}
            margin="normal"
            disabled={loading}
            helperText="Leave blank to keep your current password"
          />
          <TextField
            fullWidth
            label="Re-enter New Password"
            name="confirmPassword"
            type="password"
            value={formData.confirmPassword}
            onChange={handleChange}
            margin="normal"
            disabled={loading}
          />

          <Box sx={{ display: 'flex', gap: 2, mt: 3 }}>
            <Button
              type="submit"
              variant="contained"
              disabled={loading}
              sx={{ flex: 1 }}
            >
              {loading ? <CircularProgress size={24} /> : 'Save Changes'}
            </Button>
            <Button
              type="button"
              variant="outlined"
              onClick={() => navigate('/dashboard')}
              disabled={loading}
              sx={{ flex: 1 }}
            >
              Back
            </Button>
          </Box>
        </form>
      </Paper>
    </Box>
  )
}

export default AccountSettings