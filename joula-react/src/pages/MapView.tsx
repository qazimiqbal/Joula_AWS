import React, { useState, useEffect } from 'react'
import {
  Box,
  Paper,
  TextField,
  Button,
  Typography,
  CircularProgress,
  Alert,
  List,
  ListItem,
  ListItemText,
} from '@mui/material'
import { MapContainer, TileLayer, CircleMarker, Popup, useMap } from 'react-leaflet'
import apiService from '@services/api'
import { Masjid } from '@/types'

const DEFAULT_CENTER: [number, number] = [33.749, -84.388]

const MapViewport: React.FC<{ center: [number, number] }> = ({ center }) => {
  const map = useMap()

  useEffect(() => {
    map.setView(center, 11)
  }, [center, map])

  return null
}

const MapView: React.FC = () => {
  const [masjids, setMasjids] = useState<Masjid[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null)
  const [mapCenter, setMapCenter] = useState<[number, number]>(DEFAULT_CENTER)

  useEffect(() => {
    // Get user's location on component mount
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const location = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          }
          setUserLocation(location)
          setMapCenter([location.lat, location.lng])
        },
        (error) => {
          console.error('Error getting location:', error)
          setError('Unable to get your location. Please enable location services.')
        }
      )
    }
  }, [])

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError('')

    try {
      const data = await apiService.getMasjids({ search, limit: 20 })
      setMasjids(data)
      if (data.length > 0) {
        setMapCenter([data[0].latitude, data[0].longitude])
      }
    } catch (err: any) {
      setError(err.message || 'Failed to search masjids')
    } finally {
      setLoading(false)
    }
  }

  const handleNearby = async () => {
    if (!userLocation) {
      setError('Location not available. Please enable location services.')
      return
    }

    setLoading(true)
    setError('')

    try {
      const data = await apiService.searchMasjidsByLocation(
        userLocation.lat,
        userLocation.lng,
        10
      )
      setMasjids(data)
      if (data.length > 0) {
        setMapCenter([data[0].latitude, data[0].longitude])
      }
    } catch (err: any) {
      setError(err.message || 'Failed to find nearby masjids')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box
      sx={{
        display: 'grid',
        gridTemplateColumns: { xs: '1fr', md: '300px 1fr' },
        gap: 2,
        minHeight: { xs: 700, md: 600 },
      }}
    >
      {/* Sidebar */}
      <Paper elevation={1} sx={{ p: 2, display: 'flex', flexDirection: 'column' }}>
        <Typography variant="h6" gutterBottom>
          Search Masjids
        </Typography>

        <form onSubmit={handleSearch} style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <TextField
            size="small"
            placeholder="Search by name or address..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            fullWidth
            disabled={loading}
          />
          <Button type="submit" variant="contained" disabled={loading} sx={{ minWidth: 80 }}>
            Search
          </Button>
        </form>

        <Button
          variant="outlined"
          onClick={handleNearby}
          disabled={loading || !userLocation}
          fullWidth
          sx={{ mb: 2 }}
        >
          Find Nearby
        </Button>

        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        {loading ? (
          <Box display="flex" justifyContent="center" py={2}>
            <CircularProgress />
          </Box>
        ) : (
          <List sx={{ flexGrow: 1, overflow: 'auto' }}>
            {masjids.map((masjid) => (
              <ListItem
                key={masjid.id}
                divider
                button
                onClick={() => setMapCenter([masjid.latitude, masjid.longitude])}
              >
                <ListItemText
                  primary={masjid.name}
                  secondary={`📍 ${masjid.address}${
                    masjid.distance ? ` (${masjid.distance.toFixed(2)} km)` : ''
                  }`}
                />
              </ListItem>
            ))}
          </List>
        )}
      </Paper>

      <Paper elevation={1} sx={{ p: 1, minHeight: { xs: 420, md: 'auto' } }}>
        <MapContainer
          style={{ height: '100%', minHeight: 400, width: '100%' }}
        >
          <MapViewport center={mapCenter} />
          <TileLayer url='https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' />

          {userLocation && (
            <CircleMarker center={[userLocation.lat, userLocation.lng]} pathOptions={{ color: '#1976d2' }}>
              <Popup>Your location</Popup>
            </CircleMarker>
          )}

          {masjids.map((masjid) => (
            <CircleMarker
              key={masjid.id}
              center={[masjid.latitude, masjid.longitude]}
              pathOptions={{ color: '#d32f2f' }}
            >
              <Popup>
                <Typography variant="subtitle2">{masjid.name}</Typography>
                <Typography variant="body2">{masjid.address}</Typography>
              </Popup>
            </CircleMarker>
          ))}
        </MapContainer>
      </Paper>
    </Box>
  )
}

export default MapView
