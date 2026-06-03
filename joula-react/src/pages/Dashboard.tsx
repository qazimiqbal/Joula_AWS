import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Box, Grid, Typography, Button, Paper, Card, CardHeader, CardContent } from '@mui/material';
import apiService from '@/services/api';
import { Masjid, PendingGeocodeRecord, PendingMasjidRecord } from '@/types';
import { useAuth } from '@/context/AuthContext';

const Dashboard: React.FC = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const roleBasedLevel = user?.orgRole === 'org_admin' || user?.orgRole === 'admin'
    ? 3
    : user?.orgRole === 'editor'
      ? 2
      : user?.orgRole === 'viewer'
        ? 1
        : 0;
  const permissionLevel = Math.max(user?.permissionLevel ?? 0, roleBasedLevel, user?.role === 'admin' ? 3 : 1);
  const isSuperAdmin = permissionLevel >= 4;
  const isChildEditor = permissionLevel === 2;
  const isViewer = permissionLevel <= 1;
  const canManageOwnData = permissionLevel >= 2;
  const [pendingMasjids, setPendingMasjids] = useState<PendingMasjidRecord[]>([]);
  const [pendingAddresses, setPendingAddresses] = useState<PendingGeocodeRecord[]>([]);
  const [missingCoordinatesCount, setMissingCoordinatesCount] = useState(0);
  const [pendingUsersCount, setPendingUsersCount] = useState(0);
  // availableMasjids powers address access/warning checks.
  // - child editors: org-scoped (mirror parent visibility)
  // - others: own uploads (including pending)
  const [availableMasjids, setAvailableMasjids] = useState<Masjid[]>([]);
  const [loadingAvailableMasjids, setLoadingAvailableMasjids] = useState(true);
  const showPendingMasjidsButton = !isViewer && pendingMasjids.length > 0;
  const showPendingAddressesButton = !isViewer;
  const canAddAddresses = canManageOwnData;
  const unifiedButtonSx = {
    bgcolor: '#1565c0',
    color: '#fff',
    fontWeight: 700,
    textTransform: 'none',
    '&:hover': {
      bgcolor: '#0d47a1',
    },
  };

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

  const loadAvailableMasjids = async (userId: number): Promise<Masjid[]> => {
    if (!userId) return [];
    if (isChildEditor) {
      return apiService.getMasjids({ orgScoped: true });
    }
    return apiService.getMasjids({ mine: true, includeOwnPending: true });
  };

  const loadPendingAddresses = async (isSuper: boolean, userId: number): Promise<PendingGeocodeRecord[]> => {
    if (isSuper) {
      return apiService.getAddressReviewList();
    }

    try {
      return await apiService.getMyPendingAddresses();
    } catch {
      return apiService.getAddressReviewList(userId);
    }
  };

  useEffect(() => {
    if (!user?.id || isViewer) {
      setPendingMasjids([]);
      setPendingAddresses([]);
      setMissingCoordinatesCount(0);
      setPendingUsersCount(0);
      setAvailableMasjids([]);
      setLoadingAvailableMasjids(false);
      return;
    }

    loadPendingMasjids(isSuperAdmin, user.id).then(setPendingMasjids).catch(() => setPendingMasjids([]));
    loadPendingAddresses(isSuperAdmin, user.id).then(setPendingAddresses).catch(() => setPendingAddresses([]));
    if (isSuperAdmin) {
      apiService.getMissingCoordinates()
        .then((rows) => setMissingCoordinatesCount(rows.length))
        .catch(() => setMissingCoordinatesCount(0));
    } else {
      setMissingCoordinatesCount(0);
    }
    if (isSuperAdmin) {
      apiService.getPendingUsers()
        .then((rows) => setPendingUsersCount(rows.length))
        .catch(() => setPendingUsersCount(0));
    } else {
      setPendingUsersCount(0);
    }

    setLoadingAvailableMasjids(true);
    loadAvailableMasjids(user.id)
      .then(setAvailableMasjids)
      .catch(() => setAvailableMasjids([]))
      .finally(() => setLoadingAvailableMasjids(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isSuperAdmin, isViewer, isChildEditor, user?.id]);

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
          title={<Typography variant="h6">Dashboard</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          {/* Approve Masjid button. For non-super users always show to access self-approval screen. */}
          {showPendingMasjidsButton && (
            <Button
              variant="contained"
              sx={{ ...unifiedButtonSx, mb: 3, letterSpacing: 1, minWidth: 260 }}
              onClick={() => navigate('/pending-masjids')}
            >
              Approve Masjid
              {pendingMasjids.length > 0 && (
                <span style={{
                  background: '#ff7043',
                  color: '#fff',
                  borderRadius: '12px',
                  padding: '2px 10px',
                  marginLeft: 12,
                  fontWeight: 700,
                  fontSize: 14,
                  display: 'inline-block',
                }}>{pendingMasjids.length}</span>
              )}
            </Button>
          )}

          <Paper elevation={0} sx={{ p: 2 }}>
            <Grid container spacing={2}>
              {user && (
                <Grid item xs={12} sm={6} md={4}>
                  <Button fullWidth variant="contained" sx={unifiedButtonSx} onClick={() => navigate('/area-selection')}>
                    View Data
                  </Button>
                </Grid>
              )}

              {showPendingAddressesButton && (
                <Grid item xs={12} sm={6} md={4}>
                  <Button
                    fullWidth
                    variant="contained"
                    sx={{ ...unifiedButtonSx, letterSpacing: 1 }}
                    onClick={() => navigate('/pending-addresses')}
                  >
                    Approve Addresses
                    {pendingAddresses.length > 0 && (
                      <span style={{
                        background: '#d32f2f',
                        color: '#fff',
                        borderRadius: '12px',
                        padding: '2px 10px',
                        marginLeft: 12,
                        fontWeight: 700,
                        fontSize: 14,
                        display: 'inline-block',
                      }}>{pendingAddresses.length}</span>
                    )}
                  </Button>
                </Grid>
              )}

              {canManageOwnData && (
                <>
                  {canAddAddresses && (
                    <Grid item xs={12} sm={6} md={4}>
                      <Button
                        fullWidth
                        variant="contained"
                        sx={unifiedButtonSx}
                        onClick={() => navigate('/addresses/new')}
                      >
                        Add New Address
                      </Button>
                    </Grid>
                  )}
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="contained"
                      sx={unifiedButtonSx}
                      onClick={() => navigate('/masjids/new')}
                    >
                      Add New Masjid
                    </Button>
                  </Grid>
                </>
              )}

              {canManageOwnData && !isSuperAdmin && !loadingAvailableMasjids && availableMasjids.length === 0 && (
                <Grid item xs={12}>
                  <Typography color="warning.main">
                    Add a masjid first before adding addresses. New addresses must be associated with one of your approved masjids.
                  </Typography>
                </Grid>
              )}

              {isSuperAdmin && (
                <>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="contained"
                      sx={unifiedButtonSx}
                      onClick={() => navigate('/missing-coordinates')}
                    >
                      Fix Missing Coordinates
                      {missingCoordinatesCount > 0 && (
                        <span style={{
                          background: '#d32f2f',
                          color: '#fff',
                          borderRadius: '12px',
                          padding: '2px 10px',
                          marginLeft: 12,
                          fontWeight: 700,
                          fontSize: 14,
                          display: 'inline-block',
                        }}>{missingCoordinatesCount}</span>
                      )}
                    </Button>
                  </Grid>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="contained"
                      sx={unifiedButtonSx}
                      onClick={() => navigate('/pending-users')}
                    >
                      Approve New Users
                      {pendingUsersCount > 0 && (
                        <span style={{
                          background: '#d32f2f',
                          color: '#fff',
                          borderRadius: '12px',
                          padding: '2px 10px',
                          marginLeft: 12,
                          fontWeight: 700,
                          fontSize: 14,
                          display: 'inline-block',
                        }}>{pendingUsersCount}</span>
                      )}
                    </Button>
                  </Grid>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="contained"
                      sx={unifiedButtonSx}
                      onClick={() => navigate('/geocode-review')}
                    >
                      Review/Approve Submissions
                    </Button>
                  </Grid>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="contained"
                      sx={unifiedButtonSx}
                      onClick={() => navigate('/view-users')}
                    >
                      View Users
                    </Button>
                  </Grid>
                </>
              )}

              {permissionLevel >= 2 && (
                <>
                  {permissionLevel >= 3 && !isSuperAdmin && (
                    <Grid item xs={12} sm={6} md={4}>
                      <Button
                        fullWidth
                        variant="contained"
                        sx={unifiedButtonSx}
                        onClick={() => navigate('/build-team')}
                      >
                        Manage Team
                      </Button>
                    </Grid>
                  )}
                  {permissionLevel === 3 && (
                    <Grid item xs={12} sm={6} md={4}>
                      <Button
                        fullWidth
                        variant="contained"
                        sx={unifiedButtonSx}
                        onClick={() => navigate('/billing')}
                      >
                        Subscription &amp; Billing
                      </Button>
                    </Grid>
                  )}
                  {isSuperAdmin && (
                    <Grid item xs={12} sm={6} md={4}>
                      <Button
                        fullWidth
                        variant="contained"
                        sx={unifiedButtonSx}
                        onClick={() => navigate('/create-free-user')}
                      >
                        Add Free Editor User
                      </Button>
                    </Grid>
                  )}
                </>
              )}
            </Grid>
          </Paper>
        </CardContent>
      </Card>
    </Box>
  );
}

export default Dashboard;