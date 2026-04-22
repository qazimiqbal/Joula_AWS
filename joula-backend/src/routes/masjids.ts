import { Router, Request, Response } from 'express'
import masjidService from '@services/masjidService'
import { authenticate } from '@middleware/auth'

const router = Router()

// Get all masjids
router.get('/', async (req: Request, res: Response) => {
  try {
    const limit = parseInt(req.query.limit as string) || 10
    const page = parseInt(req.query.page as string) || 1
    const search = req.query.search as string
    const offset = (page - 1) * limit

    const masjids = await masjidService.getMasjids(limit, offset, search)
    res.json({ success: true, data: masjids })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

// Get masjid by ID
router.get('/:id', async (req: Request, res: Response) => {
  try {
    const masjid = await masjidService.getMasjidById(parseInt(req.params.id))
    if (!masjid) {
      return res.status(404).json({ success: false, error: 'Masjid not found' })
    }
    res.json({ success: true, data: masjid })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

// Search by location
router.get('/search/location', async (req: Request, res: Response) => {
  try {
    const latitude = parseFloat(req.query.latitude as string)
    const longitude = parseFloat(req.query.longitude as string)
    const radius = parseFloat(req.query.radius as string) || 10

    if (isNaN(latitude) || isNaN(longitude)) {
      return res.status(400).json({
        success: false,
        error: 'Valid latitude and longitude are required',
      })
    }

    const masjids = await masjidService.searchByLocation(latitude, longitude, radius)
    res.json({ success: true, data: masjids })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

// Create masjid (admin only)
router.post('/', authenticate, async (req: Request, res: Response) => {
  try {
    const masjid = await masjidService.createMasjid(req.body)
    res.status(201).json({ success: true, data: masjid })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

// Update masjid (admin only)
router.put('/:id', authenticate, async (req: Request, res: Response) => {
  try {
    const masjid = await masjidService.updateMasjid(parseInt(req.params.id), req.body)
    if (!masjid) {
      return res.status(404).json({ success: false, error: 'Masjid not found' })
    }
    res.json({ success: true, data: masjid })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

export default router
