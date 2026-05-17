import React, { useEffect, useState } from 'react';
import { Box, Card, CardContent, CardHeader, Typography, Button, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, CircularProgress, Alert } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import EditIcon from '@mui/icons-material/Edit';
import { useNavigate } from 'react-router-dom';
import { AddressRecord } from '@/types';
import apiClient from '@/services/api';

const AllAddresses: React.FC = () => {
  const navigate = useNavigate();
  const [addresses, setAddresses] = useState<AddressRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchAddresses = async () => {
      try {
        setLoading(true);
        setError(null);
        const data = await apiClient.getAddresses({ listAll: true });
        setAddresses(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load addresses');
      } finally {
        setLoading(false);
      }
    };

    fetchAddresses();
  }, []);

  const handleEdit = () => {
    navigate('/area-selection');
  };

  const formatAddress = (addr: AddressRecord): string => {
    const parts = [];
    if (addr.houseNo) parts.push(addr.houseNo);
    if (addr.aptNo) parts.push(`Apt ${addr.aptNo}`);
    if (addr.streetName) parts.push(addr.streetName);
    if (addr.city) parts.push(addr.city);
    if (addr.state) parts.push(addr.state);
    if (addr.zip) parts.push(addr.zip);
    return parts.length > 0 ? parts.join(', ') : addr.address || 'N/A';
  };

  return (
    <Box sx={{ flex: 1, minHeight: 0, bgcolor: 'grey.100', display: 'flex', flexDirection: 'column', alignItems: 'stretch' }}>
      <Card elevation={1} sx={{ borderRadius: 0, p: 0, width: '100%', flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          title={<Typography variant="h6">All Addresses</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">{addresses.length} addresses found</Typography>}
          action={
            <Button
              startIcon={<ArrowBackIcon />}
              onClick={() => navigate('/view-users')}
              sx={{ mt: 1, mr: 1 }}
              size="small"
            >
              Back
            </Button>
          }
        />
        <CardContent sx={{ flex: 1, overflow: 'auto', p: 0 }}>
          {loading && (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '300px' }}>
              <CircularProgress />
            </Box>
          )}
          {error && <Alert severity="error">{error}</Alert>}
          {!loading && !error && addresses.length === 0 && (
            <Box sx={{ p: 2 }}>
              <Typography color="text.secondary">No addresses found.</Typography>
            </Box>
          )}
          {!loading && !error && addresses.length > 0 && (
            <TableContainer sx={{ height: '100%' }}>
              <Table stickyHeader size="small">
                <TableHead>
                  <TableRow sx={{ backgroundColor: 'grey.100' }}>
                    <TableCell sx={{ fontWeight: 600 }}>Name</TableCell>
                    <TableCell sx={{ fontWeight: 600 }}>Address</TableCell>
                    <TableCell sx={{ fontWeight: 600, width: 100, textAlign: 'center' }}>Action</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {addresses.map((addr) => (
                    <TableRow key={addr.id} hover>
                      <TableCell>{addr.name}</TableCell>
                      <TableCell>{formatAddress(addr)}</TableCell>
                      <TableCell sx={{ textAlign: 'center' }}>
                        <Button
                          startIcon={<EditIcon />}
                          onClick={handleEdit}
                          size="small"
                          variant="outlined"
                        >
                          Edit
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>
    </Box>
  );
};

export default AllAddresses;
