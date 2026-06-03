import React, { useEffect, useMemo, useState } from 'react'
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
  Divider,
} from '@mui/material'
import axios from 'axios'
import { useGoogleLogin } from '@react-oauth/google'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'

// Isolated component so useGoogleLogin hook only runs inside GoogleOAuthProvider
interface GoogleButtonProps {
  loading: boolean
  onSuccess: (accessToken: string) => void
  onError: (msg: string) => void
  showDivider: boolean
}

const GoogleSignInButton: React.FC<GoogleButtonProps> = ({ loading, onSuccess, onError, showDivider }) => {
  const googleLogin = useGoogleLogin({
    flow: 'implicit',
    prompt: 'select_account',
    onSuccess: (tokenResponse) => onSuccess(tokenResponse.access_token),
    onError: (error) => {
      onError('Google sign-in was cancelled or failed. Please try again.')
      console.error('Google login error:', error)
    },
  })

  return (
    <>
      {showDivider && <Divider sx={{ my: 2 }}>OR</Divider>}
      <Button
        fullWidth
        variant="outlined"
        color="inherit"
        size="large"
        onClick={() => googleLogin()}
        disabled={loading}
      >
        Continue with Google
      </Button>
    </>
  )
}

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
  const { login, loginWithGoogle } = useAuth()
  const navigate = useNavigate()

  const authMode = useMemo<'local' | 'hybrid' | 'google' | 'auto'>(() => {
    const raw = String(import.meta.env.VITE_AUTH_MODE ?? 'auto').trim().toLowerCase()
    if (raw === 'google' || raw === 'hybrid' || raw === 'local' || raw === 'auto') {
      return raw
    }
    return 'auto'
  }, [])

  const allowPassword = authMode !== 'google'
  const hasGoogleClientId = !!(import.meta.env.VITE_GOOGLE_CLIENT_ID as string | undefined)
  const allowGoogle = (authMode === 'google' || authMode === 'hybrid' || authMode === 'auto') && hasGoogleClientId

  useEffect(() => {
    if (!allowPassword && mode === 'register') {
      setMode('login')
    }
  }, [allowPassword, mode])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    setLoading(true)

    try {
      console.log('Attempting login with:', email)
      await login(email, password)
      console.log('Login successful, navigating to /dashboard')
      navigate('/dashboard')
    } catch (err: any) {
      console.error('Login error:', err)
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

  const handleGoogleSuccess = async (accessToken: string) => {
    setLoading(true)
    setError('')
    try {
      await loginWithGoogle(accessToken)
      navigate('/dashboard')
    } catch (err: any) {
      const msg = err.message || 'Google sign-in failed.'
      if (msg.toLowerCase().includes('pending') || msg.toLowerCase().includes('approval')) {
        setError('Your account is pending administrator approval. Please contact your administrator.')
      } else {
        setError(msg)
      }
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

          {allowPassword ? (
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
          ) : (
            <Alert severity="info" sx={{ mb: 2 }}>
              Google-only mode is active.
            </Alert>
          )}

          {authMode === 'hybrid' && (
            <Alert severity="info" sx={{ mb: 2 }}>
              Hybrid mode is active: you can debug with password login and roll out Google sign-in gradually.
            </Alert>
          )}

          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
          {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

          {mode === 'login' ? (
            <Box>
              {allowPassword && (
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
              )}

              {allowGoogle && (
                <GoogleSignInButton
                  loading={loading}
                  onSuccess={handleGoogleSuccess}
                  onError={(msg) => setError(msg)}
                  showDivider={allowPassword}
                />
              )}
            </Box>
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
        </Paper>
      </Box>
    </Container>
  )
}

export default Login
