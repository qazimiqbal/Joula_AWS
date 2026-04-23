import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Tabs,
  Tab,
  Box,
  Button,
  TextField,
  Paper,
  Typography,
  Container,
  Alert,
  CircularProgress,
} from '@mui/material'
import axios from 'axios'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'

const Login: React.FC = () => {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  const [regUsername, setRegUsername] = useState('')
  const [regPassword, setRegPassword] = useState('')
  const [regConfirmPassword, setRegConfirmPassword] = useState('')
  const [regEmail, setRegEmail] = useState('')
  const [regPhone, setRegPhone] = useState('')
  const [duplicateFields, setDuplicateFields] = useState<{ username: boolean; password: boolean; email: boolean }>({
    username: false,
    password: false,
    email: false,
  })

  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [loading, setLoading] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    setLoading(true)

    try {
      await login(email, password)
      navigate('/dashboard')
    } catch (err: any) {
      setError(err.message || 'Login failed. Please check your credentials.')
    } finally {
      setLoading(false)
    }
  }

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    setDuplicateFields({ username: false, password: false, email: false })

    if (!regUsername.trim() || !regPassword.trim() || !regEmail.trim() || !regPhone.trim()) {
      setError('Username, password, email, and phone are required.')
      return
    }

    if (regPassword !== regConfirmPassword) {
      setError('Password and re-entered password do not match.')
      return
    }

    setLoading(true)
    try {
      await apiService.register({
        username: regUsername.trim(),
        password: regPassword,
        email: regEmail.trim(),
        phone: regPhone.trim(),
      })
      setSuccess('Account request submitted. A super administrator will review and approve your account.')
      setRegUsername('')
      setRegPassword('')
      setRegConfirmPassword('')
      setRegEmail('')
      setRegPhone('')
      setMode('login')
    } catch (err: unknown) {
      let message = 'Failed to create account.'
      if (axios.isAxiosError(err)) {
        const responseData = err.response?.data as { message?: string } | undefined
        if (responseData?.message) {
          message = responseData.message
        } else if (err.response?.status === 404) {
          message = 'Registration endpoint was not found. Upload mobile/api/register.php and verify VITE_API_URL.'
        } else if (err.message) {
          message = err.message
        }
      } else if (err instanceof Error) {
        message = err.message
      }
      setError(message)
      setDuplicateFields({
        username: /username/i.test(message),
        password: /password/i.test(message),
        email: /email/i.test(message),
      })
    } finally {
      setLoading(false)
    }
  }

  return (
    <Container maxWidth="sm">
      <Box
        sx={{
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'center',
          alignItems: 'center',
          minHeight: '100vh',
          py: 2,
        }}
      >
        <Paper elevation={3} sx={{ p: { xs: 2, sm: 4 }, width: '100%' }}>
          <Typography variant="h4" component="h1" gutterBottom align="center" sx={{ mb: 3 }}>
            🕌 Joula Login
          </Typography>

          <Tabs
            value={mode}
            onChange={(_, value) => {
              setMode(value)
              setError('')
              setSuccess('')
              setDuplicateFields({ username: false, password: false, email: false })
            }}
            centered
            sx={{ mb: 2 }}
          >
            <Tab label="Login" value="login" />
            <Tab label="Create Account" value="register" />
          </Tabs>

          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
          {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

          {mode === 'login' ? (
            <form onSubmit={handleSubmit}>
              <TextField
                fullWidth
                label="Email or Username"
                type="text"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                margin="normal"
                autoComplete="username"
                required
                disabled={loading}
              />
              <TextField
                fullWidth
                label="Password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                margin="normal"
                required
                disabled={loading}
              />
              <Button
                fullWidth
                variant="contained"
                color="primary"
                size="large"
                sx={{ mt: 3 }}
                type="submit"
                disabled={loading}
              >
                {loading ? <CircularProgress size={24} /> : 'Login'}
              </Button>
            </form>
          ) : (
            <form onSubmit={handleRegister}>
              <TextField
                fullWidth
                label="Username"
                type="text"
                value={regUsername}
                onChange={(e) => setRegUsername(e.target.value)}
                margin="normal"
                required
                disabled={loading}
                error={duplicateFields.username}
                helperText={duplicateFields.username ? 'This username already exists. Please choose another one.' : ''}
              />
              <TextField
                fullWidth
                label="Password"
                type="password"
                value={regPassword}
                onChange={(e) => setRegPassword(e.target.value)}
                margin="normal"
                required
                disabled={loading}
                error={duplicateFields.password}
                helperText={duplicateFields.password ? 'This password already exists. Please choose another one.' : ''}
              />
              <TextField
                fullWidth
                label="Re-enter Password"
                type="password"
                value={regConfirmPassword}
                onChange={(e) => setRegConfirmPassword(e.target.value)}
                margin="normal"
                required
                disabled={loading}
              />
              <TextField
                fullWidth
                label="Email"
                type="email"
                value={regEmail}
                onChange={(e) => setRegEmail(e.target.value)}
                margin="normal"
                required
                disabled={loading}
                error={duplicateFields.email}
                helperText={duplicateFields.email ? 'This email already exists. Please choose another one.' : ''}
              />
              <TextField
                fullWidth
                label="Phone"
                type="text"
                value={regPhone}
                onChange={(e) => setRegPhone(e.target.value)}
                margin="normal"
                required
                disabled={loading}
              />
              <Button
                fullWidth
                variant="contained"
                color="primary"
                size="large"
                sx={{ mt: 3 }}
                type="submit"
                disabled={loading}
              >
                {loading ? <CircularProgress size={24} /> : 'Create Account'}
              </Button>
            </form>
          )}

          <Typography variant="body2" align="center" sx={{ mt: 2 }}>
            Demo credentials: admin@myjoula.com / password123
          </Typography>
        </Paper>
      </Box>
    </Container>
  )
}

export default Login
