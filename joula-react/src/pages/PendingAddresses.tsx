import React, { useEffect, useState } from 'react';
import {
  Box,
  Card,
  CardHeader,
  CardContent,
  Typography,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Button,
  CircularProgress,
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Stack,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import apiService from '@/services/api';
import { PendingGeocodeRecord } from '@/types';
import { useNavigate } from 'react-router-dom';
import { GoogleMap, Marker, useJsApiLoader } from '@react-google-maps/api';
import { useAuth } from '@/context/AuthContext';

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const hasValidCoordinates = (address: PendingGeocodeRecord): boolean =>
  typeof address.latitude === 'number' && typeof address.longitude === 'number';

const PendingAddresses: React.FC = () => {
  const { user } = useAuth();
  const { isLoaded } = useJsApiLoader({ googleMapsApiKey: GOOGLE_MAPS_API_KEY });
  const [pendingAddresses, setPendingAddresses] = useState<PendingGeocodeRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [mapOpen, setMapOpen] = useState(false);
  const [selectedAddress, setSelectedAddress] = useState<PendingGeocodeRecord | null>(null);
  const [approveLoading, setApproveLoading] = useState<number | null>(null);
  const [approveAllLoading, setApproveAllLoading] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [editSaving, setEditSaving] = useState(false);
  const [editDeleting, setEditDeleting] = useState(false);
  const [editForm, setEditForm] = useState<PendingGeocodeRecord | null>(null);
  const navigate = useNavigate();
  const permissionLevel = user?.permissionLevel ?? 0;
  const isSuperAdmin = permissionLevel >= 4;
  const canApprove = permissionLevel >= 2;

  const loadPendingAddresses = async (isSuper: boolean, userId: number): Promise<PendingGeocodeRecord[]> => {
    if (isSuper) {
      return apiService.getAddressReviewList();
    }
    return apiService.getAddressReviewList(userId);
  };

  useEffect(() => {
    fetchPendingAddresses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isSuperAdmin, user?.id]);

  const fetchPendingAddresses = async () => {
    setLoading(true);
    setError('');
    try {
      if (!user?.id) {
        setPendingAddresses([]);
        return;
      }

      const data = await loadPendingAddresses(isSuperAdmin, user.id);
      setPendingAddresses(data);
    } catch {
      setError('Failed to load pending addresses');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (id: number) => {
    setApproveLoading(id);
    setError('');
    try {
      await apiService.approveAddress(id);
      setPendingAddresses((prev) => prev.filter((a) => a.id !== id));
    } catch {
      setError('Failed to approve address');
    } finally {
      setApproveLoading(null);
    }
  };

  const handleApproveAll = async () => {
    setApproveAllLoading(true);
    setError('');
    try {
      await apiService.approveAllAddresses();
      await fetchPendingAddresses();
    } catch {
      setError('Failed to approve all pending addresses');
    } finally {
      setApproveAllLoading(false);
    }
  };

  const handleOpenEdit = (address: PendingGeocodeRecord) => {
    const coords =
      typeof address.latitude === 'number' && typeof address.longitude === 'number'
        ? `${address.latitude},${address.longitude}`
        : (address.coordinates || '');

    setEditForm({
      ...address,
      aptNo: address.aptNo || '',
      locality: address.locality || '',
      comments: address.comments || '',
      lastVisit: address.lastVisit || '',
      verified: address.verified === 'Y' ? 'Y' : 'N',
      masjid: address.masjid || '',
      coordinates: coords,
    });
    setEditOpen(true);
  };

  const handleSaveEdit = async () => {
    if (!editForm) return;
    setEditSaving(true);
    setError('');
    try {
      await apiService.updatePendingAddress(editForm.id, {
        name: editForm.name,
        houseNo: editForm.houseNo,
        aptNo: editForm.aptNo || '',
        streetName: editForm.streetName,
        city: editForm.city,
        state: editForm.state,
        zip: editForm.zip,
        locality: editForm.locality || '',
        comments: editForm.comments || '',
        lastVisit: editForm.lastVisit || '',
        masjid: editForm.masjid || '',
        verified: editForm.verified === 'Y' ? 'Y' : 'N',
        coordinates: editForm.coordinates || '',
      });
      await fetchPendingAddresses();
      setEditOpen(false);
      setEditForm(null);
    } catch {
      setError('Failed to update pending address');
    } finally {
      setEditSaving(false);
    }
  };

  const handleDeleteEdit = async () => {
    if (!editForm) return;
    setEditDeleting(true);
    setError('');
    try {
      await apiService.deleteAddress(editForm.id);
      setPendingAddresses((prev) => prev.filter((a) => a.id !== editForm.id));
      setEditOpen(false);
      setEditForm(null);
    } catch {
      setError('Failed to delete address');
    } finally {
      setEditDeleting(false);
    }
  };

  const handleViewMap = (address: PendingGeocodeRecord) => {
    setSelectedAddress(address);
    setMapOpen(true);
  };

  const handleCloseMap = () => {
    setMapOpen(false);
    setSelectedAddress(null);
  };

  if (mapOpen && selectedAddress) {
    const hasCoords = typeof selectedAddress.latitude === 'number' && typeof selectedAddress.longitude === 'number';
    return (
      <Box sx={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, zIndex: 1300, display: 'flex', flexDirection: 'column' }}>
        <Box sx={{ display: 'flex', alignItems: 'center', px: 1, py: 0.5, bgcolor: 'black', color: 'white', flexShrink: 0 }}>
          <IconButton onClick={handleCloseMap} sx={{ color: 'white', mr: 1 }} size="small">
            <ArrowBackIcon />
          </IconButton>
          <Typography variant="subtitle1" sx={{ flexGrow: 1 }}>{selectedAddress.name}</Typography>
          {canApprove && (
            <Button
              size="small"
              color="success"
              variant="contained"
              disabled={approveLoading === selectedAddress.id}
              onClick={() => { handleApprove(selectedAddress.id); handleCloseMap(); }}
            >
              {approveLoading === selectedAddress.id ? <CircularProgress size={16} /> : 'Approve'}
            </Button>
          )}
        </Box>
        <Box sx={{ flexGrow: 1 }}>
          {!hasCoords ? (
            <Box sx={{ p: 3 }}><Typography color="error">No coordinates available for this address.</Typography></Box>
          ) : !isLoaded ? (
            <Box sx={{ p: 3 }}><CircularProgress /></Box>
          ) : (
            <GoogleMap
              mapContainerStyle={{ height: '100%', width: '100%' }}
              center={{ lat: selectedAddress.latitude as number, lng: selectedAddress.longitude as number }}
              zoom={15}
              options={{ gestureHandling: 'greedy' }}
            >
              <Marker position={{ lat: selectedAddress.latitude as number, lng: selectedAddress.longitude as number }} />
            </GoogleMap>
          )}
        </Box>
      </Box>
    );
  }

  return (
    <Box sx={{
      flex: 1,
      minHeight: 0,
      bgcolor: 'grey.100',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'stretch',
    }}>
      <Card elevation={1} sx={{ borderRadius: 0, p: 0, width: '100%', maxWidth: '100%', flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          title={<Typography variant="h6">Pending Addresses for Approval</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">{isSuperAdmin ? 'Review and approve new address submissions.' : 'View your pending address submissions.'}</Typography>}
          action={
            canApprove && pendingAddresses.length > 0 ? (
              <Button
                color="success"
                variant="contained"
                onClick={handleApproveAll}
                disabled={approveAllLoading}
                sx={{ mt: 1, mr: 1, fontWeight: 700 }}
              >
                {approveAllLoading ? <CircularProgress size={18} /> : 'Approve All With Coordinates'}
              </Button>
            ) : undefined
          }
        />
        <CardContent sx={{ flex: 1 }}>
          {error && <Typography color="error" sx={{ mb: 1 }}>{error}</Typography>}
          {loading ? (
            <CircularProgress />
          ) : pendingAddresses.length === 0 ? (
            <Typography>No pending addresses.</Typography>
          ) : (
            <TableContainer component={Paper} sx={{ borderRadius: 2 }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Name</TableCell>
                    <TableCell>Address</TableCell>
                    <TableCell>Geocoding</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {pendingAddresses.map((address) => (
                    <TableRow key={address.id}>
                      <TableCell>{address.name}</TableCell>
                      <TableCell>{[address.houseNo, address.aptNo, address.streetName].filter(Boolean).join(' ')}</TableCell>
                      <TableCell>
                        {hasValidCoordinates(address) ? (
                          <Typography variant="body2" color="success.main" fontWeight={600}>Ready</Typography>
                        ) : (
                          <Typography variant="body2" color="warning.main" fontWeight={700}>Need Geocoding</Typography>
                        )}
                      </TableCell>
                      <TableCell>
                        <Button size="small" variant="outlined" sx={{ mr: 1 }} onClick={() => handleViewMap(address)}>
                          View on Map
                        </Button>
                        {canApprove && (
                          <Button size="small" color="success" variant="contained" sx={{ mr: 1, fontWeight: 700, letterSpacing: 1 }} onClick={() => handleApprove(address.id)} disabled={approveLoading === address.id}>
                            {approveLoading === address.id ? <CircularProgress size={18} /> : 'Approve'}
                          </Button>
                        )}
                        {canApprove && (
                          <Button size="small" variant="contained" color="warning" onClick={() => handleOpenEdit(address)}>
                            Edit
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>
      <Dialog open={editOpen} onClose={() => setEditOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Edit Pending Address</DialogTitle>
        <DialogContent>
          {editForm && (
            <Stack spacing={2} sx={{ mt: 1 }}>
              <TextField label="Name" value={editForm.name} onChange={(e) => setEditForm({ ...editForm, name: e.target.value })} fullWidth />
              <TextField label="House No" value={editForm.houseNo} onChange={(e) => setEditForm({ ...editForm, houseNo: e.target.value })} fullWidth />
              <TextField label="Apt No" value={editForm.aptNo || ''} onChange={(e) => setEditForm({ ...editForm, aptNo: e.target.value })} fullWidth />
              <TextField label="Street Name" value={editForm.streetName} onChange={(e) => setEditForm({ ...editForm, streetName: e.target.value })} fullWidth />
              <TextField label="City" value={editForm.city} onChange={(e) => setEditForm({ ...editForm, city: e.target.value })} fullWidth />
              <TextField label="State" value={editForm.state} onChange={(e) => setEditForm({ ...editForm, state: e.target.value })} fullWidth />
              <TextField label="Zip" value={editForm.zip} onChange={(e) => setEditForm({ ...editForm, zip: e.target.value })} fullWidth />
              <TextField label="Locality" value={editForm.locality || ''} onChange={(e) => setEditForm({ ...editForm, locality: e.target.value })} fullWidth />
              <TextField label="Masjid" value={editForm.masjid || ''} onChange={(e) => setEditForm({ ...editForm, masjid: e.target.value })} fullWidth />
              <TextField label="Verified (Y/N)" value={editForm.verified || 'N'} onChange={(e) => setEditForm({ ...editForm, verified: e.target.value.toUpperCase() === 'Y' ? 'Y' : 'N' })} fullWidth />
              <TextField label="Coordinates (lat,lng)" placeholder="e.g. 33.8619429,-84.037217" value={editForm.coordinates || ''} onChange={(e) => setEditForm({ ...editForm, coordinates: e.target.value })} fullWidth />
              <TextField label="Last Visit (YYYY-MM-DD)" value={editForm.lastVisit || ''} onChange={(e) => setEditForm({ ...editForm, lastVisit: e.target.value })} fullWidth />
              <TextField label="Comments" multiline rows={3} value={editForm.comments || ''} onChange={(e) => setEditForm({ ...editForm, comments: e.target.value })} fullWidth />
            </Stack>
          )}
        </DialogContent>
        <DialogActions sx={{ justifyContent: 'space-between' }}>
          <Button variant="contained" color="error" onClick={handleDeleteEdit} disabled={editSaving || editDeleting || !editForm}>
            {editDeleting ? <CircularProgress size={18} /> : 'Delete'}
          </Button>
          <Box sx={{ display: 'flex', gap: 1 }}>
            <Button onClick={() => setEditOpen(false)} disabled={editSaving || editDeleting}>Cancel</Button>
            <Button variant="contained" onClick={handleSaveEdit} disabled={editSaving || editDeleting || !editForm}>
              {editSaving ? <CircularProgress size={18} /> : 'Save'}
            </Button>
          </Box>
        </DialogActions>
      </Dialog>
      <Button variant="outlined" color="secondary" onClick={() => navigate('/dashboard')}>Back to Dashboard</Button>
    </Box>
  );
};

export default PendingAddresses;
