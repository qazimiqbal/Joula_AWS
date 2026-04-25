import React, { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Box,
  Paper,
  CircularProgress,
  Alert,
  Table,
  TableBody,
  TableCell,
  TableRow,
} from '@mui/material'
import { MapContainer, TileLayer, CircleMarker, Popup, Marker, useMap } from 'react-leaflet'
import L from 'leaflet'
import apiService from '@services/api'
import { AddressRecord, Masjid, PendingGeocodeRecord } from '@/types'

const truncatePopupText = (value?: string, maxLength: number = 80): string => {
  if (!value) return 'None'
  return value.length > maxLength ? `${value.slice(0, maxLength - 3)}...` : value
}

const DEFAULT_CENTER: [number, number] = [33.749, -84.388]
const MASJID_PNG_SRC = `${import.meta.env.BASE_URL}masjid-marker.png`
const MASJID_DIV_ICON = L.divIcon({
  html: `<img src="${MASJID_PNG_SRC}" style="width:36px;height:36px;display:block;" />`,
  className: '',
  iconSize: [36, 36],
  iconAnchor: [18, 18],
  popupAnchor: [0, -20],
})

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
  const [masjids, setMasjids] = useState<Masjid[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null)
  const [mapCenter, setMapCenter] = useState<[number, number]>(DEFAULT_CENTER)
  const [reviewMarker, setReviewMarker] = useState<{ lat: number; lng: number; id?: string; name?: string; type?: 'masjid' | 'address' } | null>(null)
  const [reviewMarkers, setReviewMarkers] = useState<PendingGeocodeRecord[]>([])

  useEffect(() => {
    const stateParam = searchParams.get('state')
    const localityParam = searchParams.get('locality')
    const radiusParam = searchParams.get('radius')
    const latParam = searchParams.get('lat')
    const lngParam = searchParams.get('lng')
    const reviewLatParam = searchParams.get('reviewLat')
    const reviewLngParam = searchParams.get('reviewLng')
    const reviewIdParam = searchParams.get('reviewId')
    const reviewNameParam = searchParams.get('reviewName')
    const reviewTypeParam = searchParams.get('reviewType')
    const reviewAllParam = searchParams.get('reviewAll')

    if (reviewAllParam === '1') {
      setLoading(true)
      setError('')
      setReviewMarker(null)
      Promise.all([apiService.getGeocodeReviewList(), apiService.getMasjids()]).then(([data, masjidData]) => {
        const withCoordinates = data.filter(
          (item) => typeof item.latitude === 'number' && typeof item.longitude === 'number'
        )
        setReviewMarkers(withCoordinates)
        setMasjids(masjidData)
        if (withCoordinates.length > 0) {
          setMapCenter([withCoordinates[0].latitude as number, withCoordinates[0].longitude as number])
        }
        setLoading(false)
      }).catch(() => {
        setError('Failed to load geocoded review markers')
        setLoading(false)
      })
      return
    }

    setReviewMarkers([])

    if (reviewLatParam && reviewLngParam) {
      const reviewLat = parseFloat(reviewLatParam)
      const reviewLng = parseFloat(reviewLngParam)
      if (!Number.isNaN(reviewLat) && !Number.isNaN(reviewLng)) {
        setReviewMarker({
          lat: reviewLat,
          lng: reviewLng,
          id: reviewIdParam || undefined,
          name: reviewNameParam || undefined,
          type: reviewTypeParam === 'masjid' ? 'masjid' : 'address',
        })
        setMapCenter([reviewLat, reviewLng])
      }
    } else {
      setReviewMarker(null)
    }

    if (radiusParam && latParam && lngParam) {
      // Radius search — location provided via URL params
      const lat = parseFloat(latParam)
      const lng = parseFloat(lngParam)
      const radius = parseFloat(radiusParam) * 1.60934 // miles → km
      setUserLocation({ lat, lng })
      setMapCenter([lat, lng])
      setLoading(true)
      Promise.all([
        apiService.searchAddressesByLocation(lat, lng, radius),
        apiService.searchMasjidsByLocation(lat, lng, radius),
      ]).then(([addressData, masjidData]) => {
        setAddresses(addressData)
        setMasjids(masjidData)
        setLoading(false)
      }).catch(() => {
        setError('Failed to load nearby records')
        setLoading(false)
      })
    } else if (stateParam) {
      // State/locality filter
      setLoading(true)
      Promise.all([
        apiService.getAddresses({
          state: stateParam,
          locality: localityParam || undefined,
        }),
        apiService.getMasjids({
          state: stateParam,
          locality: localityParam || undefined,
        }),
      ]).then(([addressData, masjidData]) => {
        setAddresses(addressData)
        setMasjids(masjidData)
        if (addressData.length > 0) {
          setMapCenter([addressData[0].latitude, addressData[0].longitude])
        } else if (masjidData.length > 0 && typeof masjidData[0].latitude === 'number' && typeof masjidData[0].longitude === 'number') {
          setMapCenter([masjidData[0].latitude, masjidData[0].longitude])
        }
        setLoading(false)
      }).catch(() => {
        setError('Failed to load state records')
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
            apiService.searchMasjidsByLocation(location.lat, location.lng, 50)
              .then((data) => setMasjids(data))
              .catch(() => setMasjids([]))
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

          {reviewMarker && reviewMarker.type === 'masjid' && (
            <Marker
              position={[reviewMarker.lat, reviewMarker.lng]}
              {...{ icon: MASJID_DIV_ICON } as object}
            >
              <Popup>
                <strong>Pending Masjid Review</strong><br />
                {reviewMarker.name || 'Masjid'}
                {reviewMarker.id ? ` (ID: ${reviewMarker.id})` : ''}
              </Popup>
            </Marker>
          )}
          {reviewMarker && reviewMarker.type !== 'masjid' && (
            <CircleMarker
              center={[reviewMarker.lat, reviewMarker.lng]}
              {...{ radius: 8 } as object}
              pathOptions={{ color: '#000000', weight: 2, fillColor: '#000000', fillOpacity: 0.95 }}
            >
              <Popup>
                <strong>Pending Geocode Review</strong><br />
                {reviewMarker.name || 'Address'}
                {reviewMarker.id ? ` (ID: ${reviewMarker.id})` : ''}
              </Popup>
            </CircleMarker>
          )}

          {reviewMarkers.map((marker) => (
            <CircleMarker
              key={`review-${marker.id}`}
              center={[marker.latitude as number, marker.longitude as number]}
              {...{ radius: 7 } as object}
              pathOptions={{ color: '#000000', weight: 1.5, fillColor: '#000000', fillOpacity: 0.9 }}
            >
              <Popup>
                <strong>Pending Geocode Review</strong><br />
                {marker.name} (ID: {marker.id})<br />
                {[marker.aptNo, marker.houseNo, marker.streetName, marker.city, marker.state, marker.zip]
                  .filter(Boolean)
                  .join(', ')}
              </Popup>
            </CircleMarker>
          ))}

          {masjids
            .filter((masjid) => typeof masjid.latitude === 'number' && typeof masjid.longitude === 'number')
            .map((masjid) => {
              const masjidAddress = [masjid.aptNo, masjid.houseNo, masjid.streetName, masjid.city, masjid.state, masjid.zip]
                .filter(Boolean)
                .join(', ')

              return (
                <Marker
                  key={`masjid-${masjid.id}`}
                  position={[masjid.latitude as number, masjid.longitude as number]}
                  {...{ icon: MASJID_DIV_ICON } as object}
                >
                  <Popup>
                    <strong>Masjid</strong><br />
                    {masjid.name}
                    {masjidAddress ? <><br />{masjidAddress}</> : null}
                  </Popup>
                </Marker>
              )
            })}

          {addresses.map((address) => {
            const color = getMarkerColor(address.lastVisit)
            const fullAddress = [address.aptNo, address.houseNo, address.streetName, address.city, address.state, address.zip]
              .filter(Boolean)
              .join(', ')
            return (
            <CircleMarker
              key={address.id}
              center={[address.latitude, address.longitude]}
              {...{ radius: 6 } as object}
              pathOptions={{ color: '#333', weight: 1, fillColor: color, fillOpacity: 1 }}
            >
              <Popup>
                <Table
                  size="small"
                  sx={{
                    minWidth: 150,
                    borderCollapse: 'collapse',
                    fontSize: '12px',
                    '& .MuiTableCell-root': {
                      border: '1px solid #d3d3d3',
                      padding: '4px 6px',
                      verticalAlign: 'top',
                      fontSize: '12px',
                      lineHeight: 1.2,
                    },
                  }}
                >
                  <TableBody>
                    <TableRow>
                      <TableCell sx={{ width: '35%', fontWeight: 600 }}>Name</TableCell>
                      <TableCell>{address.name}</TableCell>
                    </TableRow>
                    {address.city && (
                      <TableRow>
                        <TableCell sx={{ fontWeight: 600 }}>City</TableCell>
                        <TableCell>{address.city}</TableCell>
                      </TableRow>
                    )}
                    <TableRow>
                      <TableCell sx={{ fontWeight: 600 }}>Address</TableCell>
                      <TableCell>{fullAddress}</TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell sx={{ fontWeight: 600 }}>Last Visit</TableCell>
                      <TableCell>{address.lastVisit || 'Never'}</TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell sx={{ fontWeight: 600 }}>Comments</TableCell>
                      <TableCell>{truncatePopupText(address.comments)}</TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
                <div style={{ display: 'flex', gap: 4, marginTop: 5 }}>
                  <a
                    href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(
                      [address.aptNo, address.houseNo, address.streetName, address.city, address.state, address.zip].filter(Boolean).join(' ')
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    style={{ display: 'inline-block', padding: '3px 7px', background: '#1976d2', color: 'white', borderRadius: 4, textDecoration: 'none', fontSize: 11 }}
                  >
                    Navigate me here
                  </a>
                  <a
                    href={`${import.meta.env.BASE_URL}comments?id=${address.id}&comments=${encodeURIComponent(address.comments || '')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    style={{ display: 'inline-block', padding: '3px 7px', background: '#388e3c', color: 'white', borderRadius: 4, textDecoration: 'none', fontSize: 11 }}
                  >
                    Enter Comments
                  </a>
                </div>
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
