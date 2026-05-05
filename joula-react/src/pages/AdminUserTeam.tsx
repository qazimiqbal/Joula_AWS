import React, { useEffect, useState } from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import {
  Box, Card, CardHeader, CardContent, Typography, CircularProgress,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  Paper, Button, Chip, Stack, IconButton, Dialog, DialogTitle,
  DialogContent, DialogActions, TextField, Alert,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import NavigateBeforeIcon from '@mui/icons-material/NavigateBefore';
import NavigateNextIcon from '@mui/icons-material/NavigateNext';
import EditIcon from '@mui/icons-material/Edit';
import apiService from '@/services/api';
import { useAuth } from '@/context/AuthContext';

const PAGE_SIZE = 10;

interface TeamMember {
  id: number;
  username: string;
  email: string;
  phone: string;
  orgRole: string;
  status: string;
}

const AdminUserTeam: React.FC = () => {
  const { userId } = useParams<{ userId: string }>();
  const [searchParams] = useSearchParams();
  const username = searchParams.get('username') || `User ${userId}`;
  const navigate = useNavigate();
  const { user } = useAuth();
  const permissionLevel = user?.permissionLevel ?? 0;

  const [team, setTeam] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [page, setPage] = useState(0);

  const [editMember, setEditMember] = useState<TeamMember | null>(null);
  const [editForm, setEditForm] = useState({ email: '', phone: '', password: '', confirmPassword: '' });
  const [editLoading, setEditLoading] = useState(false);
  const [editError, setEditError] = useState('');
  const [editSuccess, setEditSuccess] = useState('');

  useEffect(() => {
    if (permissionLevel < 4) { navigate('/dashboard'); return; }
    fetchTeam();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  const fetchTeam = async () => {
    setLoading(true);
    setError('');
    try {
      const data = await apiService.adminGetUserTeam(Number(userId));
      setTeam(data);
    } catch {
      setError('Failed to load team');
    } finally {
      setLoading(false);
    }
  };

  const openEdit = (member: TeamMember) => {
    setEditMember(member);
    setEditForm({ email: member.email, phone: member.phone || '', password: '', confirmPassword: '' });
    setEditError('');
    setEditSuccess('');
  };

  const handleEditChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setEditForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleEditSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editMember) return;
    setEditError('');
    setEditSuccess('');

    if (!editForm.email.trim()) { setEditError('Email is required'); return; }
    if (editForm.password || editForm.confirmPassword) {
      if (editForm.password !== editForm.confirmPassword) { setEditError('Passwords do not match'); return; }
      if (editForm.password.length < 4) { setEditError('Password must be at least 4 characters'); return; }
    }

    setEditLoading(true);
    try {
      await apiService.adminUpdateUser(editMember.id, {
        email: editForm.email.trim(),
        phone: editForm.phone.trim(),
        password: editForm.password.trim() || undefined,
      });
      setTeam((prev) => prev.map((m) =>
        m.id === editMember.id ? { ...m, email: editForm.email.trim(), phone: editForm.phone.trim() } : m
      ));
      setEditSuccess('User updated successfully');
      setEditForm((prev) => ({ ...prev, password: '', confirmPassword: '' }));
    } catch (err: unknown) {
      setEditError(err instanceof Error ? err.message : 'Failed to update user');
    } finally {
      setEditLoading(false);
    }
  };

  const totalPages = Math.ceil(team.length / PAGE_SIZE);
  const pageTeam = team.slice(page * PAGE_SIZE, page * PAGE_SIZE + PAGE_SIZE);

  return (
    <Box sx={{ flex: 1, minHeight: 0, bgcolor: 'grey.100', display: 'flex', flexDirection: 'column' }}>
      <Card elevation={1} sx={{ borderRadius: 0, flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          avatar={
            <IconButton onClick={() => navigate('/view-users')} size="small">
              <ArrowBackIcon />
            </IconButton>
          }
          title={<Typography variant="h6">Team — {username}</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">Editors and visitors under this account.</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          {error && <Typography color="error" sx={{ mb: 1 }}>{error}</Typography>}
          {loading ? (
            <CircularProgress />
          ) : team.length === 0 ? (
            <Typography>No editors or visitors found for this user.</Typography>
          ) : (
            <>
              <TableContainer component={Paper} sx={{ borderRadius: 2 }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Username</TableCell>
                      <TableCell>Email</TableCell>
                      <TableCell>Phone</TableCell>
                      <TableCell>Role</TableCell>
                      <TableCell>Status</TableCell>
                      <TableCell>Actions</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {pageTeam.map((m) => (
                      <TableRow key={m.id}>
                        <TableCell>{m.username}</TableCell>
                        <TableCell>{m.email}</TableCell>
                        <TableCell>{m.phone || '—'}</TableCell>
                        <TableCell>
                          <Chip label={m.orgRole} size="small" color={m.orgRole === 'editor' ? 'primary' : 'default'} />
                        </TableCell>
                        <TableCell>{m.status}</TableCell>
                        <TableCell>
                          <Button
                            size="small"
                            variant="outlined"
                            startIcon={<EditIcon />}
                            onClick={() => openEdit(m)}
                          >
                            Edit
                          </Button>
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

      {/* Edit Dialog */}
      <Dialog open={!!editMember} onClose={() => !editLoading && setEditMember(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Edit — {editMember?.username}</DialogTitle>
        <DialogContent>
          {editError && <Alert severity="error" sx={{ mb: 1 }}>{editError}</Alert>}
          {editSuccess && <Alert severity="success" sx={{ mb: 1 }}>{editSuccess}</Alert>}
          <form id="edit-team-form" onSubmit={handleEditSubmit}>
            <TextField
              fullWidth label="Email" name="email" type="email"
              value={editForm.email} onChange={handleEditChange}
              margin="normal" disabled={editLoading}
            />
            <TextField
              fullWidth label="Phone" name="phone"
              value={editForm.phone} onChange={handleEditChange}
              margin="normal" disabled={editLoading}
            />
            <TextField
              fullWidth label="New Password" name="password" type="password"
              value={editForm.password} onChange={handleEditChange}
              margin="normal" disabled={editLoading}
              helperText="Leave blank to keep current password"
            />
            <TextField
              fullWidth label="Re-enter New Password" name="confirmPassword" type="password"
              value={editForm.confirmPassword} onChange={handleEditChange}
              margin="normal" disabled={editLoading}
            />
          </form>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditMember(null)} disabled={editLoading}>Cancel</Button>
          <Button type="submit" form="edit-team-form" variant="contained" disabled={editLoading}>
            {editLoading ? <CircularProgress size={18} /> : 'Save'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default AdminUserTeam;
