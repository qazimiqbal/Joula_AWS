import React from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'

/**
 * Wraps protected routes. Redirects to /billing when the trial
 * has expired or the subscription is past_due / cancelled.
 * Editors and viewers are blocked too — only org admin can fix billing.
 */
const SubscriptionGuard: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { subscription } = useAuth()

  if (!subscription) {
    // No subscription data yet (legacy user or org not set up) — let through
    return <>{children}</>
  }

  const blocked = subscription.planStatus === 'expired'
    || subscription.planStatus === 'cancelled'
    || subscription.planStatus === 'past_due'

  if (blocked) {
    return <Navigate to="/billing" replace />
  }

  return <>{children}</>
}

export default SubscriptionGuard
