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
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import apiService from '@/services/api';
import { PendingMasjidRecord } from '@/types';
import { useNavigate } from 'react-router-dom';
import { GoogleMap, Marker, useJsApiLoader } from '@react-google-maps/api';
import { useAuth } from '@/context/AuthContext';

function getLatLngFromCoordinates(coordinates?: string): { lat: number, lng: number } | null {
  if (!coordinates) return null;
  const parts = coordinates.split(',');
  if (parts.length !== 2) return null;
  const lat = parseFloat(parts[0]);
  const lng = parseFloat(parts[1]);
  if (isNaN(lat) || isNaN(lng)) return null;
  return { lat, lng };
}

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const PendingMasjids: React.FC = () => {
  const { user } = useAuth();
  const { isLoaded } = useJsApiLoader({ googleMapsApiKey: GOOGLE_MAPS_API_KEY });
  const [pendingMasjids, setPendingMasjids] = useState<PendingMasjidRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [mapOpen, setMapOpen] = useState(false);
  const [selectedMasjid, setSelectedMasjid] = useState<PendingMasjidRecord | null>(null);
  const [approveLoading, setApproveLoading] = useState<number | null>(null);
  const navigate = useNavigate();
  const permissionLevel = user?.permissionLevel ?? 0;
  const isSuperAdmin = permissionLevel >= 4;
  const canApprove = permissionLevel >= 2;

  const loadPendingMasjids = async (isSuper: boolean, userId: number): Promise<PendingMasjidRecord[]> => {
    if (isSuper) {
      return apiService.getMasjidReviewList();
    }

    try {
      return await apiService.getMyPendingMasjids();
    } catch {
      return apiService.getMasjidReviewList(userId);
    }
  };

  useEffect(() => {
    fetchPendingMasjids();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isSuperAdmin, user?.id]);

  const fetchPendingMasjids = async () => {
    setLoading(true);
    setError('');
    try {
      if (!user?.id) {
        setPendingMasjids([]);
        return;
      }

      const data = await loadPendingMasjids(isSuperAdmin, user.id);
      setPendingMasjids(data);
    } catch (err) {
      setError('Failed to load pending masjids');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (id: number) => {
    setApproveLoading(id);
    setError('');
    try {
      await apiService.approveMasjid(id);
      setPendingMasjids((prev) => prev.filter((m) => m.id !== id));
    } catch (err) {
      setError('Failed to approve masjid');
    } finally {
      setApproveLoading(null);
    }
  };

  const handleViewMap = (masjid: PendingMasjidRecord) => {
    setSelectedMasjid(masjid);
    setMapOpen(true);
  };
  const handleCloseMap = () => {
    setMapOpen(false);
    setSelectedMasjid(null);
  };

  if (mapOpen && selectedMasjid) {
    const coords = getLatLngFromCoordinates(selectedMasjid.Coordinates);
    return (
      <Box sx={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, zIndex: 1300, display: 'flex', flexDirection: 'column' }}>
        <Box sx={{ display: 'flex', alignItems: 'center', px: 1, py: 0.5, bgcolor: 'black', color: 'white', flexShrink: 0 }}>
          <IconButton onClick={handleCloseMap} sx={{ color: 'white', mr: 1 }} size="small">
            <ArrowBackIcon />
          </IconButton>
          <Typography variant="subtitle1" sx={{ flexGrow: 1 }}>{selectedMasjid.name}</Typography>
          {(canApprove || selectedMasjid.createdBy === user?.id) && (
            <Button
              size="small"
              color="success"
              variant="contained"
              disabled={approveLoading === selectedMasjid.id}
              onClick={() => { handleApprove(selectedMasjid.id); handleCloseMap(); }}
            >
              {approveLoading === selectedMasjid.id ? <CircularProgress size={16} /> : 'Approve'}
            </Button>
          )}
        </Box>
        <Box sx={{ flexGrow: 1 }}>
          {!coords ? (
            <Box sx={{ p: 3 }}><Typography color="error">No coordinates available for this masjid.</Typography></Box>
          ) : !isLoaded ? (
            <Box sx={{ p: 3 }}><CircularProgress /></Box>
          ) : (
            <GoogleMap
              mapContainerStyle={{ height: '100%', width: '100%' }}
              center={{ lat: coords.lat, lng: coords.lng }}
              zoom={15}
            >
              <Marker position={{ lat: coords.lat, lng: coords.lng }} />
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
          title={<Typography variant="h6">Pending Masjids for Approval</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">{isSuperAdmin ? 'Review and approve new masjid submissions.' : 'View your pending masjid submissions.'}</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          {error && <Typography color="error" sx={{ mb: 1 }}>{error}</Typography>}
          {loading ? (
            <CircularProgress />
          ) : pendingMasjids.length === 0 ? (
            <Typography>No pending masjids.</Typography>
          ) : (
            <TableContainer component={Paper} sx={{ borderRadius: 2 }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Name</TableCell>
                    <TableCell>Address</TableCell>
                    <TableCell>City</TableCell>
                    <TableCell>State</TableCell>
                    <TableCell>Zip</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {pendingMasjids.map((masjid) => (
                    <TableRow key={masjid.id}>
                      <TableCell>{masjid.name}</TableCell>
                      <TableCell>{[masjid.houseNo, masjid.aptNo, masjid.streetName].filter(Boolean).join(' ')}</TableCell>
                      <TableCell>{masjid.city}</TableCell>
                      <TableCell>{masjid.state}</TableCell>
                      <TableCell>{masjid.zip}</TableCell>
                      <TableCell>
                        <Button size="small" variant="outlined" sx={{ mr: 1 }} onClick={() => handleViewMap(masjid)}>
                          View on Map
                        </Button>
                        {(canApprove || masjid.createdBy === user?.id) && (
                          <Button size="small" color="success" variant="contained" sx={{ fontWeight: 700, letterSpacing: 1 }} onClick={() => handleApprove(masjid.id)} disabled={approveLoading === masjid.id}>
                            {approveLoading === masjid.id ? <CircularProgress size={18} /> : 'Approve'}
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
      <Button variant="outlined" color="secondary" onClick={() => navigate('/dashboard')}>Back to Dashboard</Button>
    </Box>
  );
};

export default PendingMasjids;
