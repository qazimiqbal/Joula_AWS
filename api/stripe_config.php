<?php
// ============================================================
// STRIPE CONFIGURATION
// ============================================================
// Replace the placeholder values below with your real Stripe
// keys from https://dashboard.stripe.com/apikeys
//
// IMPORTANT:
//   - Do NOT commit this file with real keys to version control.
//   - On AWS: use environment variables or Secrets Manager and
//     read them with: getenv('STRIPE_SECRET_KEY')
// ============================================================

define('STRIPE_SECRET_KEY',      'sk_test_REPLACE_WITH_YOUR_SECRET_KEY');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_REPLACE_WITH_YOUR_PUBLISHABLE_KEY');
define('STRIPE_WEBHOOK_SECRET',  'whsec_REPLACE_WITH_YOUR_WEBHOOK_SECRET');

// The Stripe Price ID for your monthly subscription product.
// Create a Product + Price in the Stripe Dashboard, then paste
// the Price ID (starts with "price_") here.
define('STRIPE_PRICE_ID', 'price_REPLACE_WITH_YOUR_PRICE_ID');

// Where Stripe redirects after Checkout
define('STRIPE_SUCCESS_URL', 'https://myjoula.com/Joula/billing?success=1');
define('STRIPE_CANCEL_URL',  'https://myjoula.com/Joula/billing?cancelled=1');
