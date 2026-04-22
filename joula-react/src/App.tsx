import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate, useLocation } from 'react-router-dom'
import {
  AppBar, Box, Toolbar, Typography, Container,
  BottomNavigation, BottomNavigationAction, Paper, useMediaQuery, useTheme,
} from '@mui/material'
import DashboardIcon from '@mui/icons-material/Dashboard'
import MapIcon from '@mui/icons-material/Map'
import AccountCircleIcon from '@mui/icons-material/AccountCircle'
import LogoutIcon from '@mui/icons-material/Logout'
import { AuthProvider, useAuth } from './context/AuthContext'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import MapView from './pages/MapView'
import AreaSelection from './pages/AreaSelection'
import UserProfile from './pages/UserProfile'
import PrivateRoute from './components/PrivateRoute'
import './App.css'

function MobileBottomNav() {
  const navigate = useNavigate()
  const location = useLocation()
  const { isAuthenticated, logout } = useAuth()
  const theme = useTheme()
  const isMobile = useMediaQuery(theme.breakpoints.down('md'))

  if (!isAuthenticated || !isMobile) return null

  const pathToValue = (path: string) => {
    if (path.startsWith('/map') || path.startsWith('/area-selection')) return 1
    if (path.startsWith('/profile')) return 2
    return 0
  }

  return (
    <Paper sx={{ position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 1200 }} elevation={3}>
      <BottomNavigation
        value={pathToValue(location.pathname)}
        onChange={(_e, newValue) => {
          if (newValue === 0) navigate('/dashboard')
          if (newValue === 1) navigate('/area-selection')
          if (newValue === 2) navigate('/profile')
          if (newValue === 3) { logout(); navigate('/login') }
        }}
      >
        <BottomNavigationAction label="Home" icon={<DashboardIcon />} />
        <BottomNavigationAction label="Map" icon={<MapIcon />} />
        <BottomNavigationAction label="Profile" icon={<AccountCircleIcon />} />
        <BottomNavigationAction label="Logout" icon={<LogoutIcon />} />
      </BottomNavigation>
    </Paper>
  )
}

function App() {
  return (
    <AuthProvider>
      <Router basename={import.meta.env.BASE_URL}>
        <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
          <AppBar position="static">
            <Toolbar>
              <Typography
                variant="h6"
                component="div"
                sx={{ flexGrow: 1, fontSize: { xs: '1rem', sm: '1.25rem' } }}
              >
                🕌 <Box component="span" sx={{ display: { xs: 'none', sm: 'inline' } }}>Joula - Masjid Finder</Box>
                <Box component="span" sx={{ display: { xs: 'inline', sm: 'none' } }}>Joula</Box>
              </Typography>
            </Toolbar>
          </AppBar>

          <Container
            maxWidth="lg"
            sx={{ flexGrow: 1, py: { xs: 2, md: 4 }, px: { xs: 1, sm: 2, md: 3 }, pb: { xs: 10, md: 4 } }}
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

          <MobileBottomNav />
        </Box>
      </Router>
    </AuthProvider>
  )
}

export default App
