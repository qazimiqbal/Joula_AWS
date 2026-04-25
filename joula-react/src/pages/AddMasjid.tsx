import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Grid,
  Paper,
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
    name: '',
    houseNo: '',
    aptNo: '',
    streetName: '',
    city: '',
    state: 'GA',
    zip: '',
  })

  const updateField = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')

    if (!form.name.trim() || !form.houseNo.trim() || !form.streetName.trim() || !form.city.trim() || !form.state.trim() || !form.zip.trim()) {
      setError('Name, house number, street name, city, state, and zip are required.')
      return
    }

    setLoading(true)
    try {
      await apiService.createMasjid({
        name: form.name.trim(),
        houseNo: form.houseNo.trim(),
        aptNo: form.aptNo.trim(),
        streetName: form.streetName.trim(),
        city: form.city.trim(),
        state: form.state.trim(),
        zip: form.zip.trim(),
      })

      setSuccess('Masjid submitted successfully and is pending admin approval.')
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
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box sx={{ maxWidth: 900, mx: 'auto', px: 2, py: 3 }}>
      <Typography variant="h5" gutterBottom>
        Add New Masjid
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        You can currently add up to 5 masjids. Each masjid is automatically tagged with your user account.
      </Typography>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      <Paper elevation={1} sx={{ p: 3 }}>
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

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 3 }}>
            <Button type="submit" variant="contained" disabled={loading}>
              {loading ? <CircularProgress size={22} /> : 'Create Masjid'}
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

export default AddMasjid
