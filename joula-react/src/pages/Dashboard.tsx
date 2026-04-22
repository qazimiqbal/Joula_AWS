import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Box,
  Grid,
  Typography,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Alert,
} from '@mui/material'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'
import { Masjid } from '@/types'

const Dashboard: React.FC = () => {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [masjids, setMasjids] = useState<Masjid[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchMasjids()
  }, [])

  const fetchMasjids = async () => {
    setLoading(true)
    setError('')
    try {
      const data = await apiService.getMasjids({ limit: 10 })
      setMasjids(data)
    } catch (err: any) {
      setError(err.message || 'Failed to fetch masjids')
    } finally {
      setLoading(false)
    }
  }

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  return (
    <Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4 }}>
        <Typography variant="h4">Welcome, {user?.name}!</Typography>
        <Box>
          <Button
            variant="outlined"
            color="primary"
            onClick={() => navigate('/map')}
            sx={{ mr: 2 }}
          >
            View Map
          </Button>
          <Button
            variant="outlined"
            color="primary"
            onClick={() => navigate('/profile')}
            sx={{ mr: 2 }}
          >
            Profile
          </Button>
          <Button variant="contained" color="error" onClick={handleLogout}>
            Logout
          </Button>
        </Box>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <Typography variant="h5" gutterBottom sx={{ mt: 4, mb: 2 }}>
        Recent Masjids
      </Typography>

      {loading ? (
        <Box display="flex" justifyContent="center" py={4}>
          <CircularProgress />
        </Box>
      ) : (
        <Grid container spacing={2}>
          {masjids.map((masjid) => (
            <Grid item xs={12} sm={6} md={4} key={masjid.id}>
              <Card>
                <CardContent>
                  <Typography variant="h6" component="div" gutterBottom>
                    {masjid.name}
                  </Typography>
                  <Typography color="textSecondary" variant="body2" sx={{ mb: 1 }}>
                    📍 {masjid.address}
                  </Typography>
                  {masjid.distance && (
                    <Typography color="textSecondary" variant="body2" sx={{ mb: 1 }}>
                      📏 {masjid.distance.toFixed(2)} km away
                    </Typography>
                  )}
                  {masjid.phone && (
                    <Typography color="textSecondary" variant="body2" sx={{ mb: 1 }}>
                      📱 {masjid.phone}
                    </Typography>
                  )}
                  <Button
                    variant="outlined"
                    color="primary"
                    size="small"
                    sx={{ mt: 1 }}
                    onClick={() => navigate(`/map?masjid=${masjid.id}`)}
                  >
                    View on Map
                  </Button>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}
    </Box>
  )
}

export default Dashboard
