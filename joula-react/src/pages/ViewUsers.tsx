import React, { useEffect, useState } from 'react';
import {
  Box, Card, CardHeader, CardContent, Typography, CircularProgress,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  Paper, Chip, IconButton, Tooltip, Stack,
} from '@mui/material';
import MosqueIcon from '@mui/icons-material/Mosque';
import PeopleIcon from '@mui/icons-material/People';
import NavigateBeforeIcon from '@mui/icons-material/NavigateBefore';
import NavigateNextIcon from '@mui/icons-material/NavigateNext';
import apiService from '@/services/api';
import { useAuth } from '@/context/AuthContext';
import { useNavigate } from 'react-router-dom';

const PAGE_SIZE = 10;

interface AdminUser {
  id: number;
  username: string;
  email: string;
  phone: string;
  orgId: number;
  orgName: string;
  permissions: number;
  orgRole: string;
  status: string;
  masjidCount: number;
  teamCount: number;
}

const ViewUsers: React.FC = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const permissionLevel = user?.permissionLevel ?? 0;

  const [users, setUsers] = useState<AdminUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [page, setPage] = useState(0);

  useEffect(() => {
    if (permissionLevel < 4) {
      navigate('/dashboard');
      return;
    }
    fetchUsers();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fetchUsers = async () => {
    setLoading(true);
    setError('');
    try {
      const data = await apiService.adminGetUsers();
      setUsers(data);
    } catch {
      setError('Failed to load users');
    } finally {
      setLoading(false);
    }
  };

  const totalPages = Math.ceil(users.length / PAGE_SIZE);
  const pageUsers = users.slice(page * PAGE_SIZE, page * PAGE_SIZE + PAGE_SIZE);

  return (
    <Box sx={{ flex: 1, minHeight: 0, bgcolor: 'grey.100', display: 'flex', flexDirection: 'column', alignItems: 'stretch' }}>
      <Card elevation={1} sx={{ borderRadius: 0, p: 0, width: '100%', flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          title={<Typography variant="h6">View Users</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">All users on the platform.</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          {error && <Typography color="error" sx={{ mb: 1 }}>{error}</Typography>}
          {loading ? (
            <CircularProgress />
          ) : users.length === 0 ? (
            <Typography>No users found.</Typography>
          ) : (
            <>
              <TableContainer component={Paper} sx={{ borderRadius: 2 }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Username</TableCell>
                      <TableCell>Email</TableCell>
                      <TableCell>Phone</TableCell>
                      <TableCell>Org</TableCell>
                      <TableCell>Role</TableCell>
                      <TableCell align="center">Masjids</TableCell>
                      <TableCell align="center">Team</TableCell>
                      <TableCell>Actions</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {pageUsers.map((u) => (
                      <TableRow key={u.id}>
                        <TableCell>{u.username}</TableCell>
                        <TableCell>{u.email}</TableCell>
                        <TableCell>{u.phone || '—'}</TableCell>
                        <TableCell>{u.orgName || '—'}</TableCell>
                        <TableCell>
                          <Chip
                            label={u.orgRole || (u.permissions >= 4 ? 'super_admin' : 'admin')}
                            size="small"
                            color={u.permissions >= 4 ? 'error' : u.orgRole === 'editor' ? 'primary' : u.orgRole === 'viewer' ? 'default' : 'warning'}
                          />
                        </TableCell>
                        <TableCell align="center">
                          <Chip label={u.masjidCount} size="small" color={u.masjidCount > 0 ? 'primary' : 'default'} />
                        </TableCell>
                        <TableCell align="center">
                          <Chip label={u.teamCount} size="small" color={u.teamCount > 0 ? 'secondary' : 'default'} />
                        </TableCell>
                        <TableCell>
                          <Tooltip title="View Masjids">
                            <IconButton size="small" color="primary"
                              onClick={() => navigate(`/admin/users/${u.id}/masjids?username=${encodeURIComponent(u.username)}`)}>
                              <MosqueIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title="View Team (Editors & Visitors)">
                            <IconButton size="small" color="secondary"
                              onClick={() => navigate(`/admin/users/${u.id}/team?username=${encodeURIComponent(u.username)}`)}>
                              <PeopleIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
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

export default ViewUsers;
