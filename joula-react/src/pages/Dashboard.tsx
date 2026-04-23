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
              View Data
            </Button>
          </Grid>

          {canAccessStaffActions && (
            <>
              <Grid item xs={12} sm={6} md={4}>
                <Button
                  fullWidth
                  variant="outlined"
                  onClick={() => navigate('/addresses/new')}
                >
                  Add New Address (AWS)
                </Button>
              </Grid>
              <Grid item xs={12} sm={6} md={4}>
                <Button
                  fullWidth
                  variant="outlined"
                  color="secondary"
                  onClick={() => navigate('/missing-coordinates')}
                >
                  Fix Missing Coordinates
                </Button>
              </Grid>
            </>
          )}

          <Grid item xs={12} sm={6} md={4}>
            <Button
              fullWidth
              variant="outlined"
              onClick={() => navigate('/account')}
            >
              Account Settings
            </Button>
          </Grid>

          {canAccessAdminActions && (
            <Grid item xs={12} sm={6} md={4}>
              <Button
                fullWidth
                variant="outlined"
                color="warning"
                onClick={() => navigate('/pending-users')}
              >
                Approve New Users
              </Button>
            </Grid>
          )}
        </Grid>
      </Paper>
    </Box>
  )
}

export default Dashboard
