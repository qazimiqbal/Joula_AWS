import React, { useEffect, useState } from 'react'
import {
  Box,
  Card,
  CardHeader,
  CardContent,
  Typography,
  TextField,
  Button,
  Alert,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Stack,
  Divider,
  Collapse,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Chip,
  CircularProgress,
} from '@mui/material'
import apiService from '@/services/api'
import { useAuth } from '@/context/AuthContext'
import { Navigate } from 'react-router-dom'
import { OrgUser, OrgUsersResponse } from '@/types'

const roleColor: Record<string, 'primary' | 'secondary' | 'default'> = {
  org_admin: 'primary',
  admin: 'primary',
  editor: 'secondary',
  viewer: 'default',
}

const DEFAULT_MAX_EDITORS = 1
const DEFAULT_MAX_VIEWERS = 3

const BuildTeamPage: React.FC = () => {
  const { user } = useAuth()
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [role, setRole] = useState<'editor' | 'viewer'>('editor')
  const [loading, setLoading] = useState(false)
  const [successMessage, setSuccessMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [showCreateForm, setShowCreateForm] = useState(false)
  const [teamData, setTeamData] = useState<OrgUsersResponse | null>(null)
  const [teamLoading, setTeamLoading] = useState(true)
  const [teamError, setTeamError] = useState('')
  const [busyKey, setBusyKey] = useState<string | null>(null)

  const editorCount = teamData?.editorCount ?? 0
  const viewerCount = teamData?.viewerCount ?? 0
  const maxEditors = (teamData?.maxEditors ?? 0) > 0 ? (teamData?.maxEditors ?? 0) : DEFAULT_MAX_EDITORS
  const maxViewers = (teamData?.maxViewers ?? 0) > 0 ? (teamData?.maxViewers ?? 0) : DEFAULT_MAX_VIEWERS
  const editorsLeft = Math.max(maxEditors - editorCount, 0)
  const viewersLeft = Math.max(maxViewers - viewerCount, 0)
  const canCreateEditor = editorsLeft > 0
  const canCreateViewer = viewersLeft > 0
  const selectedRoleSeatsLeft = role === 'editor' ? editorsLeft : viewersLeft

  const loadTeam = async () => {
    setTeamLoading(true)
    setTeamError('')
    try {
      const res = await apiService.getOrgUsers()
      setTeamData(res)
      if (!res) {
        setTeamError('Unable to load team members right now.')
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load team members'
      setTeamError(message)
    } finally {
      setTeamLoading(false)
    }
  }

  useEffect(() => {
    loadTeam()
  }, [])

  useEffect(() => {
    if (role === 'editor' && editorsLeft <= 0 && viewersLeft > 0) {
      setRole('viewer')
    }
    if (role === 'viewer' && viewersLeft <= 0 && editorsLeft > 0) {
      setRole('editor')
    }
  }, [role, editorsLeft, viewersLeft])

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSuccessMessage('')
    setErrorMessage('')

    if (!username.trim() || !email.trim() || !phone.trim()) {
      setErrorMessage('Username, email, and phone are required.')
      return
    }

    setLoading(true)
    try {
      await apiService.createTeamUser({
        username: username.trim(),
        email: email.trim(),
        phone: phone.trim(),
        role,
      })
      setSuccessMessage(`Team member created as ${role}.`)
      setUsername('')
      setEmail('')
      setPhone('')
      setRole('editor')
      setShowCreateForm(false)
      await loadTeam()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to create team member'
      setErrorMessage(message)
    } finally {
      setLoading(false)
    }
  }

  const handleRemove = async (member: OrgUser) => {
    if (!confirm(`Remove ${member.username} from your organization?`)) return
    setBusyKey(`remove-${member.id}`)
    setTeamError('')
    try {
      await apiService.removeOrgUser(member.id)
      await loadTeam()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to remove member'
      setTeamError(message)
    } finally {
      setBusyKey(null)
    }
  }

  const handleRoleChange = async (member: OrgUser, nextRole: 'editor' | 'viewer') => {
    if (!confirm(`Change ${member.username} to ${nextRole}?`)) return
    setBusyKey(`role-${member.id}`)
    setTeamError('')
    try {
      await apiService.setOrgUserRole(member.id, nextRole)
      await loadTeam()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to update member role'
      setTeamError(message)
    } finally {
      setBusyKey(null)
    }
  }

  const permissionLevel = user?.permissionLevel ?? (user?.role === 'admin' ? 3 : 1)
  if (permissionLevel !== 3) {
    return <Navigate to="/dashboard" replace />
  }

  const canPromoteMemberToEditor = (member: OrgUser) => {
    if (member.orgRole === 'editor') return true
    return editorsLeft > 0
  }

  const canChangeMemberToViewer = (member: OrgUser) => {
    if (member.orgRole === 'viewer') return true
    return viewersLeft > 0
  }

  return (
    <Box
      sx={{
        flex: 1,
        minHeight: 0,
        bgcolor: 'grey.100',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'stretch',
      }}
    >
      <Card elevation={1} sx={{ borderRadius: 0, p: 0, width: '100%', maxWidth: '100%', flex: 1, display: 'flex', flexDirection: 'column' }}>
        <CardHeader
          title={<Typography variant="h6">Manage Team</Typography>}
          subheader={<Typography variant="body2" color="text.secondary">Create editor and viewer accounts for your organization.</Typography>}
        />
        <CardContent sx={{ flex: 1 }}>
          <Typography variant="subtitle2" sx={{ mb: 1 }}>Seat Availability</Typography>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mb: 2 }}>
            <Alert severity={editorsLeft > 0 ? 'info' : 'warning'} sx={{ py: 0 }}>
              Editors: {editorCount}/{maxEditors} used ({editorsLeft} left)
            </Alert>
            <Alert severity={viewersLeft > 0 ? 'info' : 'warning'} sx={{ py: 0 }}>
              Viewers: {viewerCount}/{maxViewers} used ({viewersLeft} left)
            </Alert>
          </Stack>

          <Typography variant="subtitle1" sx={{ mb: 1 }}>Existing Team Members</Typography>
          {teamError && <Alert severity="error" sx={{ mb: 2 }}>{teamError}</Alert>}

          {teamLoading ? (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 2 }}>
              <CircularProgress size={24} />
            </Box>
          ) : (
            <TableContainer component={Paper} variant="outlined">
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Name</TableCell>
                    <TableCell>Email</TableCell>
                    <TableCell>Role</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {(teamData?.users ?? []).map((member) => (
                    <TableRow key={member.id}>
                      <TableCell>{member.username}</TableCell>
                      <TableCell>{member.email}</TableCell>
                      <TableCell>
                        <Chip
                          size="small"
                          label={member.orgRole.replace('org_', '').replace('_', ' ')}
                          color={roleColor[member.orgRole] ?? 'default'}
                        />
                      </TableCell>
                      <TableCell>
                        {member.orgRole !== 'org_admin' && member.orgRole !== 'admin' && (
                          <Stack direction="row" spacing={1}>
                            {member.orgRole !== 'editor' && (
                              <Button
                                size="small"
                                disabled={busyKey !== null || !canPromoteMemberToEditor(member)}
                                onClick={() => handleRoleChange(member, 'editor')}
                              >
                                Make Editor
                              </Button>
                            )}
                            {member.orgRole !== 'viewer' && (
                              <Button
                                size="small"
                                disabled={busyKey !== null || !canChangeMemberToViewer(member)}
                                onClick={() => handleRoleChange(member, 'viewer')}
                              >
                                Make Viewer
                              </Button>
                            )}
                            <Button
                              size="small"
                              color="error"
                              disabled={busyKey !== null}
                              onClick={() => handleRemove(member)}
                            >
                              {busyKey === `remove-${member.id}` ? 'Removing...' : 'Remove'}
                            </Button>
                          </Stack>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                  {(teamData?.users?.length ?? 0) === 0 && (
                    <TableRow>
                      <TableCell colSpan={4} align="center">No team members yet.</TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </TableContainer>
          )}

          <Divider sx={{ my: 3 }} />

          {successMessage && <Alert severity="success" sx={{ mb: 2 }}>{successMessage}</Alert>}
          {errorMessage && <Alert severity="error" sx={{ mb: 2 }}>{errorMessage}</Alert>}

          <Button
            variant="contained"
            onClick={() => setShowCreateForm((prev) => !prev)}
            sx={{ mb: 2 }}
          >
            {showCreateForm ? 'Hide Create Team Member Form' : 'Create Team Member'}
          </Button>

          <Collapse in={showCreateForm}>
            <Stack spacing={2} component="form" onSubmit={handleSubmit}>
              <Alert severity="info" icon={null}>
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 0.5 }}>Google Sign-In Only</Typography>
                <Typography variant="body2">
                  New team members will receive an email invite and can sign in using their Google account only. No password required.
                </Typography>
              </Alert>

              <TextField
                label="Username"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
                fullWidth
              />

              <TextField
                label="Email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                fullWidth
              />

              <TextField
                label="Phone"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
                fullWidth
              />

              <FormControl fullWidth>
                <InputLabel id="team-role-label">Role</InputLabel>
                <Select
                  labelId="team-role-label"
                  label="Role"
                  value={role}
                  onChange={(e) => setRole(e.target.value as 'editor' | 'viewer')}
                >
                  <MenuItem value="editor" disabled={!canCreateEditor}>Editor{!canCreateEditor ? ' - Full' : ''}</MenuItem>
                  <MenuItem value="viewer" disabled={!canCreateViewer}>Viewer{!canCreateViewer ? ' - Full' : ''}</MenuItem>
                </Select>
              </FormControl>

              {selectedRoleSeatsLeft <= 0 && (
                <Alert severity="warning">
                  No {role} seats left for this account.
                </Alert>
              )}

              <Button type="submit" variant="contained" disabled={loading || selectedRoleSeatsLeft <= 0}>
                {loading ? 'Creating...' : 'Create team member'}
              </Button>
            </Stack>
          </Collapse>
        </CardContent>
      </Card>
    </Box>
  )
}

export default BuildTeamPage
