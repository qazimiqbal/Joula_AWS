import React from 'react';
import { Box, Card, CardContent, CardHeader, Typography, Button } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { useNavigate } from 'react-router-dom';

const AllAddresses: React.FC = () => {
  const navigate = useNavigate();

  return (
    <Box sx={{ flex: 1, minHeight: 0, bgcolor: 'grey.100', display: 'flex', flexDirection: 'column', alignItems: 'stretch' }}>
      <Card elevation={1} sx={{ borderRadius: 0, p: 0, width: '100%', flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          title={<Typography variant="h6">All Addresses</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">Coming soon.</Typography>}
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
        <CardContent>
          <Typography color="text.secondary">
            This page will display all addresses across all users. Feature coming soon.
          </Typography>
        </CardContent>
      </Card>
    </Box>
  );
};

export default AllAddresses;
