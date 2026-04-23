import React, { useState, useEffect, useRef, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  FormControl,
  Grid,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import apiService from '@services/api'

const todayStr = () => new Date().toISOString().split('T')[0]

const AddAddress: React.FC = () => {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [geocoding, setGeocoding] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [form, setForm] = useState({
    name: '',
    halaqa: 'Atlanta East',
    houseNo: '',
    aptNo: '',
    streetName: '',
    city: '',
    state: 'GA',
    zip: '',
    locality: '',
    verified: 'N' as 'Y' | 'N',
    masjid: '',
    lastVisit: todayStr(),
    comments: '',
    latitude: '',
    longitude: '',
  })

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const updateField = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
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
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`
      const response = await fetch(url, { headers: { Accept: 'application/json' } })
      if (!response.ok) return
      const results = (await response.json()) as Array<{ lat: string; lon: string }>
      if (!Array.isArray(results) || results.length === 0) return

      const lat = Number(results[0].lat)
      const lon = Number(results[0].lon)
      if (Number.isNaN(lat) || Number.isNaN(lon)) return

      setForm((prev) => ({
        ...prev,
        latitude: lat.toFixed(6),
        longitude: lon.toFixed(6),
      }))
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')

    if (!form.name.trim() || !form.houseNo.trim() || !form.streetName.trim() || !form.city.trim() || !form.state.trim() || !form.zip.trim() || !form.locality.trim()) {
      setError('Name, house number, street, city, state, zip, and locality are required.')
      return
    }

    setLoading(true)
    try {
      await apiService.createAddress({
        name: form.name.trim(),
        halaqa: form.halaqa.trim(),
        houseNo: form.houseNo.trim(),
        aptNo: form.aptNo.trim(),
        streetName: form.streetName.trim(),
        city: form.city.trim(),
        state: form.state.trim(),
        zip: form.zip.trim(),
        locality: form.locality.trim(),
        verified: form.verified,
        masjid: form.masjid.trim(),
        lastVisit: form.lastVisit,
        comments: form.comments.trim(),
        latitude: form.latitude.trim() ? Number(form.latitude) : undefined,
        longitude: form.longitude.trim() ? Number(form.longitude) : undefined,
      })
      setSuccess('Address created successfully in Addresses_AWS.')
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
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      <Paper elevation={1} sx={{ p: 3 }}>
        <form onSubmit={handleSubmit}>
          <Grid container spacing={2}>
            <Grid item xs={12} md={6}>
              <TextField fullWidth label="Name" value={form.name} onChange={(e) => updateField('name', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField fullWidth label="Halaqa" value={form.halaqa} onChange={(e) => updateField('halaqa', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="House No" value={form.houseNo} onChange={(e) => updateField('houseNo', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField fullWidth label="Apt No" value={form.aptNo} onChange={(e) => updateField('aptNo', e.target.value)} />
            </Grid>
            <Grid item xs={12} md={4}>
              <FormControl fullWidth>
                <InputLabel>Verified</InputLabel>
                <Select value={form.verified} label="Verified" onChange={(e) => updateField('verified', e.target.value)}>
                  <MenuItem value="N">No</MenuItem>
                  <MenuItem value="Y">Yes</MenuItem>
                </Select>
              </FormControl>
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
                onChange={(e) => updateField('latitude', e.target.value)}
                InputProps={{ endAdornment: geocoding ? <CircularProgress size={16} /> : undefined }}
                helperText="Auto-filled from address"
              />
            </Grid>
            <Grid item xs={12} md={4}>
              <TextField
                fullWidth
                label="Longitude"
                value={form.longitude}
                onChange={(e) => updateField('longitude', e.target.value)}
                helperText="Auto-filled from address"
              />
            </Grid>
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