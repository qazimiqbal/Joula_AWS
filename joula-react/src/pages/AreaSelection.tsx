import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Box,
  Typography,
  Button,
  Paper,
  Grid,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  CircularProgress,
  Alert,
} from '@mui/material'
import apiService from '@services/api'

const STATES = [
  { label: 'Georgia', code: 'GA' },
  { label: 'Alabama', code: 'AL' },
  { label: 'South Carolina', code: 'SC' },
  { label: 'Tennessee', code: 'TN' },
]

const RADIUS_OPTIONS = [1, 2, 3, 4, 5, 7, 10, 15, 25]

const AreaSelection: React.FC = () => {
  const navigate = useNavigate()

  const [selectedState, setSelectedState] = useState<string | null>(null)
  const [localities, setLocalities] = useState<string[]>([])
  const [selectedLocality, setSelectedLocality] = useState('All')
  const [localitiesLoading, setLocalitiesLoading] = useState(false)
  const [selectedRadius, setSelectedRadius] = useState(5)
  const [locationError, setLocationError] = useState('')

  // Load localities directly from the MySQL-backed PHP endpoint.
  useEffect(() => {
    if (!selectedState) return
    setLocalitiesLoading(true)
    setSelectedLocality('All')
    apiService
      .getLocalities(selectedState)
      .then((data) => setLocalities(data))
      .catch(() => setLocalities([]))
      .finally(() => setLocalitiesLoading(false))
  }, [selectedState])

  const handleStateSubmit = () => {
    if (!selectedState) return
    const params = new URLSearchParams({ state: selectedState })
    if (selectedLocality && selectedLocality !== 'All') {
      params.set('locality', selectedLocality)
    }
    navigate(`/map?${params.toString()}`)
  }

  const handleRadiusSearch = () => {
    if (!navigator.geolocation) {
      setLocationError('Geolocation is not supported by your browser.')
      return
    }
    setLocationError('')
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const params = new URLSearchParams({
          radius: String(selectedRadius),
          lat: String(position.coords.latitude),
          lng: String(position.coords.longitude),
        })
        navigate(`/map?${params.toString()}`)
      },
      () => {
        setLocationError('Unable to get your location. Please enable location services.')
      }
    )
  }

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        VIEW DATA
      </Typography>

      <Paper elevation={1} sx={{ p: 3, mb: 3 }}>
        <Typography variant="h6" gutterBottom>
          View by State
        </Typography>
        <Grid container spacing={2} sx={{ mb: 2 }}>
          {STATES.map((s) => (
            <Grid item xs={6} sm={3} key={s.code}>
              <Button
                fullWidth
                variant={selectedState === s.code ? 'contained' : 'outlined'}
                onClick={() => setSelectedState(s.code)}
              >
                {s.label}
              </Button>
            </Grid>
          ))}
        </Grid>

        {selectedState && (
          <Box sx={{ mt: 2 }}>
            {localitiesLoading ? (
              <CircularProgress size={24} />
            ) : (
              <FormControl fullWidth size="small" sx={{ mb: 2 }}>
                <InputLabel>Select Locality</InputLabel>
                <Select
                  value={selectedLocality}
                  label="Select Locality"
                  onChange={(e) => setSelectedLocality(e.target.value)}
                >
                  <MenuItem value="All">All Localities</MenuItem>
                  {localities.map((loc) => (
                    <MenuItem key={loc} value={loc}>
                      {loc}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}
            <Button variant="contained" onClick={handleStateSubmit} disabled={localitiesLoading}>
              View Map
            </Button>
          </Box>
        )}
      </Paper>
      <Paper elevation={1} sx={{ p: 3, mb: 3 }}>
        <Typography variant="h6" gutterBottom>
          Find Masjids Within a Radius Around You
        </Typography>
        {locationError && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {locationError}
          </Alert>
        )}
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel>Distance</InputLabel>
            <Select
              value={selectedRadius}
              label="Distance"
              onChange={(e) => setSelectedRadius(Number(e.target.value))}
            >
              {RADIUS_OPTIONS.map((r) => (
                <MenuItem key={r} value={r}>
                  {r} mile{r > 1 ? 's' : ''}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Button variant="contained" onClick={handleRadiusSearch}>
            Search
          </Button>
        </Box>
      </Paper>

      <Button variant="text" onClick={() => navigate('/dashboard')}>
        Back to Dashboard
      </Button>
    </Box>
  )
}

export default AreaSelection
