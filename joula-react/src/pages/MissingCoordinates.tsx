import React, { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Stack,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  TextField,
  Typography,
  Chip,
} from '@mui/material'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import { useJsApiLoader } from '@react-google-maps/api'
import apiService from '@services/api'
import { MissingCoordinatesRecord } from '@/types'

interface RowState {
  status: 'idle' | 'geocoding' | 'saving' | 'done' | 'error'
  lat?: number
  lng?: number
  errorMsg?: string
}

const MissingCoordinates: React.FC = () => {
  const navigate = useNavigate()
  const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || ''
  const { isLoaded: isGoogleMapsLoaded } = useJsApiLoader({
    id: 'missing-coordinates-google-geocoder',
    googleMapsApiKey,
  })
  const [loading, setLoading] = useState(true)
  const [pageError, setPageError] = useState('')
  const [addresses, setAddresses] = useState<MissingCoordinatesRecord[]>([])
  const [rowStates, setRowStates] = useState<Record<number, RowState>>({})
  const [editDrafts, setEditDrafts] = useState<Record<number, MissingCoordinatesRecord>>({})
  const [editSaving, setEditSaving] = useState<Record<number, boolean>>({})
  const [deleteSaving, setDeleteSaving] = useState<Record<number, boolean>>({})
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)

  const loadAddresses = useCallback(async () => {
    setLoading(true)
    setPageError('')
    try {
      const data = await apiService.getMissingCoordinates()
      setAddresses(data)
      setPage(0)
      const initial: Record<number, RowState> = {}
      data.forEach((a) => { initial[a.id] = { status: 'idle' } })
      setRowStates(initial)
      setEditDrafts({})
      setEditSaving({})
      setDeleteSaving({})
    } catch (err: unknown) {
      setPageError(err instanceof Error ? err.message : 'Failed to load addresses')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadAddresses() }, [loadAddresses])

  const setRow = (id: number, state: Partial<RowState>) =>
    setRowStates((prev) => ({ ...prev, [id]: { ...prev[id], ...state } }))

  const buildQuery = (a: MissingCoordinatesRecord) =>
    [a.aptNo, a.houseNo, a.streetName, a.city, a.state, a.zip]
      .map((p) => (p || '').trim())
      .filter(Boolean)
      .join(', ')

  const geocodeWithBrowserGoogle = useCallback(async (query: string): Promise<{ lat: number; lng: number } | null> => {
    if (!isGoogleMapsLoaded || typeof window === 'undefined' || !window.google?.maps?.Geocoder) {
      return null
    }

    const geocoder = new window.google.maps.Geocoder()
    return new Promise((resolve) => {
      geocoder.geocode({ address: query }, (results, status) => {
        if (status === 'OK' && results?.[0]?.geometry?.location) {
          const loc = results[0].geometry.location
          resolve({ lat: loc.lat(), lng: loc.lng() })
          return
        }
        resolve(null)
      })
    })
  }, [isGoogleMapsLoaded])

  const handleGeocode = async (a: MissingCoordinatesRecord) => {
    const query = buildQuery(a)
    if (!query) {
      setRow(a.id, { status: 'error', errorMsg: 'Not enough address data to geocode' })
      return
    }

    if (!googleMapsApiKey || !isGoogleMapsLoaded) {
      setRow(a.id, { status: 'error', errorMsg: 'Google geocoder is still loading' })
      return
    }

    setRow(a.id, { status: 'geocoding' })
    try {
      const geocoded = await geocodeWithBrowserGoogle(query)
      if (!geocoded) {
        setRow(a.id, { status: 'error', errorMsg: 'Google could not geocode this address' })
        return
      }
      const { lat, lng } = geocoded

      setRow(a.id, { status: 'saving', lat, lng })
      await apiService.saveCoordinates(a.id, lat, lng)
      setRow(a.id, { status: 'done', lat, lng })
      setAddresses((prev) => prev.filter((r) => r.id !== a.id))
    } catch (err: unknown) {
      setRow(a.id, { status: 'error', errorMsg: err instanceof Error ? err.message : 'Failed' })
    }
  }

  const handleGeocodeAll = async () => {
    for (const a of addresses) {
      const state = rowStates[a.id]
      if (state?.status === 'done') continue
      await handleGeocode(a)
    }
  }

  const startEdit = (a: MissingCoordinatesRecord) => {
    setEditDrafts((prev) => ({ ...prev, [a.id]: { ...a } }))
    setRow(a.id, { status: 'idle', errorMsg: undefined })
  }

  const cancelEdit = (id: number) => {
    setEditDrafts((prev) => {
      const next = { ...prev }
      delete next[id]
      return next
    })
  }

  const updateDraftField = (id: number, field: keyof MissingCoordinatesRecord, value: string) => {
    setEditDrafts((prev) => {
      const current = prev[id]
      if (!current) return prev
      return {
        ...prev,
        [id]: {
          ...current,
          [field]: value,
        },
      }
    })
  }

  const saveEdit = async (id: number) => {
    const draft = editDrafts[id]
    if (!draft) return

    if (!draft.streetName.trim() || !draft.city.trim() || !draft.state.trim() || !draft.zip.trim()) {
      setRow(id, { status: 'error', errorMsg: 'Street, city, state, and zip are required' })
      return
    }

    setEditSaving((prev) => ({ ...prev, [id]: true }))
    try {
      await apiService.updateMissingCoordinatesAddress(id, {
        name: draft.name.trim(),
        houseNo: draft.houseNo.trim(),
        aptNo: (draft.aptNo || '').trim(),
        streetName: draft.streetName.trim(),
        city: draft.city.trim(),
        state: draft.state.trim(),
        zip: draft.zip.trim(),
        locality: (draft.locality || '').trim(),
      })

      setAddresses((prev) => prev.map((row) => (row.id === id ? { ...draft } : row)))
      cancelEdit(id)
      setRow(id, { status: 'idle', errorMsg: undefined })
    } catch (err: unknown) {
      setRow(id, { status: 'error', errorMsg: err instanceof Error ? err.message : 'Failed to update record' })
    } finally {
      setEditSaving((prev) => ({ ...prev, [id]: false }))
    }
  }

  const deleteAddress = async (a: MissingCoordinatesRecord) => {
    const ok = window.confirm(`Delete this address?\n\n${[a.name, a.houseNo, a.streetName, a.city].filter(Boolean).join(', ')}`)
    if (!ok) return

    setDeleteSaving((prev) => ({ ...prev, [a.id]: true }))
    try {
      await apiService.deleteMissingCoordinatesAddress(a.id)
      setAddresses((prev) => prev.filter((row) => row.id !== a.id))
      cancelEdit(a.id)
      setRow(a.id, { status: 'idle', errorMsg: undefined })
    } catch (err: unknown) {
      setRow(a.id, { status: 'error', errorMsg: err instanceof Error ? err.message : 'Failed to delete record' })
    } finally {
      setDeleteSaving((prev) => ({ ...prev, [a.id]: false }))
    }
  }

  const pendingCount = addresses.filter((a) => rowStates[a.id]?.status !== 'done').length
  const pagedAddresses = addresses.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage)

  const handleChangePage = (_event: unknown, newPage: number) => {
    setPage(newPage)
  }

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10))
    setPage(0)
  }

  return (
    <Box sx={{ maxWidth: 1200, mx: 'auto', px: 2, py: 3 }}>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2, flexWrap: 'wrap', gap: 1 }}>
        <Typography variant="h5">
          Addresses Missing Coordinates
          {!loading && (
            <Chip label={`${pendingCount} remaining`} size="small" color={pendingCount === 0 ? 'success' : 'warning'} sx={{ ml: 2 }} />
          )}
        </Typography>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button variant="contained" onClick={handleGeocodeAll} disabled={loading || pendingCount === 0}>
            Geocode All
          </Button>
          <Button variant="outlined" onClick={() => navigate('/dashboard')}>
            Back
          </Button>
        </Box>
      </Box>

      {pageError && <Alert severity="error" sx={{ mb: 2 }}>{pageError}</Alert>}
      {!isGoogleMapsLoaded && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Loading Google geocoder...
        </Alert>
      )}

      {loading ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 5 }}>
          <CircularProgress />
        </Box>
      ) : addresses.length === 0 ? (
        <Alert severity="success">All addresses have coordinates.</Alert>
      ) : (
        <Paper elevation={1}>
          <TableContainer sx={{ overflowX: 'auto' }}>
            <Table size="small" sx={{ minWidth: 900 }}>
              <TableHead>
                <TableRow sx={{ bgcolor: 'grey.100' }}>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Name</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Address</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>City</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>State</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Zip</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Locality</TableCell>
                  <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>Action</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {pagedAddresses.map((a) => {
                  const rs = rowStates[a.id] ?? { status: 'idle' }
                  const draft = editDrafts[a.id]
                  const isEditing = Boolean(draft)
                  const isEditSaving = Boolean(editSaving[a.id])
                  const isDeleteSaving = Boolean(deleteSaving[a.id])
                  const rowData = draft || a
                  const fullAddress = [rowData.aptNo, rowData.houseNo, rowData.streetName].filter(Boolean).join(' ')
                  const isBusy = rs.status === 'geocoding' || rs.status === 'saving'
                  const disableActions = isBusy || isEditSaving || isDeleteSaving
                  return (
                    <TableRow key={a.id} sx={{ opacity: rs.status === 'done' ? 0.5 : 1 }}>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <TextField
                            size="small"
                            value={rowData.name}
                            onChange={(e) => updateDraftField(a.id, 'name', e.target.value)}
                            sx={{ minWidth: 150 }}
                          />
                        ) : rowData.name}
                      </TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <Stack direction="row" spacing={1}>
                            <TextField
                              size="small"
                              label="Apt"
                              value={rowData.aptNo || ''}
                              onChange={(e) => updateDraftField(a.id, 'aptNo', e.target.value)}
                              sx={{ width: 90 }}
                            />
                            <TextField
                              size="small"
                              label="House"
                              value={rowData.houseNo}
                              onChange={(e) => updateDraftField(a.id, 'houseNo', e.target.value)}
                              sx={{ width: 90 }}
                            />
                            <TextField
                              size="small"
                              label="Street"
                              value={rowData.streetName}
                              onChange={(e) => updateDraftField(a.id, 'streetName', e.target.value)}
                              sx={{ minWidth: 180 }}
                            />
                          </Stack>
                        ) : fullAddress}
                      </TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <TextField
                            size="small"
                            value={rowData.city}
                            onChange={(e) => updateDraftField(a.id, 'city', e.target.value)}
                            sx={{ width: 130 }}
                          />
                        ) : rowData.city}
                      </TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <TextField
                            size="small"
                            value={rowData.state}
                            onChange={(e) => updateDraftField(a.id, 'state', e.target.value)}
                            sx={{ width: 90 }}
                          />
                        ) : rowData.state}
                      </TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <TextField
                            size="small"
                            value={rowData.zip}
                            onChange={(e) => updateDraftField(a.id, 'zip', e.target.value)}
                            sx={{ width: 100 }}
                          />
                        ) : rowData.zip}
                      </TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <TextField
                            size="small"
                            value={rowData.locality || ''}
                            onChange={(e) => updateDraftField(a.id, 'locality', e.target.value)}
                            sx={{ width: 140 }}
                          />
                        ) : (rowData.locality || '—')}
                      </TableCell>
                      <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
                        {isEditing ? (
                          <Stack direction="row" spacing={1} justifyContent="flex-end">
                            <Button
                              size="small"
                              variant="contained"
                              onClick={() => saveEdit(a.id)}
                              disabled={disableActions}
                            >
                              {isEditSaving ? 'Saving…' : 'Save'}
                            </Button>
                            <Button size="small" variant="outlined" onClick={() => cancelEdit(a.id)} disabled={disableActions}>
                              Cancel
                            </Button>
                            <Button size="small" variant="text" color="error" onClick={() => deleteAddress(a)} disabled={disableActions}>
                              {isDeleteSaving ? 'Deleting…' : 'Delete'}
                            </Button>
                          </Stack>
                        ) : (
                          <Stack direction="row" spacing={1} justifyContent="flex-end" alignItems="center">
                            {rs.status === 'done' ? (
                              <CheckCircleIcon color="success" fontSize="small" />
                            ) : rs.status === 'error' ? (
                              <>
                                <Typography component="span" variant="caption" color="error" sx={{ mr: 1 }}>
                                  {rs.errorMsg}
                                </Typography>
                                <Button size="small" variant="text" color="error" onClick={() => handleGeocode(a)}>
                                  Retry
                                </Button>
                              </>
                            ) : (
                              <Button
                                size="small"
                                variant="text"
                                onClick={() => handleGeocode(a)}
                                disabled={isBusy}
                                startIcon={isBusy ? <CircularProgress size={14} /> : undefined}
                              >
                                {rs.status === 'geocoding' ? 'Geocoding…' : rs.status === 'saving' ? 'Saving…' : 'Geocode'}
                              </Button>
                            )}
                            <Button size="small" variant="outlined" onClick={() => startEdit(a)} disabled={disableActions}>
                              Edit
                            </Button>
                            <Button size="small" variant="text" color="error" onClick={() => deleteAddress(a)} disabled={disableActions}>
                              {isDeleteSaving ? 'Deleting…' : 'Delete'}
                            </Button>
                          </Stack>
                        )}
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            component="div"
            count={addresses.length}
            page={page}
            onPageChange={handleChangePage}
            rowsPerPage={rowsPerPage}
            onRowsPerPageChange={handleChangeRowsPerPage}
            rowsPerPageOptions={[5, 10, 25, 50]}
          />
        </Paper>
      )}
    </Box>
  )
}

export default MissingCoordinates
