import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate } from 'react-router-dom'
import {
  AppBar, Box, Toolbar, Typography, Container,
  Paper, Button,
} from '@mui/material'
import HomeIcon from '@mui/icons-material/Home'
import RefreshIcon from '@mui/icons-material/Refresh'
import LogoutIcon from '@mui/icons-material/Logout'
import { AuthProvider, useAuth } from './context/AuthContext'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import MapView from './pages/MapView'
import AreaSelection from './pages/AreaSelection'
import UserProfile from './pages/UserProfile'
import PrivateRoute from './components/PrivateRoute'
import './App.css'

function AppFooter() {
  const navigate = useNavigate()
  const { isAuthenticated, logout } = useAuth()

  if (!isAuthenticated) return null

  return (
    <Paper
      component="footer"
      elevation={3}
      square
      sx={{
        position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 1200,
        bgcolor: 'black', display: 'flex', justifyContent: 'center', gap: 1, py: 0.5,
      }}
    >
      <Button
        startIcon={<HomeIcon />}
        onClick={() => navigate('/dashboard')}
        sx={{ color: 'white', textTransform: 'none' }}
      >Home</Button>
      <Button
        startIcon={<RefreshIcon />}
        onClick={() => window.location.reload()}
        sx={{ color: 'white', textTransform: 'none' }}
      >Refresh</Button>
      <Button
        startIcon={<LogoutIcon />}
        onClick={() => { logout(); navigate('/login') }}
        sx={{ color: 'white', textTransform: 'none' }}
      >Logout</Button>
    </Paper>
  )
}

function App() {
  return (
    <AuthProvider>
      <Router basename={import.meta.env.BASE_URL}>
        <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
          <AppBar position="static" sx={{ bgcolor: 'black' }}>
            <Toolbar>
              <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
                Joula Dashboard
              </Typography>
            </Toolbar>
          </AppBar>

          <Container
            maxWidth={false}
            disableGutters
            sx={{ flexGrow: 1, overflow: 'hidden' }}
          >
            <Routes>
              <Route path="/login" element={<Login />} />
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route
                path="/dashboard"
                element={
                  <PrivateRoute>
                    <Dashboard />
                  </PrivateRoute>
                }
              />
              <Route
                path="/area-selection"
                element={
                  <PrivateRoute>
                    <AreaSelection />
                  </PrivateRoute>
                }
              />
              <Route
                path="/map"
                element={
                  <PrivateRoute>
                    <MapView />
                  </PrivateRoute>
                }
              />
              <Route
                path="/profile"
                element={
                  <PrivateRoute>
                    <UserProfile />
                  </PrivateRoute>
                }
              />
            </Routes>
          </Container>

          <AppFooter />
        </Box>
      </Router>
    </AuthProvider>
  )
}

export default App
