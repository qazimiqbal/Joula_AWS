import React, { useEffect, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'
import { PendingUser } from '@/types'

const PendingUsers: React.FC = () => {
  const { user } = useAuth()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [pendingUsers, setPendingUsers] = useState<PendingUser[]>([])

  const permissionLevel = user?.permissionLevel ?? 0

  const loadPendingUsers = async () => {
    if (!user) return
    setLoading(true)
    setError('')
    try {
      const data = await apiService.getPendingUsers(user.id)
      setPendingUsers(data)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to load pending users')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadPendingUsers()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id])

  const handleReview = async (userId: number, action: 'approve' | 'disapprove') => {
    if (!user) return
    setError('')
    setSuccess('')
    try {
      await apiService.reviewPendingUser(user.id, userId, action)
      setSuccess(`User ${action === 'approve' ? 'approved' : 'disapproved'} successfully`)
      setPendingUsers((prev) => prev.filter((u) => u.id !== userId))
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to update user status')
    }
  }

  if (permissionLevel < 3) {
    return <Alert severity="error">You do not have permission to access this page.</Alert>
  }

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        Pending User Approvals
      </Typography>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      <Paper elevation={1} sx={{ p: 2 }}>
        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
            <CircularProgress />
          </Box>
        ) : pendingUsers.length === 0 ? (
          <Typography variant="body1">No pending users found.</Typography>
        ) : (
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Username</TableCell>
                <TableCell>Email</TableCell>
                <TableCell>Phone</TableCell>
                <TableCell>Created</TableCell>
                <TableCell align="right">Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {pendingUsers.map((pendingUser) => (
                <TableRow key={pendingUser.id}>
                  <TableCell>{pendingUser.username}</TableCell>
                  <TableCell>{pendingUser.email}</TableCell>
                  <TableCell>{pendingUser.phone}</TableCell>
                  <TableCell>{pendingUser.createdAt}</TableCell>
                  <TableCell align="right">
                    <Button
                      size="small"
                      variant="contained"
                      color="success"
                      sx={{ mr: 1 }}
                      onClick={() => handleReview(pendingUser.id, 'approve')}
                    >
                      Approve
                    </Button>
                    <Button
                      size="small"
                      variant="outlined"
                      color="error"
                      onClick={() => handleReview(pendingUser.id, 'disapprove')}
                    >
                      Disapprove
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Paper>
    </Box>
  )
}

export default PendingUsers
