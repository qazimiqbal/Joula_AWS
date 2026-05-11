import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Box,
  Typography,
  Button,
  Paper,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Alert,
  Stack,
  CircularProgress,
  Collapse,
  TextField,
  Divider,
  List,
  ListItem,
  ListItemText,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  IconButton,
  Tooltip,
} from '@mui/material'
import MapIcon from '@mui/icons-material/Map'
import LocationOnIcon from '@mui/icons-material/LocationOn'
import EditIcon from '@mui/icons-material/Edit'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'
import { Masjid, AddressRecord } from '@/types'

const RADIUS_OPTIONS = [1, 2, 3, 4, 5, 7, 10, 15, 25]
const PAGE_SIZE_OPTIONS = [10, 25, 50]
type ActivePanel = 'masjids' | 'addresses' | 'radius' | null

interface EditDialogState {
  type: 'masjid' | 'address'
  id: number
  fields: Record<string, string>
}

const MASJID_FIELDS = [
  { key: 'name', label: 'Name' },
  { key: 'houseNo', label: 'House No' },
  { key: 'aptNo', label: 'Apt No' },
  { key: 'streetName', label: 'Street Name' },
  { key: 'city', label: 'City' },
  { key: 'state', label: 'State' },
  { key: 'zip', label: 'Zip' },
]

const ADDRESS_FIELDS = [
  { key: 'name', label: 'Name' },
  { key: 'houseNo', label: 'House No' },
  { key: 'aptNo', label: 'Apt No' },
  { key: 'streetName', label: 'Street Name' },
  { key: 'city', label: 'City' },
  { key: 'state', label: 'State' },
  { key: 'zip', label: 'Zip' },
  { key: 'locality', label: 'Locality' },
  { key: 'comments', label: 'Comments' },
]

const AreaSelection: React.FC = () => {
  const navigate = useNavigate()
  const { user } = useAuth()
  const permissionLevel = user?.permissionLevel ?? (user?.role === 'admin' ? 3 : 1)
  const isSuperAdmin = permissionLevel >= 4
  const isScopedView = !isSuperAdmin
  const canEdit = permissionLevel >= 2

  const [selectedRadius, setSelectedRadius] = useState(5)
  const [locationError, setLocationError] = useState('')
  const [masjids, setMasjids] = useState<Masjid[]>([])
  const [masjidsLoading, setMasjidsLoading] = useState(false)
  const [addresses, setAddresses] = useState<AddressRecord[]>([])
  const [addressesLoading, setAddressesLoading] = useState(false)
  const [activePanel, setActivePanel] = useState<ActivePanel>(null)
  const [selectedAddressMasjidId, setSelectedAddressMasjidId] = useState<string>('all')
  const [masjidSearch, setMasjidSearch] = useState('')
  const [addressSearch, setAddressSearch] = useState('')
  const [masjidPage, setMasjidPage] = useState(0)
  const [masjidPageSize, setMasjidPageSize] = useState(10)
  const [addressPage, setAddressPage] = useState(0)
  const [addressPageSize, setAddressPageSize] = useState(10)
  const [editDialog, setEditDialog] = useState<EditDialogState | null>(null)
  const [editSaving, setEditSaving] = useState(false)
  const [editError, setEditError] = useState('')
  const [editSuccess, setEditSuccess] = useState('')

  const buildScopedParams = (base: Record<string, string>) => {
    const params = new URLSearchParams(base)
    if (isScopedView) params.set('mine', '1')
    return params
  }

  useEffect(() => {
    setMasjidsLoading(true)
    apiService.getMasjids(isScopedView ? { mine: true } : {})
      .then((data) => setMasjids(data))
      .catch(() => setMasjids([]))
      .finally(() => setMasjidsLoading(false))
  }, [isScopedView])

  useEffect(() => {
    if (activePanel !== 'addresses') return

    setAddressesLoading(true)
    const params: { mine?: boolean; listAll?: boolean; masjidId?: number } = {
      mine: isScopedView,
      listAll: true,
    }
    if (selectedAddressMasjidId !== 'all') {
      params.masjidId = Number(selectedAddressMasjidId)
    }

    apiService.getAddresses(params)
      .then(setAddresses)
      .catch(() => setAddresses([]))
      .finally(() => setAddressesLoading(false))
  }, [activePanel, isScopedView, selectedAddressMasjidId])

  const togglePanel = (panel: ActivePanel) => {
    setActivePanel(prev => prev === panel ? null : panel)
  }

  const handleShowAllMasjids = () => {
    navigate(`/map?${buildScopedParams({ allMasjids: '1' }).toString()}`)
  }

  const handleShowAddressesOnMap = () => {
    if (selectedAddressMasjidId === 'all') {
      navigate(`/map?${buildScopedParams({ allAddresses: '1' }).toString()}`)
      return
    }
    navigate(`/map?${buildScopedParams({ masjidId: selectedAddressMasjidId }).toString()}`)
  }

  const handleRadiusSearch = () => {
    if (!navigator.geolocation) {
      setLocationError('Geolocation is not supported by your browser.')
      return
    }
    setLocationError('')
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const params = buildScopedParams({
          radius: String(selectedRadius),
          lat: String(position.coords.latitude),
          lng: String(position.coords.longitude),
        })
        navigate(`/map?${params.toString()}`)
      },
      () => setLocationError('Unable to get your location. Please enable location services.')
    )
  }

  const openEdit = (type: 'masjid' | 'address', item: Masjid | AddressRecord) => {
    setEditError('')
    setEditSuccess('')
    if (type === 'masjid') {
      const m = item as Masjid
      setEditDialog({
        type, id: m.id,
        fields: {
          name: m.name || '',
          houseNo: m.houseNo || '',
          aptNo: m.aptNo || '',
          streetName: m.streetName || '',
          city: m.city || '',
          state: m.state || '',
          zip: m.zip || '',
        },
      })
    } else {
      const a = item as AddressRecord
      setEditDialog({
        type, id: a.id,
        fields: {
          name: a.name || '',
          houseNo: a.houseNo || '',
          aptNo: a.aptNo || '',
          streetName: a.streetName || '',
          city: a.city || '',
          state: a.state || '',
          zip: a.zip || '',
          locality: a.locality || '',
          comments: a.comments || '',
        },
      })
    }
  }

  const handleSaveEdit = async () => {
    if (!editDialog) return
    setEditSaving(true)
    setEditError('')
    setEditSuccess('')
    try {
      await apiService.updateMyData(editDialog.type, editDialog.id, editDialog.fields)
      setEditSuccess('Saved successfully.')
      if (editDialog.type === 'masjid') {
        setMasjids(prev => prev.map(m => m.id === editDialog.id
          ? { ...m, ...editDialog.fields, name: editDialog.fields.name, address: [editDialog.fields.houseNo, editDialog.fields.streetName, editDialog.fields.city, editDialog.fields.state].filter(Boolean).join(' ') }
          : m
        ))
      } else {
        setAddresses(prev => prev.map(a => a.id === editDialog.id
          ? { ...a, ...editDialog.fields, name: editDialog.fields.name, address: [editDialog.fields.houseNo, editDialog.fields.streetName, editDialog.fields.city, editDialog.fields.state].filter(Boolean).join(' ') }
          : a
        ))
      }
      setTimeout(() => setEditDialog(null), 700)
    } catch (err) {
      setEditError(err instanceof Error ? err.message : 'Failed to save.')
    } finally {
      setEditSaving(false)
    }
  }

  const filteredMasjids = masjidSearch.trim()
    ? masjids.filter(m =>
        m.name.toLowerCase().includes(masjidSearch.toLowerCase()) ||
        (m.address || '').toLowerCase().includes(masjidSearch.toLowerCase())
      )
    : masjids

  const filteredAddresses = addressSearch.trim()
    ? addresses.filter(a =>
        a.name.toLowerCase().includes(addressSearch.toLowerCase()) ||
        (a.address || '').toLowerCase().includes(addressSearch.toLowerCase())
      )
    : addresses

  const masjidTotalPages = Math.max(1, Math.ceil(filteredMasjids.length / masjidPageSize))
  const addressTotalPages = Math.max(1, Math.ceil(filteredAddresses.length / addressPageSize))

  const pagedMasjids = filteredMasjids.slice(
    masjidPage * masjidPageSize,
    masjidPage * masjidPageSize + masjidPageSize
  )

  const pagedAddresses = filteredAddresses.slice(
    addressPage * addressPageSize,
    addressPage * addressPageSize + addressPageSize
  )

  useEffect(() => {
    if (masjidPage > masjidTotalPages - 1) {
      setMasjidPage(Math.max(0, masjidTotalPages - 1))
    }
  }, [masjidPage, masjidTotalPages])

  useEffect(() => {
    if (addressPage > addressTotalPages - 1) {
      setAddressPage(Math.max(0, addressTotalPages - 1))
    }
  }, [addressPage, addressTotalPages])

  return (
    <Box
      sx={{
        height: '100%',
        minHeight: 0,
        display: 'flex',
        flexDirection: 'column',
        px: { xs: 2, sm: 3 },
        py: { xs: 2, sm: 3 },
        gap: 2,
        overflow: 'auto',
      }}
    >
      <Typography variant="h5" sx={{ fontWeight: 700 }}>
        VIEW DATA
      </Typography>

      <Paper elevation={2} sx={{ p: 2, borderRadius: 3 }}>
        <Stack direction="row" spacing={1.5} sx={{ mb: 1.5 }}>
          <Button
            fullWidth
            variant={activePanel === 'masjids' ? 'contained' : 'outlined'}
            startIcon={<MapIcon />}
            onClick={() => togglePanel('masjids')}
          >
            View Masjids
          </Button>
          <Button
            fullWidth
            variant={activePanel === 'addresses' ? 'contained' : 'outlined'}
            color="secondary"
            startIcon={<LocationOnIcon />}
            onClick={() => togglePanel('addresses')}
          >
            View Addresses
          </Button>
          <Button
            fullWidth
            variant={activePanel === 'radius' ? 'contained' : 'outlined'}
            color="success"
            onClick={() => togglePanel('radius')}
          >
            View by Radius
          </Button>
        </Stack>

        {/* ── MASJIDS PANEL ── */}
        <Collapse in={activePanel === 'masjids'} unmountOnExit>
          <Paper variant="outlined" sx={{ p: 2, borderRadius: 2, mt: 1 }}>
            <Button
              fullWidth
              variant="contained"
              sx={{ mt: 1.5 }}
              onClick={handleShowAllMasjids}
              disabled={masjidsLoading}
            >
              Show on Map
            </Button>

            <Divider sx={{ my: 2 }} />

            <Typography variant="subtitle2" fontWeight={600} gutterBottom>
              Your Masjids ({filteredMasjids.length}{masjidSearch ? ` of ${masjids.length}` : ''})
            </Typography>
            <TextField
              fullWidth
              size="small"
              placeholder="Search by name or address…"
              value={masjidSearch}
              onChange={e => {
                setMasjidSearch(e.target.value)
                setMasjidPage(0)
              }}
              sx={{ mb: 1 }}
            />
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1, gap: 1 }}>
              <FormControl size="small" sx={{ minWidth: 110 }}>
                <InputLabel>Rows</InputLabel>
                <Select
                  value={masjidPageSize}
                  label="Rows"
                  onChange={(e) => {
                    setMasjidPageSize(Number(e.target.value))
                    setMasjidPage(0)
                  }}
                >
                  {PAGE_SIZE_OPTIONS.map((size) => (
                    <MenuItem key={size} value={size}>{size}</MenuItem>
                  ))}
                </Select>
              </FormControl>
              <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                <Button size="small" variant="outlined" disabled={masjidPage === 0} onClick={() => setMasjidPage((p) => Math.max(0, p - 1))}>
                  Previous
                </Button>
                <Typography variant="caption">Page {masjidTotalPages === 0 ? 0 : masjidPage + 1} of {masjidTotalPages}</Typography>
                <Button size="small" variant="outlined" disabled={masjidPage >= masjidTotalPages - 1} onClick={() => setMasjidPage((p) => Math.min(masjidTotalPages - 1, p + 1))}>
                  Next
                </Button>
              </Box>
            </Box>
            {masjidsLoading ? (
              <CircularProgress size={20} />
            ) : filteredMasjids.length === 0 ? (
              <Typography variant="body2" color="text.secondary">No masjids found.</Typography>
            ) : (
              <List dense disablePadding>
                {pagedMasjids.map(m => (
                  <ListItem
                    key={m.id}
                    disableGutters
                    divider
                    secondaryAction={canEdit ? (
                      <Tooltip title="Edit">
                        <IconButton size="small" onClick={() => openEdit('masjid', m)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    ) : undefined}
                    sx={{ pr: canEdit ? 5 : 0 }}
                  >
                    <ListItemText
                      primary={<Typography variant="body2" fontWeight={500}>{m.name}</Typography>}
                      secondary={m.address || [m.houseNo, m.streetName, m.city, m.state].filter(Boolean).join(', ') || '—'}
                    />
                  </ListItem>
                ))}
              </List>
            )}
          </Paper>
        </Collapse>

        {/* ── ADDRESSES PANEL ── */}
        <Collapse in={activePanel === 'addresses'} unmountOnExit>
          <Paper variant="outlined" sx={{ p: 2, borderRadius: 2, mt: 1 }}>
            <FormControl fullWidth size="small" sx={{ mb: 1.5 }}>
              <InputLabel>Masjid</InputLabel>
              <Select
                value={selectedAddressMasjidId}
                label="Masjid"
                onChange={(e) => {
                  setSelectedAddressMasjidId(String(e.target.value))
                  setAddressPage(0)
                }}
              >
                <MenuItem value="all">All Masjids</MenuItem>
                {masjids.map((m) => (
                  <MenuItem key={m.id} value={String(m.id)}>
                    {m.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <Button
              fullWidth
              variant="contained"
              color="secondary"
              sx={{ mt: 0.5 }}
              onClick={handleShowAddressesOnMap}
              disabled={addressesLoading}
            >
              Show on Map
            </Button>

            <Divider sx={{ my: 2 }} />

            <Typography variant="subtitle2" fontWeight={600} gutterBottom>
              Your Addresses ({filteredAddresses.length}{addressSearch ? ` of ${addresses.length}` : ''})
            </Typography>
            <TextField
              fullWidth
              size="small"
              placeholder="Search by name or address…"
              value={addressSearch}
              onChange={e => {
                setAddressSearch(e.target.value)
                setAddressPage(0)
              }}
              sx={{ mb: 1 }}
            />
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1, gap: 1 }}>
              <FormControl size="small" sx={{ minWidth: 110 }}>
                <InputLabel>Rows</InputLabel>
                <Select
                  value={addressPageSize}
                  label="Rows"
                  onChange={(e) => {
                    setAddressPageSize(Number(e.target.value))
                    setAddressPage(0)
                  }}
                >
                  {PAGE_SIZE_OPTIONS.map((size) => (
                    <MenuItem key={size} value={size}>{size}</MenuItem>
                  ))}
                </Select>
              </FormControl>
              <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                <Button size="small" variant="outlined" disabled={addressPage === 0} onClick={() => setAddressPage((p) => Math.max(0, p - 1))}>
                  Previous
                </Button>
                <Typography variant="caption">Page {addressTotalPages === 0 ? 0 : addressPage + 1} of {addressTotalPages}</Typography>
                <Button size="small" variant="outlined" disabled={addressPage >= addressTotalPages - 1} onClick={() => setAddressPage((p) => Math.min(addressTotalPages - 1, p + 1))}>
                  Next
                </Button>
              </Box>
            </Box>
            {addressesLoading ? (
              <Box display="flex" justifyContent="center" py={1}><CircularProgress size={20} /></Box>
            ) : filteredAddresses.length === 0 ? (
              <Typography variant="body2" color="text.secondary">No addresses found.</Typography>
            ) : (
              <List dense disablePadding>
                {pagedAddresses.map(a => (
                  <ListItem
                    key={a.id}
                    disableGutters
                    divider
                    secondaryAction={canEdit ? (
                      <Tooltip title="Edit">
                        <IconButton size="small" onClick={() => openEdit('address', a)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    ) : undefined}
                    sx={{ pr: canEdit ? 5 : 0 }}
                  >
                    <ListItemText
                      primary={<Typography variant="body2" fontWeight={500}>{a.name}</Typography>}
                      secondary={a.address || [a.houseNo, a.streetName, a.city, a.state].filter(Boolean).join(', ') || '—'}
                    />
                  </ListItem>
                ))}
              </List>
            )}
          </Paper>
        </Collapse>

        {/* ── RADIUS PANEL ── */}
        <Collapse in={activePanel === 'radius'} unmountOnExit>
          <Paper variant="outlined" sx={{ p: 2, borderRadius: 2, mt: 1 }}>
            <Typography variant="subtitle1" fontWeight={600} gutterBottom>
              Find Masjids Within a Radius Around You
            </Typography>
            {locationError && (
              <Alert severity="error" sx={{ mb: 2 }}>
                {locationError}
              </Alert>
            )}
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
              <FormControl size="small" sx={{ minWidth: 160 }}>
                <InputLabel>Distance</InputLabel>
                <Select
                  value={selectedRadius}
                  label="Distance"
                  onChange={(e) => setSelectedRadius(Number(e.target.value))}
                >
                  {RADIUS_OPTIONS.map((r) => (
                    <MenuItem key={r} value={r}>
                      {r} mile{r > 1 ? 's' : ''}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
              <Button variant="contained" color="success" onClick={handleRadiusSearch}>
                Search
              </Button>
            </Box>
          </Paper>
        </Collapse>
      </Paper>

      <Box sx={{ mt: 'auto', pt: 1 }}>
        <Button variant="text" onClick={() => navigate('/dashboard')}>
          Back to Dashboard
        </Button>
      </Box>

      {/* ── EDIT DIALOG ── */}
      <Dialog
        open={!!editDialog}
        onClose={() => !editSaving && setEditDialog(null)}
        maxWidth="xs"
        fullWidth
      >
        <DialogTitle>
          Edit {editDialog?.type === 'masjid' ? 'Masjid' : 'Address'}
        </DialogTitle>
        <DialogContent dividers>
          {editError && <Alert severity="error" sx={{ mb: 1 }}>{editError}</Alert>}
          {editSuccess && <Alert severity="success" sx={{ mb: 1 }}>{editSuccess}</Alert>}
          <Stack spacing={1.5} sx={{ pt: 0.5 }}>
            {(editDialog?.type === 'masjid' ? MASJID_FIELDS : ADDRESS_FIELDS).map(f => (
              <TextField
                key={f.key}
                label={f.label}
                size="small"
                fullWidth
                value={editDialog?.fields[f.key] ?? ''}
                onChange={e => setEditDialog(prev => prev
                  ? { ...prev, fields: { ...prev.fields, [f.key]: e.target.value } }
                  : prev
                )}
              />
            ))}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditDialog(null)} disabled={editSaving}>Cancel</Button>
          <Button variant="contained" onClick={handleSaveEdit} disabled={editSaving}>
            {editSaving ? 'Saving…' : 'Save'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  )
}

export default AreaSelection
