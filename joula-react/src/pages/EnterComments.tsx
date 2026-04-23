import React, { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Box,
  Typography,
  TextField,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Button,
  Alert,
  CircularProgress,
  Paper,
  Stack,
} from '@mui/material'
import apiService from '@services/api'
import axios from 'axios'

const ACTION_OPTIONS = [
  { value: 'met',                            label: 'Met' },
  { value: 'left_message',                   label: 'Left Message' },
  { value: 'No_Response',                    label: 'No Response' },
  { value: 'Ismailee',                       label: 'Ismailee' },
  { value: 'Owner_muslim_rented_non_muslim', label: 'Owner Muslim, Rented to Non Muslim' },
  { value: 'Non_muslim',                     label: 'Non Muslim' },
  { value: 'WrongAddress',                   label: 'Wrong Address' },
]

const ETHNICITY_OPTIONS: { value: string; label: string }[] = [
  { value: 'Others',    label: 'Others' },
  { value: 'African',   label: 'African' },
  { value: 'American',  label: 'American' },
  { value: 'Arab',      label: 'Arab' },
  { value: 'Bengali',   label: 'Bangladeshi' },
  { value: 'Bosnian',   label: 'Bosnian' },
  { value: 'Indian',    label: 'Indian' },
  { value: 'Pakistani', label: 'Pakistani' },
  { value: 'Spanish',   label: 'Spanish' },
]

const todayStr = () => new Date().toISOString().split('T')[0]

const EnterComments: React.FC = () => {
  const [searchParams] = useSearchParams()
  const id = searchParams.get('id')
  const initialComments = searchParams.get('comments') || ''

  const [loading, setLoading]       = useState(true)
  const [saving, setSaving]         = useState(false)
  const [error, setError]           = useState('')
  const [success, setSuccess]       = useState(false)

  const [date, setDate]             = useState(todayStr())
  const [actionTaken, setActionTaken] = useState('met')
  const [ethinicity, setEthinicity] = useState('Others')
  const [potential, setPotential]   = useState('No')
  const [comments, setComments]     = useState(initialComments)

  useEffect(() => {
    if (!id) { setLoading(false); return }
    apiService.getVisitData(Number(id))
      .then((data) => {
        setComments(data.comments || '')
        if (data.ethinicity) setEthinicity(data.ethinicity)
        if (data.potential)  setPotential(data.potential)
      })
      .catch(() => { /* keep query-param fallback if API pre-load fails */ })
      .finally(() => setLoading(false))
  }, [id, initialComments])

  const handleSubmit = async () => {
    if (!id) return
    setSaving(true)
    setError('')
    try {
      await apiService.updateVisit(Number(id), {
        today: date,
        actionTaken,
        comments,
        ethinicity,
        potential,
      })
      setSuccess(true)
    } catch (err: unknown) {
      let msg = 'Failed to save. Please try again.'
      if (axios.isAxiosError(err)) {
        const serverMsg = (err.response?.data as { message?: string })?.message
        const status = err.response?.status
        if (serverMsg) msg = serverMsg
        else if (status === 404) msg = 'API endpoint not found — visit.php not yet uploaded to the server.'
        else if (status) msg = `Server error ${status}.`
        else if (err.message) msg = err.message
      } else if (err instanceof Error) {
        msg = err.message
      }
      setError(msg)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" mt={6}>
        <CircularProgress />
      </Box>
    )
  }

  if (success) {
    return (
      <Box display="flex" justifyContent="center" mt={6}>
        <Alert severity="success" sx={{ maxWidth: 480 }}>
          Record updated successfully. You can close this tab.
        </Alert>
      </Box>
    )
  }

  return (
    <Box sx={{ maxWidth: 480, mx: 'auto', mt: 4, px: 2, pb: 4 }}>
      <Paper elevation={2} sx={{ p: 3 }}>
        <Typography variant="h6" gutterBottom>
          Enter Visit Comments
        </Typography>

        {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

        <Stack spacing={2}>
          <TextField
            label="Visit Date"
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            fullWidth
            InputLabelProps={{ shrink: true }}
          />

          <FormControl fullWidth>
            <InputLabel>Action Taken</InputLabel>
            <Select
              value={actionTaken}
              label="Action Taken"
              onChange={(e) => setActionTaken(e.target.value)}
            >
              {ACTION_OPTIONS.map((o) => (
                <MenuItem key={o.value} value={o.value}>{o.label}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl fullWidth>
            <InputLabel>Ethnicity</InputLabel>
            <Select
              value={ethinicity}
              label="Ethnicity"
              onChange={(e) => setEthinicity(e.target.value)}
            >
              {ETHNICITY_OPTIONS.map((o) => (
                <MenuItem key={o.value} value={o.value}>{o.label}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl fullWidth>
            <InputLabel>Potential</InputLabel>
            <Select
              value={potential}
              label="Potential"
              onChange={(e) => setPotential(e.target.value)}
            >
              <MenuItem value="No">No</MenuItem>
              <MenuItem value="Yes">Yes</MenuItem>
            </Select>
          </FormControl>

          <TextField
            label="Comments"
            multiline
            rows={4}
            value={comments}
            onChange={(e) => setComments(e.target.value)}
            fullWidth
          />

          <Button
            variant="contained"
            onClick={handleSubmit}
            disabled={saving}
            fullWidth
          >
            {saving ? 'Saving…' : 'Submit'}
          </Button>
        </Stack>
      </Paper>
    </Box>
  )
}

export default EnterComments
