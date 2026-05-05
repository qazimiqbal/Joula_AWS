import React, { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  FormControl,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  Typography,
} from '@mui/material'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'
import { PendingGeocodeRecord, PendingMasjidRecord } from '@/types'

type ReviewMode = 'masjids' | 'addresses'
type ReviewRow = PendingGeocodeRecord | PendingMasjidRecord

const GeocodeReview: React.FC = () => {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [mode, setMode] = useState<ReviewMode>('masjids')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [masjidRows, setMasjidRows] = useState<PendingMasjidRecord[]>([])
  const [addressRows, setAddressRows] = useState<PendingGeocodeRecord[]>([])
  const [createdByFilter, setCreatedByFilter] = useState<number | 'all'>('all')
  const [approvingId, setApprovingId] = useState<number | null>(null)
  const [openingId, setOpeningId] = useState<number | null>(null)
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)

  const permissionLevel = user?.permissionLevel ?? 0

  const rows = mode === 'addresses' ? addressRows : masjidRows

  const submitterOptions = useMemo(() => {
    const map = new Map<number, string>()
    rows.forEach((row) => {
      const id = mode === 'addresses' ? (row as PendingGeocodeRecord).uploadedBy : (row as PendingMasjidRecord).createdBy
      if (typeof id !== 'number') return
      const name = (row.submittedBy || '').trim() || `User ${id}`
      if (!map.has(id)) map.set(id, name)
    })
    return [...map.entries()]
      .map(([id, label]) => ({ id, label }))
      .sort((a, b) => a.label.localeCompare(b.label))
  }, [rows, mode])

  const loadRows = async (nextMode: ReviewMode, filter: number | 'all') => {
    setLoading(true)
    setError('')
    try {
      const createdBy = filter === 'all' ? undefined : filter
      if (nextMode === 'addresses') {
        const data = await apiService.getGeocodeReviewList(createdBy)
        setAddressRows(data)
      } else {
        const data = await apiService.getMasjidReviewList(createdBy)
        setMasjidRows(data)
      }
      setPage(0)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to load pending review items')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadRows(mode, createdByFilter)
  }, [mode, createdByFilter])

  const openOnMap = async (row: ReviewRow) => {
    setOpeningId(row.id)
    setError('')
    try {
      const reviewType = mode === 'masjids' ? 'masjid' : 'address'
      const hasCoordinates = 'latitude' in row && typeof row.latitude === 'number' && typeof row.longitude === 'number'
      if (hasCoordinates) {
        navigate(`/map?reviewLat=${row.latitude}&reviewLng=${row.longitude}&reviewId=${row.id}&reviewName=${encodeURIComponent(row.name)}&reviewType=${reviewType}`)
        return
      }

      const query = [row.aptNo, row.houseNo, row.streetName, row.city, row.state, row.zip].filter(Boolean).join(', ')
      const geocoded = await apiService.geocodeAddress(query)
      navigate(`/map?reviewLat=${geocoded.lat}&reviewLng=${geocoded.lng}&reviewId=${row.id}&reviewName=${encodeURIComponent(row.name)}&reviewType=${reviewType}`)
    } catch {
      setError('Could not open this row on map. Coordinate lookup failed.')
    } finally {
      setOpeningId(null)
    }
  }

  const handleApprove = async (row: ReviewRow) => {
    setApprovingId(row.id)
    setError('')
    setSuccess('')
    try {
      if (mode === 'addresses') {
        await apiService.approveGeocodedAddress(row.id)
        setAddressRows((prev) => prev.filter((r) => r.id !== row.id))
        setSuccess('Address approved and now visible in regular map/list.')
      } else {
        await apiService.approveMasjid(row.id)
        setMasjidRows((prev) => prev.filter((r) => r.id !== row.id))
        setSuccess('Masjid approved successfully.')
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to approve item')
    } finally {
      setApprovingId(null)
    }
  }

  const pagedRows = rows.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage)

  const handleChangePage = (_event: unknown, newPage: number) => {
    setPage(newPage)
  }

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10))
    setPage(0)
  }

  if (permissionLevel < 4) {
    return <Alert severity="error">You do not have permission to access this page.</Alert>
  }

  return (
    <Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1, flexWrap: 'wrap', gap: 1 }}>
        <Typography variant="h5" gutterBottom sx={{ mb: 0 }}>
          Review/Approve Submissions
        </Typography>
        <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
          <Button variant={mode === 'masjids' ? 'contained' : 'outlined'} onClick={() => setMode('masjids')}>
            Review New Masjids
          </Button>
          <Button variant={mode === 'addresses' ? 'contained' : 'outlined'} onClick={() => setMode('addresses')}>
            Review New Addresses
          </Button>
        </Box>
      </Box>

      <Box sx={{ mb: 2, maxWidth: 320 }}>
        <FormControl fullWidth size="small">
          <InputLabel id="review-created-by-label">Filter by User</InputLabel>
          <Select
            labelId="review-created-by-label"
            label="Filter by User"
            value={createdByFilter}
            onChange={(event) => {
              const value = event.target.value
              setCreatedByFilter(value === 'all' ? 'all' : Number(value))
            }}
          >
            <MenuItem value="all">All Users</MenuItem>
            {submitterOptions.map((opt) => (
              <MenuItem key={opt.id} value={opt.id}>{opt.label}</MenuItem>
            ))}
          </Select>
        </FormControl>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      <Paper elevation={1} sx={{ p: 2 }}>
        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
            <CircularProgress />
          </Box>
        ) : rows.length === 0 ? (
          <Typography>
            {mode === 'addresses'
              ? 'No pending addresses waiting for admin review.'
              : 'No pending masjids waiting for admin review.'}
          </Typography>
        ) : (
          <>
            <TableContainer sx={{ overflowX: 'auto' }}>
              <Table size="small" sx={{ minWidth: 900 }}>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Name</TableCell>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Address</TableCell>
                    {mode === 'addresses' && <TableCell sx={{ whiteSpace: 'nowrap' }}>Locality</TableCell>}
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Submitted By</TableCell>
                    {mode === 'addresses' && <TableCell sx={{ whiteSpace: 'nowrap' }}>Coordinates</TableCell>}
                    <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {pagedRows.map((row) => {
                    const fullAddress = [row.aptNo, row.houseNo, row.streetName, row.city, row.state, row.zip].filter(Boolean).join(', ')
                    const addressRow = row as PendingGeocodeRecord

                    return (
                      <TableRow key={row.id}>
                        <TableCell sx={{ whiteSpace: 'nowrap' }}>{row.name}</TableCell>
                        <TableCell sx={{ whiteSpace: 'nowrap' }}>{fullAddress}</TableCell>
                        {mode === 'addresses' && <TableCell sx={{ whiteSpace: 'nowrap' }}>{addressRow.locality || '—'}</TableCell>}
                        <TableCell sx={{ whiteSpace: 'nowrap' }}>{row.submittedBy || 'Unknown'}</TableCell>
                        {mode === 'addresses' && (
                          <TableCell sx={{ whiteSpace: 'nowrap' }}>
                            {typeof addressRow.latitude === 'number' && typeof addressRow.longitude === 'number'
                              ? `${addressRow.latitude.toFixed(6)}, ${addressRow.longitude.toFixed(6)}`
                              : 'Not geocoded'}
                          </TableCell>
                        )}
                        <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
                          <Button
                            size="small"
                            variant="text"
                            sx={{ mr: 1 }}
                            disabled={openingId === row.id}
                            onClick={() => openOnMap(row)}
                          >
                            {openingId === row.id ? 'Opening...' : 'Open on Map'}
                          </Button>
                          <Button
                            size="small"
                            variant="text"
                            color="success"
                            disabled={approvingId === row.id}
                            onClick={() => handleApprove(row)}
                          >
                            {approvingId === row.id ? 'Approving...' : 'Approve'}
                          </Button>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </TableContainer>
            <TablePagination
              component="div"
              count={rows.length}
              page={page}
              onPageChange={handleChangePage}
              rowsPerPage={rowsPerPage}
              onRowsPerPageChange={handleChangeRowsPerPage}
              rowsPerPageOptions={[5, 10, 25, 50]}
            />
          </>
        )}
      </Paper>
    </Box>
  )
}

export default GeocodeReview
