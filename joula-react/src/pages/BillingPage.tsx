import React, { useEffect, useState } from 'react'
import {
  Box, Typography, Card, CardContent, Button, Chip,
  Divider, CircularProgress, Alert, LinearProgress
} from '@mui/material'
import CreditCardIcon from '@mui/icons-material/CreditCard'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import WarningIcon from '@mui/icons-material/Warning'
import { useAuth } from '@/context/AuthContext'
import apiService from '@services/api'
import { SubscriptionInfo, PlanStatus } from '@/types'

const statusColor: Record<PlanStatus, 'success' | 'warning' | 'error' | 'info'> = {
  trial: 'info',
  active: 'success',
  past_due: 'error',
  cancelled: 'error',
  expired: 'error',
}

const statusLabel: Record<PlanStatus, string> = {
  trial: 'Free Trial',
  active: 'Active',
  past_due: 'Payment Failed',
  cancelled: 'Cancelled',
  expired: 'Expired',
}

const BillingPage: React.FC = () => {
  const { subscription, setSubscription } = useAuth()
  const [info, setInfo] = useState<SubscriptionInfo | null>(subscription)
  const [loading, setLoading] = useState(!subscription)
  const [actionLoading, setActionLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Detect success redirect from Stripe Checkout
  const params = new URLSearchParams(window.location.search)
  const checkoutSuccess = params.get('success') === '1'
  const checkoutCancelled = params.get('cancelled') === '1'

  useEffect(() => {
    let mounted = true
    const load = async () => {
      setLoading(true)
      const data = await apiService.getSubscription()
      if (mounted) {
        setInfo(data)
        setSubscription(data)
        setLoading(false)
      }
    }
    load()
    return () => { mounted = false }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const handleSubscribe = async () => {
    setActionLoading(true)
    setError(null)
    try {
      const url = await apiService.createCheckoutSession()
      if (url) {
        window.location.href = url
      } else {
        setError('Could not create checkout session. Please try again.')
      }
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Unknown error'
      setError(msg)
    } finally {
      setActionLoading(false)
    }
  }

  const handleManage = async () => {
    setActionLoading(true)
    setError(null)
    try {
      const url = await apiService.createBillingPortalSession()
      if (url) {
        window.location.href = url
      } else {
        setError('Could not open billing portal. Please try again.')
      }
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Unknown error'
      setError(msg)
    } finally {
      setActionLoading(false)
    }
  }

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', mt: 6 }}>
        <CircularProgress />
      </Box>
    )
  }

  const isAdmin = info?.orgRole === 'org_admin'
  const trialProgress = info?.planStatus === 'trial'
    ? Math.max(0, Math.min(100, Math.round(((30 - (info.trialDaysLeft ?? 0)) / 30) * 100)))
    : null

  return (
    <Box sx={{ p: 2, maxWidth: 520, mx: 'auto' }}>
      <Typography variant="h6" gutterBottom>Subscription &amp; Billing</Typography>

      {checkoutSuccess && (
        <Alert severity="success" sx={{ mb: 2 }}>
          <strong>Payment successful!</strong> Your subscription is now active.
        </Alert>
      )}
      {checkoutCancelled && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Checkout was cancelled. You can subscribe any time below.
        </Alert>
      )}
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      {!info ? (
        <Alert severity="warning">No subscription data found. Please contact support.</Alert>
      ) : (
        <Card variant="outlined">
          <CardContent>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
              <Typography variant="subtitle1" fontWeight={600}>{info.orgName ?? 'Your Organization'}</Typography>
              <Chip
                label={statusLabel[info.planStatus]}
                color={statusColor[info.planStatus]}
                size="small"
                icon={info.planStatus === 'active' ? <CheckCircleIcon /> : <WarningIcon />}
              />
            </Box>

            {info.planStatus === 'trial' && (
              <Box sx={{ mb: 2 }}>
                <Typography variant="body2" color="text.secondary" gutterBottom>
                  Trial: {info.trialDaysLeft} day{info.trialDaysLeft === 1 ? '' : 's'} remaining
                  &nbsp;(ends {new Date(info.trialEndsAt).toLocaleDateString()})
                </Typography>
                <LinearProgress
                  variant="determinate"
                  value={trialProgress ?? 0}
                  color={info.trialDaysLeft <= 7 ? 'warning' : 'info'}
                  sx={{ borderRadius: 1 }}
                />
              </Box>
            )}

            <Divider sx={{ my: 1.5 }} />

            <Typography variant="body2" sx={{ mb: 0.5 }}>
              <strong>Plan:</strong> {info.monthlyPriceCents
                ? `$${(info.monthlyPriceCents / 100).toFixed(2)} / month`
                : 'Monthly subscription'}
            </Typography>
            <Typography variant="body2" sx={{ mb: 0.5 }}>
              <strong>Editors:</strong> up to {info.maxEditors}
            </Typography>
            <Typography variant="body2" sx={{ mb: 2 }}>
              <strong>Viewers:</strong> up to {info.maxViewers}
            </Typography>

            {isAdmin && (
              <>
                {(info.planStatus === 'trial' || info.planStatus === 'expired' || info.planStatus === 'cancelled') && (
                  <Button
                    variant="contained"
                    startIcon={<CreditCardIcon />}
                    onClick={handleSubscribe}
                    disabled={actionLoading}
                    fullWidth
                  >
                    {actionLoading ? <CircularProgress size={20} /> : 'Subscribe Now'}
                  </Button>
                )}

                {(info.planStatus === 'active' || info.planStatus === 'past_due') && (
                  <Button
                    variant="outlined"
                    startIcon={<CreditCardIcon />}
                    onClick={handleManage}
                    disabled={actionLoading}
                    fullWidth
                  >
                    {actionLoading ? <CircularProgress size={20} /> : 'Manage Billing'}
                  </Button>
                )}
              </>
            )}

            {!isAdmin && (
              <Alert severity="info" sx={{ mt: 1 }}>
                Only the organization admin can manage billing.
              </Alert>
            )}
          </CardContent>
        </Card>
      )}
    </Box>
  )
}

export default BillingPage
