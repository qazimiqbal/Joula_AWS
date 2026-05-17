import React, { useState, useEffect, useMemo, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Box,
  CircularProgress,
  Alert,
  Button,
  FormControl,
  InputLabel,
  MenuItem,
  Select,
  Table,
  TableBody,
  TableCell,
  TableRow,
  TextField,
} from '@mui/material'
import { useJsApiLoader, GoogleMap, Marker, InfoWindow, Circle } from '@react-google-maps/api'
import apiService from '@services/api'
import { AddressRecord, Masjid, PendingGeocodeRecord } from '@/types'

const ACTION_OPTIONS = [
  { value: 'met', label: 'Met' },
  { value: 'left_message', label: 'Left Message' },
  { value: 'No_Response', label: 'No Response' },
  { value: 'Ismailee', label: 'Ismailee' },
  { value: 'Owner_muslim_rented_non_muslim', label: 'Owner Muslim, Rented to Non Muslim' },
  { value: 'Non_muslim', label: 'Non Muslim' },
  { value: 'WrongAddress', label: 'Wrong Address' },
]

const todayStr = () => new Date().toISOString().split('T')[0]

const truncatePopupText = (value?: string, maxLength: number = 80): string => {
  if (!value) return 'None'
  return value.length > maxLength ? `${value.slice(0, maxLength - 3)}...` : value
}

const DEFAULT_CENTER = { lat: 33.749, lng: -84.388 }
const MASJID_PNG_SRC = `${import.meta.env.BASE_URL}masjid-marker.png`
const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string
const MAP_CONTAINER_STYLE: React.CSSProperties = { width: '100%', height: '100%' }

const makeCircleSvgUrl = (color: string): string =>
  `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"><circle cx="7" cy="7" r="6" fill="${color}" stroke="#333" stroke-width="1"/></svg>`
  )}`

const makeCurrentLocationSvgUrl = (): string =>
  `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44">
      <circle cx="22" cy="22" r="19" fill="#00c853" fill-opacity="0.2"/>
      <circle cx="22" cy="22" r="12" fill="#00e676" stroke="#ffffff" stroke-width="3"/>
      <circle cx="22" cy="22" r="4" fill="#0b3d91"/>
    </svg>`
  )}`

function getMarkerColor(lastVisit?: string): string {
  if (!lastVisit) return '#d32f2f'
  const visited = new Date(lastVisit)
  if (isNaN(visited.getTime())) return '#d32f2f'
  const daysSince = (Date.now() - visited.getTime()) / (1000 * 60 * 60 * 24)
  if (daysSince < 90) return '#42DB35'
  if (daysSince < 180) return '#FAED0A'
  return '#64b5f6'
}

const MapView: React.FC = () => {
  const [searchParams] = useSearchParams()
  const { isLoaded } = useJsApiLoader({ googleMapsApiKey: GOOGLE_MAPS_API_KEY })
  const [addresses, setAddresses] = useState<AddressRecord[]>([])
  const [masjids, setMasjids] = useState<Masjid[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null)
  const [mapCenter, setMapCenter] = useState(DEFAULT_CENTER)
  const [reviewMarker, setReviewMarker] = useState<{ lat: number; lng: number; id?: string; name?: string; type?: 'masjid' | 'address' } | null>(null)
  const [reviewMarkers, setReviewMarkers] = useState<PendingGeocodeRecord[]>([])
  const [selectedMasjid, setSelectedMasjid] = useState<Masjid | null>(null)
  const [selectedAddress, setSelectedAddress] = useState<AddressRecord | null>(null)
  const [selectedReview, setSelectedReview] = useState<PendingGeocodeRecord | null>(null)
  const [reviewMarkerOpen, setReviewMarkerOpen] = useState(false)
  const [isEditingAddress, setIsEditingAddress] = useState(false)
  const [visitLoading, setVisitLoading] = useState(false)
  const [visitSaving, setVisitSaving] = useState(false)
  const [visitError, setVisitError] = useState('')
  const [visitSuccess, setVisitSuccess] = useState('')
  const [actionTaken, setActionTaken] = useState('met')
  const [visitComments, setVisitComments] = useState('')
  const [visitEthinicity, setVisitEthinicity] = useState('Others')
  const [visitPotential, setVisitPotential] = useState('No')

  const masjidIcon = useMemo(() => {
    if (!isLoaded) return undefined
    return {
      url: MASJID_PNG_SRC,
      scaledSize: new window.google.maps.Size(36, 36),
      anchor: new window.google.maps.Point(18, 36),
    }
  }, [isLoaded])

  const makeCircleIcon = useCallback((color: string) => {
    if (!isLoaded) return makeCircleSvgUrl(color)
    return {
      url: makeCircleSvgUrl(color),
      scaledSize: new window.google.maps.Size(14, 14),
      anchor: new window.google.maps.Point(7, 7),
    }
  }, [isLoaded])

  const currentLocationIcon = useMemo(() => {
    if (!isLoaded) return makeCurrentLocationSvgUrl()
    return {
      url: makeCurrentLocationSvgUrl(),
      scaledSize: new window.google.maps.Size(44, 44),
      anchor: new window.google.maps.Point(22, 22),
    }
  }, [isLoaded])

  useEffect(() => {
    if (!navigator.geolocation) return
    if (!searchParams.toString()) return

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setUserLocation({ lat: position.coords.latitude, lng: position.coords.longitude })
      },
      () => {
        // Keep map usable even if location permission is denied.
      }
    )
  }, [searchParams])

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
    const allMasjidsParam = searchParams.get('allMasjids')
    const allAddressesParam = searchParams.get('allAddresses')
    const masjidIdParam = searchParams.get('masjidId')
    const mineParam = searchParams.get('mine') === '1'
    const masjidFilterParam = searchParams.get('masjidFilter')
    const showMasjidsParam = searchParams.get('showMasjids') === '1'

    if (allAddressesParam === '1' || masjidIdParam) {
      setLoading(true)
      setError('')
      setReviewMarker(null)
      setReviewMarkers([])
      const addrParams: { mine?: boolean; masjidId?: number } = { mine: mineParam }
      if (masjidIdParam) addrParams.masjidId = Number(masjidIdParam)
      const masjidPromise = showMasjidsParam
        ? apiService.getMasjids(mineParam ? { mine: true } : {})
        : Promise.resolve([] as Masjid[])
      Promise.all([apiService.getAddresses(addrParams), masjidPromise]).then(([addressData, masjidData]) => {
        setAddresses(addressData)
        setMasjids(masjidData)
        if (addressData.length > 0 && typeof addressData[0].latitude === 'number' && typeof addressData[0].longitude === 'number') {
          setMapCenter({ lat: addressData[0].latitude, lng: addressData[0].longitude })
        } else if (masjidData.length > 0 && typeof masjidData[0].latitude === 'number' && typeof masjidData[0].longitude === 'number') {
          setMapCenter({ lat: masjidData[0].latitude, lng: masjidData[0].longitude })
        }
        setLoading(false)
      }).catch(() => {
        setError('Failed to load data')
        setLoading(false)
      })
      return
    }

    if (allMasjidsParam === '1') {
      setLoading(true)
      setError('')
      setReviewMarker(null)
      setReviewMarkers([])
      apiService.getMasjids({ mine: mineParam }).then((masjidData) => {
        const filtered = masjidFilterParam
          ? masjidData.filter((m) => String(m.id) === masjidFilterParam)
          : masjidData
        setMasjids(filtered)
        setAddresses([])
        if (filtered.length > 0 && typeof filtered[0].latitude === 'number' && typeof filtered[0].longitude === 'number') {
          setMapCenter({ lat: filtered[0].latitude, lng: filtered[0].longitude })
        }
        setLoading(false)
      }).catch(() => {
        setError('Failed to load masjids')
        setLoading(false)
      })
      return
    }

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
          setMapCenter({ lat: withCoordinates[0].latitude as number, lng: withCoordinates[0].longitude as number })
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
        setMapCenter({ lat: reviewLat, lng: reviewLng })
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
      setMapCenter({ lat, lng })
      setLoading(true)
      Promise.all([
        apiService.searchAddressesByLocation(lat, lng, radius, { mine: mineParam }),
        apiService.searchMasjidsByLocation(lat, lng, radius, { mine: mineParam }),
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
          mine: mineParam,
        }),
        apiService.getMasjids({
          state: stateParam,
          locality: localityParam || undefined,
          mine: mineParam,
        }),
      ]).then(([addressData, masjidData]) => {
        setAddresses(addressData)
        setMasjids(masjidData)
        if (addressData.length > 0 && typeof addressData[0].latitude === 'number' && typeof addressData[0].longitude === 'number') {
          setMapCenter({ lat: addressData[0].latitude, lng: addressData[0].longitude })
        } else if (masjidData.length > 0 && typeof masjidData[0].latitude === 'number' && typeof masjidData[0].longitude === 'number') {
          setMapCenter({ lat: masjidData[0].latitude, lng: masjidData[0].longitude })
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
            setMapCenter(location)
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

  const resetVisitEditor = useCallback(() => {
    setIsEditingAddress(false)
    setVisitLoading(false)
    setVisitSaving(false)
    setVisitError('')
    setVisitSuccess('')
    setActionTaken('met')
    setVisitComments('')
    setVisitEthinicity('Others')
    setVisitPotential('No')
  }, [])

  const handleAddressSelect = useCallback((address: AddressRecord) => {
    setSelectedAddress(address)
    resetVisitEditor()
    setVisitComments(address.comments || '')
  }, [resetVisitEditor])

  const handleStartVisitEdit = useCallback(async (address: AddressRecord) => {
    setIsEditingAddress(true)
    setVisitLoading(true)
    setVisitSaving(false)
    setVisitError('')
    setVisitSuccess('')
    setActionTaken('met')
    setVisitComments(address.comments || '')

    try {
      const data = await apiService.getVisitData(address.id)
      setVisitComments(data.comments || address.comments || '')
      setVisitEthinicity(data.ethinicity || 'Others')
      setVisitPotential(data.potential || 'No')
    } catch {
      setVisitEthinicity('Others')
      setVisitPotential('No')
    } finally {
      setVisitLoading(false)
    }
  }, [])

  const handleSaveVisit = useCallback(async (address: AddressRecord) => {
    setVisitSaving(true)
    setVisitError('')
    setVisitSuccess('')

    try {
      const today = todayStr()
      await apiService.updateVisit(address.id, {
        today,
        actionTaken,
        comments: visitComments,
        ethinicity: visitEthinicity,
        potential: visitPotential,
      })

      setAddresses((current) => current.map((item) => (
        item.id === address.id
          ? { ...item, comments: visitComments, lastVisit: today }
          : item
      )))
      setSelectedAddress((current) => (
        current && current.id === address.id
          ? { ...current, comments: visitComments, lastVisit: today }
          : current
      ))
      setVisitSuccess('Visit updated.')
      setIsEditingAddress(false)
    } catch (error) {
      setVisitError(error instanceof Error ? error.message : 'Failed to save visit data')
    } finally {
      setVisitSaving(false)
    }
  }, [actionTaken, visitComments, visitEthinicity, visitPotential])

  return (
    <Box sx={{ height: 'calc(100vh - 112px)', display: 'flex', flexDirection: 'column' }}>
      {error && <Alert severity="error" sx={{ mb: 1 }}>{error}</Alert>}
      {loading && (
        <Box display="flex" justifyContent="center" py={1}>
          <CircularProgress size={24} />
        </Box>
      )}

      <Box sx={{ flex: 1, minHeight: 0 }}>
        {!isLoaded ? (
          <Box display="flex" justifyContent="center" alignItems="center" height="100%">
            <CircularProgress />
          </Box>
        ) : (
          <GoogleMap
            mapContainerStyle={MAP_CONTAINER_STYLE}
            center={mapCenter}
            zoom={11}
            options={{ gestureHandling: 'greedy' }}
          >
            {/* User location */}
            {userLocation && (
              <>
                <Circle
                  center={userLocation}
                  radius={175}
                  options={{
                    strokeColor: '#00c853',
                    strokeOpacity: 0.95,
                    strokeWeight: 2,
                    fillColor: '#00e676',
                    fillOpacity: 0.14,
                  }}
                />
                <Marker
                  position={userLocation}
                  icon={currentLocationIcon}
                  zIndex={999}
                  title="Your current location"
                />
              </>
            )}

            {/* Single review marker */}
            {reviewMarker && (
              <Marker
                position={{ lat: reviewMarker.lat, lng: reviewMarker.lng }}
                icon={reviewMarker.type === 'masjid' ? masjidIcon : makeCircleIcon('#000000')}
                onClick={() => setReviewMarkerOpen(true)}
              >
                {reviewMarkerOpen && (
                  <InfoWindow onCloseClick={() => setReviewMarkerOpen(false)}>
                    <div>
                      <strong>{reviewMarker.type === 'masjid' ? 'Pending Masjid Review' : 'Pending Geocode Review'}</strong><br />
                      {reviewMarker.name || (reviewMarker.type === 'masjid' ? 'Masjid' : 'Address')}
                      {reviewMarker.id ? ` (ID: ${reviewMarker.id})` : ''}
                    </div>
                  </InfoWindow>
                )}
              </Marker>
            )}

            {/* Bulk geocode review markers */}
            {reviewMarkers.map((marker) => (
              <Marker
                key={`review-${marker.id}`}
                position={{ lat: marker.latitude as number, lng: marker.longitude as number }}
                icon={makeCircleIcon('#000000')}
                onClick={() => setSelectedReview(marker)}
              >
                {selectedReview?.id === marker.id && (
                  <InfoWindow onCloseClick={() => setSelectedReview(null)}>
                    <div>
                      <strong>Pending Geocode Review</strong><br />
                      {marker.name} (ID: {marker.id})<br />
                      {[marker.aptNo, marker.houseNo, marker.streetName, marker.city, marker.state, marker.zip]
                        .filter(Boolean)
                        .join(', ')}
                    </div>
                  </InfoWindow>
                )}
              </Marker>
            ))}

            {/* Masjid markers */}
            {masjids
              .filter((m) => typeof m.latitude === 'number' && typeof m.longitude === 'number')
              .map((masjid) => {
                const masjidAddress = [masjid.aptNo, masjid.houseNo, masjid.streetName, masjid.city, masjid.state, masjid.zip]
                  .filter(Boolean)
                  .join(', ')
                return (
                  <Marker
                    key={`masjid-${masjid.id}`}
                    position={{ lat: masjid.latitude as number, lng: masjid.longitude as number }}
                    icon={masjidIcon}
                    onClick={() => setSelectedMasjid(masjid)}
                  >
                    {selectedMasjid?.id === masjid.id && (
                      <InfoWindow onCloseClick={() => setSelectedMasjid(null)}>
                        <div>
                          <strong>Masjid</strong><br />
                          {masjid.name}
                          {masjidAddress ? <><br />{masjidAddress}</> : null}
                        </div>
                      </InfoWindow>
                    )}
                  </Marker>
                )
              })}

            {/* Address markers */}
            {addresses
              .filter((a) => typeof a.latitude === 'number' && typeof a.longitude === 'number')
              .map((address) => {
                const color = getMarkerColor(address.lastVisit)
                const fullAddress = [address.aptNo, address.houseNo, address.streetName, address.city, address.state, address.zip]
                  .filter(Boolean)
                  .join(', ')
                return (
                  <Marker
                    key={address.id}
                    position={{ lat: address.latitude as number, lng: address.longitude as number }}
                    icon={makeCircleIcon(color)}
                    onClick={() => handleAddressSelect(address)}
                  >
                    {selectedAddress?.id === address.id && (
                      <InfoWindow onCloseClick={() => setSelectedAddress(null)}>
                        <div style={{ minWidth: 180 }}>
                          <Table
                            size="small"
                            sx={{
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
                                <TableCell sx={{ fontWeight: 600 }}>Legend</TableCell>
                                <TableCell>
                                  <div style={{ display: 'grid', gap: 2 }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                      <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#42DB35', border: '1px solid #333', display: 'inline-block' }} />
                                      <span>Visited within 3 months</span>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                      <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#FAED0A', border: '1px solid #333', display: 'inline-block' }} />
                                      <span>Visited 3-6 months ago</span>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                      <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#29b6f6', border: '1px solid #333', display: 'inline-block' }} />
                                      <span>Visited over 6 months ago</span>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                      <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#d32f2f', border: '1px solid #333', display: 'inline-block' }} />
                                      <span>Never visited or unknown date</span>
                                    </div>
                                  </div>
                                </TableCell>
                              </TableRow>
                              <TableRow>
                                <TableCell sx={{ fontWeight: 600 }}>Comments</TableCell>
                                <TableCell>{truncatePopupText(address.comments)}</TableCell>
                              </TableRow>
                            </TableBody>
                          </Table>
                          {visitError ? (
                            <Alert severity="error" sx={{ mt: 1, py: 0 }}>{visitError}</Alert>
                          ) : null}
                          {visitSuccess ? (
                            <Alert severity="success" sx={{ mt: 1, py: 0 }}>{visitSuccess}</Alert>
                          ) : null}
                          {isEditingAddress ? (
                            <Box sx={{ mt: 1, display: 'grid', gap: 1 }}>
                              {visitLoading ? (
                                <Box display="flex" justifyContent="center" py={1}>
                                  <CircularProgress size={20} />
                                </Box>
                              ) : (
                                <>
                                  <FormControl fullWidth size="small">
                                    <InputLabel>Action Taken</InputLabel>
                                    <Select
                                      value={actionTaken}
                                      label="Action Taken"
                                      onChange={(event) => setActionTaken(event.target.value)}
                                    >
                                      {ACTION_OPTIONS.map((option) => (
                                        <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
                                      ))}
                                    </Select>
                                  </FormControl>
                                  <TextField
                                    label="Comments"
                                    multiline
                                    minRows={3}
                                    value={visitComments}
                                    onChange={(event) => setVisitComments(event.target.value)}
                                    fullWidth
                                    size="small"
                                  />
                                  <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                      variant="contained"
                                      size="small"
                                      disabled={visitSaving}
                                      onClick={() => handleSaveVisit(address)}
                                    >
                                      {visitSaving ? 'Saving...' : 'Save Visit'}
                                    </Button>
                                    <Button variant="text" size="small" onClick={resetVisitEditor} disabled={visitSaving}>
                                      Cancel
                                    </Button>
                                  </Box>
                                </>
                              )}
                            </Box>
                          ) : null}
                          <div style={{ display: 'flex', gap: 4, marginTop: 5, flexWrap: 'wrap' }}>
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
                            <button
                              type="button"
                              onClick={() => handleStartVisitEdit(address)}
                              style={{ display: 'inline-block', padding: '3px 7px', background: '#388e3c', color: 'white', borderRadius: 4, textDecoration: 'none', fontSize: 11, border: 0, cursor: 'pointer' }}
                            >
                              {isEditingAddress ? 'Editing Visit' : 'Enter Comments'}
                            </button>
                          </div>
                        </div>
                      </InfoWindow>
                    )}
                  </Marker>
                )
              })}
          </GoogleMap>
        )}
      </Box>
    </Box>
  )
}

export default MapView
