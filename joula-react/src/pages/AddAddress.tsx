import React, { useState, useEffect, useRef, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Divider,
  Grid,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import UploadFileIcon from '@mui/icons-material/UploadFile'
import DownloadIcon from '@mui/icons-material/Download'
import apiService from '@services/api'

const CSV_HEADERS = ['name','houseNo','aptNo','streetName','city','state','zip','locality','comments','lastVisit','halaqa']
const CSV_SAMPLE   = ['John Smith','123','','Maple St','Atlanta','GA','30301','Eastside','','','' ]

function downloadTemplate() {
  const csv = [CSV_HEADERS, CSV_SAMPLE].map((r) => r.join(',')).join('\r\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = 'address_import_template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

const todayStr = () => new Date().toISOString().split('T')[0]

const AddAddress: React.FC = () => {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [geocoding, setGeocoding] = useState(false)
  const [locating, setLocating] = useState(false)
  const [needsRegeocode, setNeedsRegeocode] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [form, setForm] = useState({
    name: '',
    houseNo: '',
    aptNo: '',
    streetName: '',
    city: '',
    state: 'GA',
    zip: '',
    locality: '',
    masjid: '',
    lastVisit: todayStr(),
    comments: '',
    latitude: '',
    longitude: '',
  })

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const updateField = (field: string, value: string, markAsManualEdit: boolean = true) => {
    setForm((prev) => ({ ...prev, [field]: value }))

    if (
      markAsManualEdit &&
      (field === 'houseNo' || field === 'aptNo' || field === 'streetName' || field === 'city' || field === 'state' || field === 'zip') &&
      form.latitude.trim() !== '' &&
      form.longitude.trim() !== ''
    ) {
      setNeedsRegeocode(true)
    }
  }

  const buildAddressQuery = useCallback((f: typeof form) => {
    return [f.aptNo, f.houseNo, f.streetName, f.city, f.state, f.zip]
      .map((part) => part.trim())
      .filter(Boolean)
      .join(', ')
  }, [])

  const geocodeAddress = useCallback(async (f: typeof form) => {
    const query = buildAddressQuery(f)
    // Need at least house number + street + city to geocode meaningfully
    if (!f.houseNo.trim() || !f.streetName.trim() || !f.city.trim()) return

    setGeocoding(true)
    try {
      const { lat, lng } = await apiService.geocodeAddress(query)
      setForm((prev) => ({
        ...prev,
        latitude: lat.toFixed(6),
        longitude: lng.toFixed(6),
      }))
      setNeedsRegeocode(false)
    } catch {
      // silent — user can still enter manually
    } finally {
      setGeocoding(false)
    }
  }, [buildAddressQuery])

  // Auto-geocode with 800ms debounce whenever address fields change
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      geocodeAddress(form)
    }, 800)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
    // Only re-run when address-relevant fields change
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.houseNo, form.aptNo, form.streetName, form.city, form.state, form.zip])

  const handleUseCurrentLocation = () => {
    setError('')
    if (!navigator.geolocation) {
      setError('Geolocation is not supported by your browser.')
      return
    }

    setLocating(true)
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const lat = position.coords.latitude
        const lng = position.coords.longitude
        setForm((prev) => ({
          ...prev,
          latitude: lat.toFixed(6),
          longitude: lng.toFixed(6),
        }))

        try {
          const reverse = await apiService.reverseGeocode(lat, lng)
          setForm((prev) => ({
            ...prev,
            latitude: lat.toFixed(6),
            longitude: lng.toFixed(6),
            houseNo: reverse.houseNo || prev.houseNo,
            streetName: reverse.streetName || prev.streetName,
            city: reverse.city || prev.city,
            state: reverse.state || prev.state,
            zip: reverse.zip || prev.zip,
          }))
          setNeedsRegeocode(false)
        } catch {
          // Keep coordinates even if reverse geocode returns no address
        } finally {
          setLocating(false)
        }
      },
      () => {
        setError('Unable to get current location. Please allow location permission and try again.')
        setLocating(false)
      },
      { enableHighAccuracy: true, timeout: 10000 }
    )
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')

    const hasCoordinates = form.latitude.trim() !== '' && form.longitude.trim() !== ''
    const hasFullAddress =
      form.houseNo.trim() !== '' &&
      form.streetName.trim() !== '' &&
      form.city.trim() !== '' &&
      form.state.trim() !== '' &&
      form.zip.trim() !== '' &&
      form.locality.trim() !== ''

    if (!form.name.trim()) {
      setError('Name is required.')
      return
    }

    if (!hasFullAddress && !hasCoordinates) {
      setError('Provide full address fields, or use current location to capture coordinates.')
      return
    }

    setLoading(true)
    try {
      await apiService.createAddress({
        name: form.name.trim(),
        houseNo: form.houseNo.trim(),
        aptNo: form.aptNo.trim(),
        streetName: form.streetName.trim(),
        city: form.city.trim(),
        state: form.state.trim(),
        zip: form.zip.trim(),
        locality: form.locality.trim(),
        masjid: form.masjid.trim(),
        lastVisit: form.lastVisit,
        comments: form.comments.trim(),
        latitude: form.latitude.trim() ? Number(form.latitude) : undefined,
        longitude: form.longitude.trim() ? Number(form.longitude) : undefined,
      })
      setSuccess('Address submitted successfully and is pending admin approval.')
      setForm((prev) => ({
        ...prev,
        name: '',
        houseNo: '',
        aptNo: '',
        streetName: '',
        city: '',
        zip: '',
        locality: '',
        masjid: '',
        comments: '',
        latitude: '',
        longitude: '',
      }))
      setNeedsRegeocode(false)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to create address')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box sx={{ maxWidth: 900, mx: 'auto', px: 2, py: 3 }}>
      <Typography variant="h5" gutterBottom>
        Add New Address
      </Typography>

      {/* CSV bulk-import banner */}
      <Paper variant="outlined" sx={{ p: 2, mb: 3, bgcolor: 'grey.50' }}>
        <Typography variant="subtitle2" gutterBottom>
          Want to add multiple addresses at once?
        </Typography>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            size="small"
            variant="outlined"
            startIcon={<DownloadIcon />}
            onClick={downloadTemplate}
          >
            Download CSV Template
          </Button>
          <Button
            size="small"
            variant="contained"
            startIcon={<UploadFileIcon />}
            onClick={() => navigate('/address-import')}
          >
            Upload CSV File
          </Button>
        </Stack>
      </Paper>

      <Divider sx={{ mb: 3 }}>
        <Typography variant="caption" color="text.secondary">
          OR ADD ONE ADDRESS BELOW
        </Typography>
      </Divider>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      <Paper elevation={1} sx={{ p: 3 }}>
        <form onSubmit={handleSubmit}>
          <Grid container spacing={2}>
            <Grid item xs={12} md={6}>
              <TextField fullWidth label="Name" value={form.name} onChange={(e) => updateField('name', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="House No" value={form.houseNo} onChange={(e) => updateField('houseNo', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="Apt No" value={form.aptNo} onChange={(e) => updateField('aptNo', e.target.value)} />
            </Grid>
            <Grid item xs={12}>
              <TextField fullWidth label="Street Name" value={form.streetName} onChange={(e) => updateField('streetName', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="City" value={form.city} onChange={(e) => updateField('city', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="State" value={form.state} onChange={(e) => updateField('state', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="Zip" value={form.zip} onChange={(e) => updateField('zip', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField fullWidth label="Locality" value={form.locality} onChange={(e) => updateField('locality', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField fullWidth label="Masjid" value={form.masjid} onChange={(e) => updateField('masjid', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="Last Visit" type="date" value={form.lastVisit} onChange={(e) => updateField('lastVisit', e.target.value)} InputLabelProps={{ shrink: true }} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField
                fullWidth
                label="Latitude"
                value={form.latitude}
                onChange={(e) => updateField('latitude', e.target.value, false)}
                InputProps={{ endAdornment: geocoding ? <CircularProgress size={16} /> : undefined }}
                helperText="Auto-filled from address"
              />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField
                fullWidth
                label="Longitude"
                value={form.longitude}
                onChange={(e) => updateField('longitude', e.target.value, false)}
                helperText="Auto-filled from address"
              />
            </Grid>
            <Grid item xs={12} md={4}>
              <Button
                fullWidth
                variant="outlined"
                onClick={handleUseCurrentLocation}
                disabled={loading || locating}
                sx={{ height: '56px' }}
              >
                {locating ? 'Getting Location...' : 'Use Current Location'}
              </Button>
            </Grid>
            {needsRegeocode && (
              <Grid item xs={12} md={4}>
                <Button
                  fullWidth
                  variant="contained"
                  color="secondary"
                  onClick={() => geocodeAddress(form)}
                  disabled={loading || geocoding}
                  sx={{ height: '56px' }}
                >
                  {geocoding ? 'Geocoding...' : 'Geocode Fixed Information'}
                </Button>
              </Grid>
            )}
            <Grid item xs={12}>
              <TextField fullWidth multiline rows={4} label="Comments" value={form.comments} onChange={(e) => updateField('comments', e.target.value)} />
            </Grid>
          </Grid>

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 3 }}>
            <Button type="submit" variant="contained" disabled={loading}>
              {loading ? <CircularProgress size={22} /> : 'Create Address'}
            </Button>
            <Button variant="outlined" onClick={() => navigate('/dashboard')} disabled={loading}>
              Back
            </Button>
          </Stack>
        </form>
      </Paper>
    </Box>
  )
}

export default AddAddress