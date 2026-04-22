import React from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Box,
  Grid,
  Typography,
  Button,
  Paper,
} from '@mui/material'
import { useAuth } from '@/context/AuthContext'

const Dashboard: React.FC = () => {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const permissionLevel = user?.permissionLevel ?? (user?.role === 'admin' ? 3 : 0)
  const canAccessStaffActions = permissionLevel > 0
  const canAccessAdminActions = permissionLevel >= 3

  const mobileBaseUrl = (import.meta.env.VITE_API_URL || '/mobile').replace(/\/$/, '')
  const siteBaseUrl = mobileBaseUrl.endsWith('/mobile')
    ? mobileBaseUrl.slice(0, -7)
    : window.location.origin

  const openExternal = (path: string) => {
    window.location.href = `${mobileBaseUrl}/${path}`
  }

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  return (
    <Box>
      <Box
        sx={{
          display: 'flex',
          flexDirection: { xs: 'column', sm: 'row' },
          justifyContent: 'space-between',
          alignItems: { xs: 'flex-start', sm: 'center' },
          gap: 1,
          mb: 3,
        }}
      >
        <Typography variant="h5" sx={{ fontSize: { xs: '1.25rem', sm: '1.5rem' } }}>
          Welcome, {user?.name}!
        </Typography>
        {/* Desktop nav buttons — hidden on mobile (bottom nav handles it) */}
        <Box sx={{ display: { xs: 'none', md: 'flex' }, gap: 1 }}>
          <Button variant="outlined" color="primary" onClick={() => navigate('/area-selection')}>
            Map
          </Button>
          <Button variant="outlined" color="primary" onClick={() => navigate('/profile')}>
            Profile
          </Button>
          <Button variant="contained" color="error" onClick={handleLogout}>
            Logout
          </Button>
        </Box>
      </Box>

      <Typography variant="h5" gutterBottom sx={{ mt: 4, mb: 2 }}>
        Dashboard Actions
      </Typography>

      <Paper elevation={1} sx={{ p: 2 }}>
        <Grid container spacing={2}>
          <Grid item xs={12} sm={6} md={4}>
            <Button fullWidth variant="contained" onClick={() => navigate('/area-selection')}>
              View Map
            </Button>
          </Grid>

          {canAccessStaffActions && (
            <>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('newList.php')}>
                  View List
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('Print_List.php')}>
                  Print List
                </Button>
              </Grid>
            </>
          )}

          <Grid item xs={12} sm={6} md={4}>
            <Button fullWidth variant="outlined" onClick={() => openExternal('nearestMasjid.php')}>
              Find Nearest Masjid
            </Button>
          </Grid>

          {canAccessStaffActions && (
            <>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('edit.php')}>
                  Add New Address
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('add_currentAddress.php')}>
                  Add Current Address
                </Button>
              </Grid>
            </>
          )}

          {canAccessAdminActions && (
            <>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('add_masjid.php')}>
                  Add New Masjid
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('add_user.php')}>
                  Add New User
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('delete_nonmuslims.php')}>
                  Delete Non-Muslim
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('verify_Masjid.php')}>
                  Verify New Masjid
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('list_users.php')}>
                  Edit Users
                </Button>
              </Grid>
            </>
          )}

          {canAccessStaffActions && (
            <>
              <Grid item xs={12} sm={6} md={4}>
                <Button fullWidth variant="outlined" onClick={() => openExternal('list.php')}>
                  Search
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button
                  fullWidth
                  variant="outlined"
                  onClick={() => {
                    window.location.href = `${siteBaseUrl}/halaqa`
                  }}
                >
                  Halaqa Dashboard
                </Button>
              </Grid>
            </>
          )}

          <Grid item xs={12} sm={6} md={4}>
            <Button
              fullWidth
              variant="outlined"
              onClick={() => openExternal(`add_user.php?id=${user?.id || ''}`)}
            >
              Change Password
            </Button>
          </Grid>
        </Grid>
      </Paper>
    </Box>
  )
}

export default Dashboard
