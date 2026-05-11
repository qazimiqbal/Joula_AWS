import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Box,
  Button,
  Card,
  CardContent,
  CardHeader,
  TextField,
  Typography,
  Alert,
  CircularProgress,
} from '@mui/material'
import { useAuth } from '@/context/AuthContext'
import apiService from '@/services/api'

const CreateFreeUser: React.FC = () => {
  const { user } = useAuth()
  const navigate = useNavigate()
  const permissionLevel = user?.permissionLevel ?? 0

  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [phone, setPhone] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  if (permissionLevel < 4) {
    return (
      <Box p={3}>
        <Typography color="error">Access denied. Super admin only.</Typography>
      </Box>
    )
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setSuccess(null)

    if (!username.trim() || !email.trim() || !password.trim()) {
      setError('Username, email, and password are required.')
      return
    }
    if (password.length < 6) {
      setError('Password must be at least 6 characters.')
      return
    }
    if (password !== confirmPassword) {
      setError('Passwords do not match.')
      return
    }

    setLoading(true)
    try {
      await apiService.createFreeUser({ username: username.trim(), email: email.trim(), password, phone: phone.trim() })
      setSuccess(`Free editor user "${username.trim()}" created successfully. They can now log in and add masjids/addresses.`)
      setUsername('')
      setEmail('')
      setPassword('')
      setConfirmPassword('')
      setPhone('')
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to create user'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box p={3} maxWidth={500} mx="auto">
      <Button variant="text" onClick={() => navigate('/dashboard')} sx={{ mb: 2 }}>
        ← Back to Dashboard
      </Button>
      <Card>
        <CardHeader title="Add Free Editor User" subheader="Creates a free user with editor (permission 2) access — no subscription required." />
        <CardContent>
          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
          {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}
          <Box component="form" onSubmit={handleSubmit} display="flex" flexDirection="column" gap={2}>
            <TextField
              label="Username"
              value={username}
              onChange={e => setUsername(e.target.value)}
              required
              fullWidth
              autoComplete="off"
            />
            <TextField
              label="Email"
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              required
              fullWidth
              autoComplete="off"
            />
            <TextField
              label="Password"
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              required
              fullWidth
              helperText="Minimum 6 characters"
              autoComplete="new-password"
            />
            <TextField
              label="Re-enter Password"
              type="password"
              value={confirmPassword}
              onChange={e => setConfirmPassword(e.target.value)}
              required
              fullWidth
              error={confirmPassword.length > 0 && password !== confirmPassword}
              helperText={confirmPassword.length > 0 && password !== confirmPassword ? 'Passwords do not match' : ''}
              autoComplete="new-password"
            />
            <TextField
              label="Phone (optional)"
              value={phone}
              onChange={e => setPhone(e.target.value)}
              fullWidth
              autoComplete="off"
            />
            <Button
              type="submit"
              variant="contained"
              color="primary"
              disabled={loading}
              fullWidth
            >
              {loading ? <CircularProgress size={22} color="inherit" /> : 'Create Free User'}
            </Button>
          </Box>
        </CardContent>
      </Card>
    </Box>
  )
}

export default CreateFreeUser
