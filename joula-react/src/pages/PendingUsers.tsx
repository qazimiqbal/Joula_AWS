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
  TableContainer,
  TableHead,
  TablePagination,
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
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)

  const permissionLevel = user?.permissionLevel ?? 0

  const loadPendingUsers = async () => {
    if (!user) return
    setLoading(true)
    setError('')
    try {
      const data = await apiService.getPendingUsers(user.id)
      setPendingUsers(data)
      setPage(0)
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

  const pagedPendingUsers = pendingUsers.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage)

  const handleChangePage = (_event: unknown, newPage: number) => {
    setPage(newPage)
  }

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10))
    setPage(0)
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
          <>
            <TableContainer sx={{ overflowX: 'auto' }}>
              <Table size="small" sx={{ minWidth: 760 }}>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Username</TableCell>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Email</TableCell>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Phone</TableCell>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>Created</TableCell>
                    <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {pagedPendingUsers.map((pendingUser) => (
                    <TableRow key={pendingUser.id}>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{pendingUser.username}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{pendingUser.email}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{pendingUser.phone}</TableCell>
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>{pendingUser.createdAt}</TableCell>
                      <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
                        <Button
                          size="small"
                          variant="text"
                          color="success"
                          sx={{ mr: 1 }}
                          onClick={() => handleReview(pendingUser.id, 'approve')}
                        >
                          Approve
                        </Button>
                        <Button
                          size="small"
                          variant="text"
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
            </TableContainer>
            <TablePagination
              component="div"
              count={pendingUsers.length}
              page={page}
              onPageChange={handleChangePage}
              rowsPerPage={rowsPerPage}
              onRowsPerPageChange={handleChangeRowsPerPage}
              rowsPerPageOptions={[5, 10, 25, 50]}
            />
          </>
        )}
      </Paper>
    </Box>
  )
}

export default PendingUsers
