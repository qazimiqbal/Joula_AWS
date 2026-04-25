import React, { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  Typography,
  Chip,
} from '@mui/material'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
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
  const [loading, setLoading] = useState(true)
  const [pageError, setPageError] = useState('')
  const [addresses, setAddresses] = useState<MissingCoordinatesRecord[]>([])
  const [rowStates, setRowStates] = useState<Record<number, RowState>>({})
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

  const handleGeocode = async (a: MissingCoordinatesRecord) => {
    const query = buildQuery(a)
    if (!query) {
      setRow(a.id, { status: 'error', errorMsg: 'Not enough address data to geocode' })
      return
    }

    setRow(a.id, { status: 'geocoding' })
    try {
      const { lat, lng } = await apiService.geocodeAddress(query)

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
      // small delay to respect Nominatim rate limit (1 req/s)
      await new Promise((r) => setTimeout(r, 1100))
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
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Locality</TableCell>
                  <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>Action</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {pagedAddresses.map((a) => {
                  const rs = rowStates[a.id] ?? { status: 'idle' }
                  const fullAddress = [a.aptNo, a.houseNo, a.streetName].filter(Boolean).join(' ')
                  const isBusy = rs.status === 'geocoding' || rs.status === 'saving'
                  return (
                    <TableRow key={a.id} sx={{ opacity: rs.status === 'done' ? 0.5 : 1 }}>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{a.name}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{fullAddress}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{a.city}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{a.state}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{a.locality || '—'}</TableCell>
                      <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
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
