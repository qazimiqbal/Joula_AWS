import React, { useEffect, useState } from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import {
  Box, Card, CardHeader, CardContent, Typography, CircularProgress,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  Paper, Button, Chip, Stack, IconButton,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import NavigateBeforeIcon from '@mui/icons-material/NavigateBefore';
import NavigateNextIcon from '@mui/icons-material/NavigateNext';
import apiService from '@/services/api';
import { useAuth } from '@/context/AuthContext';

const PAGE_SIZE = 10;

interface AdminMasjid {
  id: number;
  name: string;
  houseNo: string;
  aptNo?: string;
  streetName: string;
  city: string;
  state: string;
  zip: string;
  approved: boolean;
}

const AdminUserMasjids: React.FC = () => {
  const { userId } = useParams<{ userId: string }>();
  const [searchParams] = useSearchParams();
  const username = searchParams.get('username') || `User ${userId}`;
  const navigate = useNavigate();
  const { user } = useAuth();
  const permissionLevel = user?.permissionLevel ?? 0;

  const [masjids, setMasjids] = useState<AdminMasjid[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [page, setPage] = useState(0);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  useEffect(() => {
    if (permissionLevel < 4) { navigate('/dashboard'); return; }
    fetchMasjids();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  const fetchMasjids = async () => {
    setLoading(true);
    setError('');
    try {
      const data = await apiService.adminGetUserMasjids(Number(userId));
      setMasjids(data);
    } catch {
      setError('Failed to load masjids');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (id: number) => {
    setActionLoading(id);
    try {
      await apiService.approveMasjid(id);
      setMasjids((prev) => prev.map((m) => m.id === id ? { ...m, approved: true } : m));
    } catch {
      setError('Failed to approve masjid');
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Delete this masjid permanently?')) return;
    setActionLoading(id);
    try {
      await apiService.deleteMasjid(id);
      setMasjids((prev) => prev.filter((m) => m.id !== id));
      const newTotal = masjids.length - 1;
      const newTotalPages = Math.ceil(newTotal / PAGE_SIZE);
      if (page >= newTotalPages && page > 0) setPage(page - 1);
    } catch {
      setError('Failed to delete masjid');
    } finally {
      setActionLoading(null);
    }
  };

  const totalPages = Math.ceil(masjids.length / PAGE_SIZE);
  const pageMasjids = masjids.slice(page * PAGE_SIZE, page * PAGE_SIZE + PAGE_SIZE);

  return (
    <Box sx={{ flex: 1, minHeight: 0, bgcolor: 'grey.100', display: 'flex', flexDirection: 'column' }}>
      <Card elevation={1} sx={{ borderRadius: 0, flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          avatar={
            <IconButton onClick={() => navigate('/view-users')} size="small">
              <ArrowBackIcon />
            </IconButton>
          }
          title={<Typography variant="h6">Masjids — {username}</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">{masjids.length} masjid(s) total</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          {error && <Typography color="error" sx={{ mb: 1 }}>{error}</Typography>}
          {loading ? (
            <CircularProgress />
          ) : masjids.length === 0 ? (
            <Typography>No masjids found for this user.</Typography>
          ) : (
            <>
              <TableContainer component={Paper} sx={{ borderRadius: 2 }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Name</TableCell>
                      <TableCell>Address</TableCell>
                      <TableCell>City</TableCell>
                      <TableCell>State</TableCell>
                      <TableCell>Zip</TableCell>
                      <TableCell>Status</TableCell>
                      <TableCell>Actions</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {pageMasjids.map((m) => (
                      <TableRow key={m.id}>
                        <TableCell>{m.name}</TableCell>
                        <TableCell>{[m.houseNo, m.aptNo, m.streetName].filter(Boolean).join(' ')}</TableCell>
                        <TableCell>{m.city}</TableCell>
                        <TableCell>{m.state}</TableCell>
                        <TableCell>{m.zip}</TableCell>
                        <TableCell>
                          <Chip
                            label={m.approved ? 'Approved' : 'Pending'}
                            size="small"
                            color={m.approved ? 'success' : 'warning'}
                          />
                        </TableCell>
                        <TableCell>
                          <Stack direction="row" spacing={1}>
                            {!m.approved && (
                              <Button
                                size="small"
                                variant="contained"
                                color="success"
                                disabled={actionLoading === m.id}
                                onClick={() => handleApprove(m.id)}
                              >
                                {actionLoading === m.id ? <CircularProgress size={14} /> : 'Approve'}
                              </Button>
                            )}
                            <Button
                              size="small"
                              variant="outlined"
                              color="error"
                              disabled={actionLoading === m.id}
                              onClick={() => handleDelete(m.id)}
                            >
                              {actionLoading === m.id ? <CircularProgress size={14} /> : 'Delete'}
                            </Button>
                          </Stack>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
              {totalPages > 1 && (
                <Stack direction="row" alignItems="center" justifyContent="center" spacing={2} sx={{ mt: 2 }}>
                  <IconButton onClick={() => setPage((p) => p - 1)} disabled={page === 0}>
                    <NavigateBeforeIcon />
                  </IconButton>
                  <Typography variant="body2">Page {page + 1} of {totalPages}</Typography>
                  <IconButton onClick={() => setPage((p) => p + 1)} disabled={page >= totalPages - 1}>
                    <NavigateNextIcon />
                  </IconButton>
                </Stack>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </Box>
  );
};

export default AdminUserMasjids;
