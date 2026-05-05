import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Box, Typography, Paper, Table, TableHead, TableRow, TableCell, TableBody, Button, CircularProgress, Dialog, DialogTitle, DialogContent, IconButton } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import apiService from '@/services/api';
import { User, Masjid, AddressRecord } from '@/types';

const OrgUserDetailsPage: React.FC = () => {
  const { userId } = useParams<{ userId: string }>();
  const navigate = useNavigate();
  const [user, setUser] = useState<User | null>(null);
  const [masjids, setMasjids] = useState<Masjid[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedMasjid, setSelectedMasjid] = useState<Masjid | null>(null);
  const [addresses, setAddresses] = useState<AddressRecord[]>([]);
  const [addressDialogOpen, setAddressDialogOpen] = useState(false);

  useEffect(() => {
    const fetchUserAndMasjids = async () => {
      setLoading(true);
      try {
        if (!userId) return;
        const userData = await apiService.getUser(Number(userId));
        setUser(userData);
        // Fetch masjids for this user (assume API supports filtering by user)
        const masjids = await apiService.getMasjids({ createdBy: Number(userId) });
        setMasjids(masjids);
      } finally {
        setLoading(false);
      }
    };
    fetchUserAndMasjids();
  }, [userId]);

  const handleViewAddresses = async (masjid: Masjid) => {
    setSelectedMasjid(masjid);
    setAddressDialogOpen(true);
    // Fetch addresses for this masjid (assume API supports filtering by masjid)
    const addresses = await apiService.getAddresses({ masjidId: masjid.id as number });
    setAddresses(addresses.filter(a => typeof a.latitude === 'number' && typeof a.longitude === 'number'));
  };

  const handleCloseDialog = () => {
    setAddressDialogOpen(false);
    setSelectedMasjid(null);
    setAddresses([]);
  };

  if (loading) {
    return <Box sx={{ display: 'flex', justifyContent: 'center', mt: 6 }}><CircularProgress /></Box>;
  }

  return (
    <Box sx={{ p: 2 }}>
      <Typography variant="h6" gutterBottom>User Details</Typography>
      {user && (
        <Paper sx={{ p: 2, mb: 2 }}>
          <Typography><strong>Name:</strong> {user.name}</Typography>
          <Typography><strong>Email:</strong> {user.email}</Typography>
          <Typography><strong>Phone:</strong> {user.phone}</Typography>
        </Paper>
      )}
      <Typography variant="subtitle1" sx={{ mb: 1 }}>Masjids Created by User</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Name</TableCell>
            <TableCell>Address</TableCell>
            <TableCell>Actions</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {masjids.map((masjid) => (
            <TableRow key={masjid.id}>
              <TableCell>{masjid.name}</TableCell>
              <TableCell>{masjid.address}</TableCell>
              <TableCell>
                <Button size="small" onClick={() => handleViewAddresses(masjid)}>
                  View Addresses
                </Button>
                <Button size="small" color="primary" onClick={() => navigate(`/masjids/edit/${masjid.id}`)}>
                  Edit
                </Button>
                <Button size="small" color="error" onClick={() => {/* TODO: delete masjid */}}>
                  Delete
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
      <Dialog open={addressDialogOpen} onClose={handleCloseDialog} maxWidth="md" fullWidth>
        <DialogTitle>
          Addresses for {selectedMasjid?.name}
          <IconButton aria-label="close" onClick={handleCloseDialog} sx={{ position: 'absolute', right: 8, top: 8 }}>
            <CloseIcon />
          </IconButton>
        </DialogTitle>
        <DialogContent>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Name</TableCell>
                <TableCell>Address</TableCell>
                <TableCell>Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {addresses.map((addr) => (
                <TableRow key={addr.id}>
                  <TableCell>{addr.name}</TableCell>
                  <TableCell>{addr.address}</TableCell>
                  <TableCell>
                    <Button size="small" color="primary" onClick={() => navigate(`/addresses/edit/${addr.id}`)}>
                      Edit
                    </Button>
                    <Button size="small" color="error" onClick={() => {/* TODO: delete address */}}>
                      Delete
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DialogContent>
      </Dialog>
    </Box>
  );
};

export default OrgUserDetailsPage;
