import express from 'express'
import cors from 'cors'
import dotenv from 'dotenv'
import authRoutes from '@routes/auth'
import userRoutes from '@routes/users'
import masjidRoutes from '@routes/masjids'
import distanceRoutes from '@routes/distance'
import { errorHandler, notFound } from '@middleware/errorHandler'

dotenv.config()

const app = express()
const PORT = process.env.PORT || 5000
const FRONTEND_URLS = (process.env.FRONTEND_URL || 'http://localhost:3000')
  .split(',')
  .map((url) => url.trim())
  .filter(Boolean)

// Middleware
app.use(
  cors({
    origin: FRONTEND_URLS,
    credentials: true,
  })
)
app.use(express.json())

// Routes
app.use('/api/auth', authRoutes)
app.use('/api/users', userRoutes)
app.use('/api/masjids', masjidRoutes)
app.use('/api/distance', distanceRoutes)

// Health check
app.get('/api/health', (req, res) => {
  res.json({ success: true, message: 'Server is running' })
})

// Error handling
app.use(notFound)
app.use(errorHandler)

// Start server
app.listen(PORT, () => {
  console.log(`✅ Server running on http://localhost:${PORT}`)
  console.log(`📝 API Documentation available at /api`)
})

export default app
