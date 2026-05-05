import React from 'react'
import { Alert, Button } from '@mui/material'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'

/**
 * Shows a dismissible banner at the top of authenticated pages
 * when the account is in trial, warning the user of days remaining.
 * Disappears when plan is active.
 */
const SubscriptionBanner: React.FC = () => {
  const { user, subscription } = useAuth()
  const navigate = useNavigate()
  const permissionLevel = user?.permissionLevel ?? (user?.role === 'admin' ? 3 : 1)

  // Super admins have no trial/subscription — never show the banner
  if (permissionLevel >= 4) return null

  if (!subscription) return null
  if (subscription.planStatus === 'active') return null

  let severity: 'info' | 'warning' | 'error' = 'info'
  let message = ''

  if (subscription.planStatus === 'trial') {
    const days = subscription.trialDaysLeft
    if (days > 7) {
      severity = 'info'
      message = `Free trial: ${days} days remaining.`
    } else if (days > 0) {
      severity = 'warning'
      message = `Trial ending soon: ${days} day${days === 1 ? '' : 's'} left. Add a payment method to keep access.`
    } else {
      severity = 'error'
      message = 'Your free trial has ended. Please add a payment method.'
    }
  } else if (subscription.planStatus === 'past_due') {
    severity = 'error'
    message = 'Payment failed. Please update your billing information.'
  } else if (subscription.planStatus === 'expired' || subscription.planStatus === 'cancelled') {
    severity = 'error'
    message = 'Your subscription has ended. Please renew to continue.'
  }

  if (!message) return null

  return (
    <Alert
      severity={severity}
      sx={{ borderRadius: 0, py: 0.5 }}
      action={
        subscription.orgRole === 'org_admin' ? (
          <Button color="inherit" size="small" onClick={() => navigate('/billing')}>
            Manage Billing
          </Button>
        ) : undefined
      }
    >
      {message}
    </Alert>
  )
}

export default SubscriptionBanner
