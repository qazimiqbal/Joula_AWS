import React, { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
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
import { AddressRecord } from '@/types'

const DEFAULT_CENTER: [number, number] = [33.749, -84.388]

const MapViewport: React.FC<{ center: [number, number] }> = ({ center }) => {
  const map = useMap()

  useEffect(() => {
    map.setView(center, 11)
  }, [center, map])

  return null
}

const MapView: React.FC = () => {
  const [searchParams] = useSearchParams()
  const [addresses, setAddresses] = useState<AddressRecord[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null)
  const [mapCenter, setMapCenter] = useState<[number, number]>(DEFAULT_CENTER)

  useEffect(() => {
    const stateParam = searchParams.get('state')
    const localityParam = searchParams.get('locality')
    const radiusParam = searchParams.get('radius')
    const latParam = searchParams.get('lat')
    const lngParam = searchParams.get('lng')

    if (radiusParam && latParam && lngParam) {
      // Radius search — location provided via URL params
      const lat = parseFloat(latParam)
      const lng = parseFloat(lngParam)
      const radius = parseFloat(radiusParam) * 1.60934 // miles → km
      setUserLocation({ lat, lng })
      setMapCenter([lat, lng])
      setLoading(true)
      apiService.searchAddressesByLocation(lat, lng, radius).then((data) => {
        setAddresses(data)
        setLoading(false)
      }).catch(() => {
        setError('Failed to load nearby addresses')
        setLoading(false)
      })
    } else if (stateParam) {
      // State/locality filter
      setLoading(true)
      apiService.getAddresses({
        state: stateParam,
        locality: localityParam || undefined,
      }).then((data) => {
        setAddresses(data)
        if (data.length > 0) setMapCenter([data[0].latitude, data[0].longitude])
        setLoading(false)
      }).catch(() => {
        setError('Failed to load addresses')
        setLoading(false)
      })
    } else {
      // No URL params — get user location
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          (position) => {
            const location = { lat: position.coords.latitude, lng: position.coords.longitude }
            setUserLocation(location)
            setMapCenter([location.lat, location.lng])
          },
          () => {
            setError('Unable to get your location. Please enable location services.')
          }
        )
      }
    }
  }, [searchParams])

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError('')

    try {
      const data = await apiService.getAddresses({ search, limit: 20 })
      setAddresses(data)
      if (data.length > 0) {
        setMapCenter([data[0].latitude, data[0].longitude])
      }
    } catch (err: any) {
      setError(err.message || 'Failed to search addresses')
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
      const data = await apiService.searchAddressesByLocation(
        userLocation.lat,
        userLocation.lng,
        10
      )
      setAddresses(data)
      if (data.length > 0) {
        setMapCenter([data[0].latitude, data[0].longitude])
      }
    } catch (err: any) {
      setError(err.message || 'Failed to find nearby addresses')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box
      sx={{
        display: 'grid',
        gridTemplateColumns: { xs: '1fr', md: '300px 1fr' },
        gridTemplateRows: { xs: 'auto 1fr', md: '1fr' },
        gap: 2,
        minHeight: { xs: 'auto', md: 600 },
      }}
    >
      {/* Sidebar */}
      <Paper elevation={1} sx={{ p: 2, display: 'flex', flexDirection: 'column' }}>
        <Typography variant="h6" gutterBottom>
          Search Addresses
        </Typography>

        <form onSubmit={handleSearch} style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <TextField
            size="small"
            placeholder="Search addresses by name or street..."
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
            {addresses.map((address) => (
              <ListItem
                key={address.id}
                divider
                button
                onClick={() => setMapCenter([address.latitude, address.longitude])}
              >
                <ListItemText
                  primary={address.name}
                  secondary={`📍 ${address.address}${
                    address.distance ? ` (${address.distance.toFixed(2)} km)` : ''
                  }`}
                />
              </ListItem>
            ))}
          </List>
        )}
      </Paper>

      <Paper elevation={1} sx={{ p: 1, minHeight: { xs: 320, md: 'auto' } }}
      >
        <MapContainer
          style={{ height: '100%', minHeight: 'inherit', width: '100%' }}
        >
          <MapViewport center={mapCenter} />
          <TileLayer url='https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' />

          {userLocation && (
            <CircleMarker center={[userLocation.lat, userLocation.lng]} pathOptions={{ color: '#1976d2' }}>
              <Popup>Your location</Popup>
            </CircleMarker>
          )}

          {addresses.map((address) => (
            <CircleMarker
              key={address.id}
              center={[address.latitude, address.longitude]}
              pathOptions={{ color: '#d32f2f' }}
            >
              <Popup>
                <Typography variant="subtitle2">{address.name}</Typography>
                <Typography variant="body2">{address.address}</Typography>
              </Popup>
            </CircleMarker>
          ))}
        </MapContainer>
      </Paper>
    </Box>
  )
}

export default MapView
