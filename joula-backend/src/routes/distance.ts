import { Router, Request, Response } from 'express'
import masjidService from '@services/masjidService'

const router = Router()

// Calculate distance between two points
router.post('/calculate', (req: Request, res: Response) => {
  try {
    const { lat1, lon1, lat2, lon2 } = req.body

    if (lat1 === undefined || lon1 === undefined || lat2 === undefined || lon2 === undefined) {
      return res.status(400).json({
        success: false,
        error: 'All coordinates are required',
      })
    }

    const distance = masjidService.calculateDistance(lat1, lon1, lat2, lon2)
    res.json({ success: true, data: { distance } })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

export default router
