import React, { useEffect, useState } from 'react'
import {
  Box, Typography, Table, TableHead, TableRow, TableCell,
  TableBody, TableContainer, Paper, Button, Chip, CircularProgress,
  Alert, Divider, TablePagination, Stack
} from '@mui/material'
import PersonAddIcon from '@mui/icons-material/PersonAdd'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'
import { OrgUser, OrgUsersResponse } from '@/types'

const roleColor: Record<string, 'primary' | 'secondary' | 'default'> = {
  org_admin: 'primary',
  editor: 'secondary',
  viewer: 'default',
}

const OrgUsersPage: React.FC = () => {
  const { user } = useAuth()
  const [data, setData] = useState<OrgUsersResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [actionError, setActionError] = useState<string | null>(null)
  const [busyKey, setBusyKey] = useState<string | null>(null)
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)

  const reload = async () => {
    setLoading(true)
    const res = await apiService.getOrgUsers()
    setData(res)
    setPage(0)
    setLoading(false)
  }

  useEffect(() => { reload() }, [])

  const handleRemove = async (u: OrgUser) => {
    if (!confirm(`Remove ${u.username} from your organization?`)) return
    setBusyKey(`remove-${u.id}`)
    setActionError(null)
    try {
      await apiService.removeOrgUser(u.id)
      await reload()
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Error removing user'
      setActionError(msg)
    } finally {
      setBusyKey(null)
    }
  }

  const handleChangeRole = async (u: OrgUser, nextRole: 'editor' | 'viewer') => {
    if (!confirm(`Change ${u.username} to ${nextRole}?`)) return
    setBusyKey(`role-${u.id}`)
    setActionError(null)
    try {
      await apiService.setOrgUserRole(u.id, nextRole)
      await reload()
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Error updating role'
      setActionError(msg)
    } finally {
      setBusyKey(null)
    }
  }

  const isAdmin = user?.orgRole === 'org_admin' || user?.orgRole === 'admin' || (user?.permissionLevel ?? 0) >= 3
  const pagedUsers = data?.users.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage) ?? []

  const handleChangePage = (_event: unknown, newPage: number) => {
    setPage(newPage)
  }

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10))
    setPage(0)
  }

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', mt: 6 }}>
        <CircularProgress />
      </Box>
    )
  }

  return (
    <Box sx={{ p: 2, maxWidth: 640, mx: 'auto' }}>
      <Typography variant="h6" gutterBottom>Team Members</Typography>

      {actionError && <Alert severity="error" sx={{ mb: 2 }}>{actionError}</Alert>}

      {data && (
        <>
          <Box sx={{ display: 'flex', gap: 2, mb: 2, flexWrap: 'wrap' }}>
            <Typography variant="body2">
              <strong>Editors:</strong> {data.editorCount} / {data.maxEditors}
            </Typography>
            <Typography variant="body2">
              <strong>Viewers:</strong> {data.viewerCount} / {data.maxViewers}
            </Typography>
          </Box>

          <Divider sx={{ mb: 1.5 }} />

          <TableContainer component={Paper} variant="outlined" sx={{ overflowX: 'auto' }}>
            <Table size="small" sx={{ minWidth: 420 }}>
              <TableHead>
                <TableRow sx={{ bgcolor: 'grey.100' }}>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Name</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Email</TableCell>
                  <TableCell sx={{ whiteSpace: 'nowrap' }}>Role</TableCell>
                  {isAdmin && <TableCell sx={{ whiteSpace: 'nowrap' }}>Actions</TableCell>}
                </TableRow>
              </TableHead>
              <TableBody>
                {pagedUsers.map((u) => (
                  <TableRow key={u.id} hover>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                      {isAdmin ? (
                        <Button variant="text" onClick={() => window.location.href = `/org-users/${u.id}`}>{u.username}</Button>
                      ) : u.username}
                    </TableCell>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>{u.email}</TableCell>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                      <Chip
                        label={u.orgRole.replace('org_', '').replace('_', ' ')}
                        color={roleColor[u.orgRole] ?? 'default'}
                        size="small"
                      />
                    </TableCell>
                    {isAdmin && (
                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {u.orgRole !== 'org_admin' && u.orgRole !== 'admin' && (
                          <Stack direction="row" spacing={1}>
                            {u.orgRole !== 'editor' && (
                              <Button
                                variant="text"
                                size="small"
                                disabled={busyKey !== null}
                                onClick={() => handleChangeRole(u, 'editor')}
                              >
                                Make Editor
                              </Button>
                            )}
                            {u.orgRole !== 'viewer' && (
                              <Button
                                variant="text"
                                size="small"
                                disabled={busyKey !== null}
                                onClick={() => handleChangeRole(u, 'viewer')}
                              >
                                Make Viewer
                              </Button>
                            )}
                            <Button
                              variant="text"
                              color="error"
                              size="small"
                              disabled={busyKey !== null}
                              onClick={() => handleRemove(u)}
                            >
                              {busyKey === `remove-${u.id}` ? <CircularProgress size={16} /> : 'Remove'}
                            </Button>
                          </Stack>
                        )}
                      </TableCell>
                    )}
                  </TableRow>
                ))}
                {data.users.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={4} align="center">No team members yet.</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            component="div"
            count={data.users.length}
            page={page}
            onPageChange={handleChangePage}
            rowsPerPage={rowsPerPage}
            onRowsPerPageChange={handleChangeRowsPerPage}
            rowsPerPageOptions={[5, 10, 25, 50]}
          />

          {isAdmin && (
            <Alert severity="info" sx={{ mt: 2 }} icon={<PersonAddIcon />}>
              To add an editor or viewer, go to <strong>Pending Users</strong> and approve them —
              they will be added to your organization automatically.
            </Alert>
          )}
        </>
      )}
    </Box>
  )
}

export default OrgUsersPage
