import React, { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Box,
  Paper,
  CircularProgress,
  Alert,
  Typography,
} from '@mui/material'
import { MapContainer, TileLayer, CircleMarker, Popup, useMap } from 'react-leaflet'
import apiService from '@services/api'
import { AddressRecord } from '@/types'

const DEFAULT_CENTER: [number, number] = [33.749, -84.388]

function getMarkerColor(lastVisit?: string): string {
  if (!lastVisit) return '#d32f2f' // red - never visited
  const visited = new Date(lastVisit)
  if (isNaN(visited.getTime())) return '#d32f2f'
  const daysSince = (Date.now() - visited.getTime()) / (1000 * 60 * 60 * 24)
  if (daysSince < 30) return '#42DB35'   // green  - less than 30 days
  if (daysSince < 60) return '#ED914C'   // orange - 30-60 days
  if (daysSince < 90) return '#FAED0A'   // yellow - 60-90 days
  return '#29b6f6'                        // light blue - more than 90 days
}

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

  return (
    <Box sx={{ height: 'calc(100vh - 112px)', display: 'flex', flexDirection: 'column' }}>
      {error && <Alert severity="error" sx={{ mb: 1 }}>{error}</Alert>}
      {loading && <Box display="flex" justifyContent="center" py={2}><CircularProgress /></Box>}

      <Paper elevation={1} sx={{ p: 0, flexGrow: 1 }}>
        <MapContainer
          {...{ center: mapCenter, zoom: 11 } as object}
          style={{ height: '100%', minHeight: 'inherit', width: '100%' }}
        >
          <MapViewport center={mapCenter} />
          <TileLayer url='https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' />

          {userLocation && (
            <CircleMarker center={[userLocation.lat, userLocation.lng]} pathOptions={{ color: '#1976d2' }}>
              <Popup>Your location</Popup>
            </CircleMarker>
          )}

          {addresses.map((address) => {
            const color = getMarkerColor(address.lastVisit)
            return (
            <CircleMarker
              key={address.id}
              center={[address.latitude, address.longitude]}
              {...{ radius: 5 } as object}
              pathOptions={{ color: '#333', weight: 1, fillColor: color, fillOpacity: 1 }}
            >
              <Popup>
                <Typography variant="subtitle2" sx={{ fontWeight: 'bold', mb: 0.5 }}>{address.name}</Typography>
                {address.city && (
                  <Typography variant="body2"><strong>City:</strong> {address.city}</Typography>
                )}
                <Typography variant="body2">
                  <strong>Address:</strong>{' '}
                  {[address.aptNo, address.houseNo, address.streetName, address.city, address.state, address.zip]
                    .filter(Boolean).join(', ')}
                </Typography>
                {address.lastVisit && (
                  <Typography variant="body2"><strong>Last Visit:</strong> {address.lastVisit}</Typography>
                )}
                {!address.lastVisit && (
                  <Typography variant="body2"><strong>Last Visit:</strong> Never</Typography>
                )}
                <a
                  href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(
                    [address.aptNo, address.houseNo, address.streetName, address.city, address.state, address.zip].filter(Boolean).join(' ')
                  )}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  style={{ display: 'inline-block', marginTop: 6, padding: '4px 8px', background: '#1976d2', color: 'white', borderRadius: 4, textDecoration: 'none', fontSize: 12 }}
                >
                  Navigate me here
                </a>
              </Popup>
            </CircleMarker>
            )
          })}
        </MapContainer>
      </Paper>
    </Box>
  )
}

export default MapView
