import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  Card,
  CardHeader,
  CardContent,
  CircularProgress,
  Grid,
  
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import apiService from '@services/api'


const AddMasjid: React.FC = () => {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [form, setForm] = useState({
    name: 'Omar',
    houseNo: '805',
    aptNo: '',
    streetName: 'Dickens Rd SW',
    city: 'Lilburn',
    state: 'GA',
    zip: '30047',
  })
  const [lastLatLng, setLastLatLng] = useState<{ lat: string, lng: string } | null>(null)


  const updateField = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }



  const [addressDebug, setAddressDebug] = useState<string | null>(null);
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')

    if (!form.name.trim() || !form.houseNo.trim() || !form.streetName.trim() || !form.city.trim() || !form.state.trim() || !form.zip.trim()) {
      setError('Name, house number, street name, city, state, and zip are required.')
      return
    }

    // Always send address fields to backend; backend will geocode

    setLoading(true)

    try {
      const payload = {
        name: form.name.trim(),
        houseNo: form.houseNo.trim(),
        aptNo: form.aptNo.trim(),
        streetName: form.streetName.trim(),
        city: form.city.trim(),
        state: form.state.trim(),
        zip: form.zip.trim(),
      };
      // Debug: log payload
      // eslint-disable-next-line no-console
      console.log('Submitting masjid:', payload);

      await apiService.createMasjid(payload);
      setSuccess('Masjid submitted successfully and is pending admin approval.')
      setLastLatLng(null)
      setForm({
        name: '',
        houseNo: '',
        aptNo: '',
        streetName: '',
        city: '',
        state: 'GA',
        zip: '',
      })
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to create masjid')
      setAddressDebug(`${form.houseNo} ${form.streetName}, ${form.city}, ${form.state} ${form.zip}`);
    } finally {
      setLoading(false)
    }
  }
  // ...existing code...
  return (
    <Box sx={{
      flex: 1,
      minHeight: 0,
      bgcolor: 'grey.100',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'stretch',
    }}>
      <Card
        elevation={1}
        sx={{
          borderRadius: 0,
          p: 0,
          width: '100%',
          maxWidth: '100%',
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <CardHeader
          title={<Typography variant="h6">Add New Masjid</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">Each masjid is automatically tagged with your user account.</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          {lastLatLng && (
            <Alert severity="info" sx={{ mb: 2 }}>
              <strong>Last Created Masjid Coordinates:</strong><br />
              Latitude: {lastLatLng.lat || 'N/A'}<br />
              Longitude: {lastLatLng.lng || 'N/A'}
            </Alert>
          )}
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
              {addressDebug && (
                <Box sx={{ mt: 1, fontSize: 13, color: 'gray' }}>
                  <strong>Address string:</strong> {addressDebug}
                </Box>
              )}
            </Alert>
          )}
          {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}
          <form onSubmit={handleSubmit}>
            <Grid container spacing={2}>
              <Grid item xs={12} md={6}>
                <TextField fullWidth label="Masjid Name" value={form.name} onChange={(e) => updateField('name', e.target.value)} />
              </Grid>
              <Grid item xs={12} md={3}>
                <TextField fullWidth label="House No" value={form.houseNo} onChange={(e) => updateField('houseNo', e.target.value)} />
              </Grid>
              <Grid item xs={12} md={3}>
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
            </Grid>
            {/* Coordinates fields removed. Geocoding is now automatic on submit. */}
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 3 }}>
              <Button
                type="submit"
                variant="contained"
                sx={{
                  minWidth: 160,
                  background: 'linear-gradient(90deg, #43cea2 0%, #185a9d 100%)',
                  color: '#fff',
                  fontWeight: 700,
                  letterSpacing: 1,
                  boxShadow: '0 4px 20px 0 rgba(67,206,162,0.2)',
                  '&:hover': {
                    background: 'linear-gradient(90deg, #185a9d 0%, #43cea2 100%)',
                  },
                }}
                disabled={loading}
              >
                {loading ? <CircularProgress size={22} /> : 'Create Masjid'}
              </Button>
              <Button
                variant="outlined"
                sx={{
                  minWidth: 120,
                  borderColor: '#ff4081',
                  color: '#ff4081',
                  fontWeight: 700,
                  letterSpacing: 1,
                  background: '#fff',
                  '&:hover': {
                    background: '#ffecf6',
                    borderColor: '#f06292',
                    color: '#f06292',
                  },
                }}
                onClick={() => navigate('/dashboard')}
                disabled={loading}
              >
                Back
              </Button>
            </Stack>
          </form>
        </CardContent>
      </Card>
    </Box>
  )
}
export default AddMasjid

