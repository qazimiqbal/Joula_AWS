import fs from 'node:fs/promises'
import path from 'node:path'

const distDir = path.resolve('dist')
const indexPath = path.join(distDir, 'index.html')
const routes = [
  'login',
  'dashboard',
  'map',
  'area-selection',
  'profile',
  'account',
  'comments',
  'pending-users',
  'addresses/new',
  'masjids/new',
  'address-import',
  'missing-coordinates',
  'geocode-review',
  'billing',
  'org-users',
  'build-team',
  'create-free-user',
  'pending-masjids',
  'pending-addresses',
  'view-users',
  'admin/all-addresses',
]

async function ensureRouteFallbacks() {
  const indexHtml = await fs.readFile(indexPath, 'utf8')

  await Promise.all(
    routes.map(async (route) => {
      const routeDir = path.join(distDir, route)
      await fs.mkdir(routeDir, { recursive: true })
      await fs.writeFile(path.join(routeDir, 'index.html'), indexHtml, 'utf8')
    })
  )
}

ensureRouteFallbacks().catch((error) => {
  console.error('Failed to create static route fallbacks:', error)
  process.exit(1)
})
