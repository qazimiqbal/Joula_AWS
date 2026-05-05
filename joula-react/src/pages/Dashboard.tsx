import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Box, Grid, Typography, Button, Paper, Card, CardHeader, CardContent } from '@mui/material';
import apiService from '@/services/api';
import { Masjid, PendingMasjidRecord } from '@/types';
import { useAuth } from '@/context/AuthContext';

const Dashboard: React.FC = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const permissionLevel = user?.permissionLevel ?? (user?.role === 'admin' ? 3 : 1);
  const isSuperAdmin = permissionLevel >= 4;
  const isChildEditor = permissionLevel === 2;
  const isViewer = permissionLevel <= 1;
  const canManageOwnData = permissionLevel >= 2;
  const [pendingMasjids, setPendingMasjids] = useState<PendingMasjidRecord[]>([]);
  // availableMasjids powers address access/warning checks.
  // - child editors: org-scoped (mirror parent visibility)
  // - others: own uploads (including pending)
  const [availableMasjids, setAvailableMasjids] = useState<Masjid[]>([]);
  const [loadingAvailableMasjids, setLoadingAvailableMasjids] = useState(true);
  const showPendingMasjidsButton = !isViewer && pendingMasjids.length > 0;
  const canAddAddresses = canManageOwnData && (isSuperAdmin || (!loadingAvailableMasjids && availableMasjids.length > 0));

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

  useEffect(() => {
    if (!user?.id || isViewer) {
      setPendingMasjids([]);
      setAvailableMasjids([]);
      setLoadingAvailableMasjids(false);
      return;
    }

    loadPendingMasjids(isSuperAdmin, user.id).then(setPendingMasjids).catch(() => setPendingMasjids([]));

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
              color="warning"
              sx={{ mb: 3, fontWeight: 700, letterSpacing: 1, minWidth: 260 }}
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
              {isSuperAdmin && (
                <Grid item xs={12} sm={6} md={4}>
                  <Button fullWidth variant="contained" onClick={() => navigate('/area-selection')}>
                    View Data
                  </Button>
                </Grid>
              )}

              {canManageOwnData && (
                <>
                  {canAddAddresses && (
                    <Grid item xs={12} sm={6} md={4}>
                      <Button
                        fullWidth
                        variant="outlined"
                        onClick={() => navigate('/addresses/new')}
                      >
                        Add New Address (AWS)
                      </Button>
                    </Grid>
                  )}
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="outlined"
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

              {!isViewer && (
                <Grid item xs={12} sm={6} md={4}>
                  <Button
                    fullWidth
                    variant="outlined"
                    onClick={() => navigate('/account')}
                  >
                    Account Settings
                  </Button>
                </Grid>
              )}

              {isSuperAdmin && (
                <>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="outlined"
                      color="secondary"
                      onClick={() => navigate('/missing-coordinates')}
                    >
                      Fix Missing Coordinates
                    </Button>
                  </Grid>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="outlined"
                      color="warning"
                      onClick={() => navigate('/pending-users')}
                    >
                      Approve New Users
                    </Button>
                  </Grid>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="outlined"
                      color="info"
                      onClick={() => navigate('/geocode-review')}
                    >
                      Review/Approve Submissions
                    </Button>
                  </Grid>
                  <Grid item xs={12} sm={6} md={4}>
                    <Button
                      fullWidth
                      variant="outlined"
                      color="success"
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
                        color="primary"
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
                        variant="outlined"
                        onClick={() => navigate('/billing')}
                      >
                        Subscription &amp; Billing
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