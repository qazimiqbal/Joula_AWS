import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate, useLocation } from 'react-router-dom'
import {
  AppBar, Box, Toolbar, Typography, Container,
  Paper, Button, Menu, MenuItem,
} from '@mui/material'
import { useState } from 'react'
import type { MouseEvent } from 'react'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown'
import HomeIcon from '@mui/icons-material/Home'
import RefreshIcon from '@mui/icons-material/Refresh'
import { AuthProvider, useAuth } from './context/AuthContext'
import { GoogleOAuthProvider } from '@react-oauth/google'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import MapView from './pages/MapView'
import AreaSelection from './pages/AreaSelection'
import UserProfile from './pages/UserProfile'
import EnterComments from './pages/EnterComments'
import AccountSettings from './pages/AccountSettings'
import PendingUsers from './pages/PendingUsers'
import PendingMasjids from './pages/PendingMasjids'
import PendingAddresses from './pages/PendingAddresses'
import AddAddress from './pages/AddAddress'
import AddMasjid from './pages/AddMasjid'
import AddressImport from './pages/AddressImport'
import MissingCoordinates from './pages/MissingCoordinates'
import GeocodeReview from './pages/GeocodeReview'
import BillingPage from './pages/BillingPage'
import OrgUsersPage from './pages/OrgUsersPage'
import OrgUserDetailsPage from './pages/OrgUserDetailsPage'
import BuildTeamPage from './pages/BuildTeamPage'
import CreateFreeUser from './pages/CreateFreeUser'
import ViewUsers from './pages/ViewUsers'
import AdminUserMasjids from './pages/AdminUserMasjids'
import AdminUserTeam from './pages/AdminUserTeam'
import AllAddresses from './pages/AllAddresses'
import PrivateRoute from './components/PrivateRoute'
import SubscriptionGuard from './components/SubscriptionGuard'
import SubscriptionBanner from './components/SubscriptionBanner'
import './App.css'

function AppHeader() {
  const navigate = useNavigate()
  const location = useLocation()
  const { isAuthenticated, user, logout } = useAuth()
  const [userMenuAnchor, setUserMenuAnchor] = useState<null | HTMLElement>(null)
  const showBack = isAuthenticated && location.pathname !== '/dashboard' && location.pathname !== '/login'
  const userMenuOpen = Boolean(userMenuAnchor)

  const handleOpenUserMenu = (event: MouseEvent<HTMLElement>) => {
    setUserMenuAnchor(event.currentTarget)
  }

  const handleCloseUserMenu = () => {
    setUserMenuAnchor(null)
  }

  const handleGoToAccount = () => {
    handleCloseUserMenu()
    navigate('/account')
  }

  const handleLogout = () => {
    handleCloseUserMenu()
    logout()
    navigate('/login')
  }

  return (
    <AppBar position="static" sx={{ bgcolor: 'black' }}>
      <Toolbar sx={{ px: 1 }}>
        {/* Left: Back button */}
        <Box sx={{ width: 90, display: 'flex', alignItems: 'center' }}>
          {showBack && (
            <Button
              startIcon={<ArrowBackIcon />}
              onClick={() => {
                if (window.history.length > 1) {
                  navigate(-1)
                } else {
                  navigate('/dashboard')
                }
              }}
              sx={{ color: 'white', textTransform: 'none', minWidth: 0, px: 1 }}
            >
              Back
            </Button>
          )}
        </Box>

        {/* Center: Spacer */}
        <Box sx={{ flexGrow: 1 }} />

        {/* Right: Welcome dropdown */}
        <Box sx={{ width: 180, display: 'flex', justifyContent: 'flex-end' }}>
          {isAuthenticated && user && (
            <>
              <Button
                onClick={handleOpenUserMenu}
                endIcon={<ArrowDropDownIcon />}
                sx={{
                  color: 'rgba(255,255,255,0.9)',
                  textTransform: 'none',
                  maxWidth: 180,
                  justifyContent: 'flex-end',
                }}
              >
                <Typography variant="caption" noWrap>
                  Welcome, {user.name}
                </Typography>
              </Button>
              <Menu
                anchorEl={userMenuAnchor}
                open={userMenuOpen}
                onClose={handleCloseUserMenu}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                transformOrigin={{ vertical: 'top', horizontal: 'right' }}
              >
                <MenuItem onClick={handleGoToAccount}>Account Settings</MenuItem>
                <MenuItem onClick={handleLogout}>Logout</MenuItem>
              </Menu>
            </>
          )}
        </Box>
      </Toolbar>
    </AppBar>
  )
}

function AppFooter() {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuth()

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
    </Paper>
  )
}

function App() {
  const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID as string | undefined
  const inner = (
    <AuthProvider>
      <Router basename={import.meta.env.BASE_URL}>
        <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100dvh' }}>
          <AppHeader />

          <Container
            maxWidth={false}
            disableGutters
            sx={{
              flexGrow: 1,
              display: 'flex',
              flexDirection: 'column',
              minHeight: { xs: 'calc(100dvh - 56px)', sm: 'calc(100dvh - 64px)' },
              pb: '56px',
            }}
          >
            <SubscriptionBanner />
            <Box sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
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
                <Route
                  path="/account"
                  element={
                    <PrivateRoute>
                      <AccountSettings />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/comments"
                  element={
                    <PrivateRoute>
                      <EnterComments />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/pending-users"
                  element={
                    <PrivateRoute>
                      <PendingUsers />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/addresses/new"
                  element={
                    <PrivateRoute>
                      <SubscriptionGuard>
                        <AddAddress />
                      </SubscriptionGuard>
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/masjids/new"
                  element={
                    <PrivateRoute>
                      <AddMasjid />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/address-import"
                  element={
                    <PrivateRoute>
                      <SubscriptionGuard>
                        <AddressImport />
                      </SubscriptionGuard>
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/missing-coordinates"
                  element={
                    <PrivateRoute>
                      <MissingCoordinates />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/geocode-review"
                  element={
                    <PrivateRoute>
                      <SubscriptionGuard>
                        <GeocodeReview />
                      </SubscriptionGuard>
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/billing"
                  element={
                    <PrivateRoute>
                      <BillingPage />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/org-users"
                  element={
                    <PrivateRoute>
                      <SubscriptionGuard>
                        <OrgUsersPage />
                      </SubscriptionGuard>
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/org-users/:userId"
                  element={
                    <PrivateRoute>
                      <SubscriptionGuard>
                        <OrgUserDetailsPage />
                      </SubscriptionGuard>
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/build-team"
                  element={
                    <PrivateRoute>
                      <SubscriptionGuard>
                        <BuildTeamPage />
                      </SubscriptionGuard>
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/create-free-user"
                  element={
                    <PrivateRoute>
                      <CreateFreeUser />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/pending-masjids"
                  element={
                    <PrivateRoute>
                      <PendingMasjids />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/pending-addresses"
                  element={
                    <PrivateRoute>
                      <PendingAddresses />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/view-users"
                  element={
                    <PrivateRoute>
                      <ViewUsers />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/admin/users/:userId/masjids"
                  element={
                    <PrivateRoute>
                      <AdminUserMasjids />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/admin/users/:userId/team"
                  element={
                    <PrivateRoute>
                      <AdminUserTeam />
                    </PrivateRoute>
                  }
                />
                <Route
                  path="/admin/all-addresses"
                  element={
                    <PrivateRoute>
                      <AllAddresses />
                    </PrivateRoute>
                  }
                />
              </Routes>
            </Box>
          </Container>

          <AppFooter />
        </Box>
      </Router>
    </AuthProvider>
  )
  return googleClientId ? (
    <GoogleOAuthProvider clientId={googleClientId}>{inner}</GoogleOAuthProvider>
  ) : inner
}

export default App
