import { Router, Request, Response } from 'express'
import userService from '@services/userService'
import { authenticate } from '@middleware/auth'

const router = Router()

// Get user profile
router.get('/:id', authenticate, async (req: Request, res: Response) => {
  try {
    const user = await userService.getUserById(parseInt(req.params.id))
    if (!user) {
      return res.status(404).json({ success: false, error: 'User not found' })
    }
    // Remove password from response
    const { password, ...userWithoutPassword } = user
    res.json({ success: true, data: userWithoutPassword })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

// Update user profile
router.put('/:id', authenticate, async (req: Request, res: Response) => {
  try {
    const user = await userService.updateUser(parseInt(req.params.id), req.body)
    if (!user) {
      return res.status(404).json({ success: false, error: 'User not found' })
    }
    const { password, ...userWithoutPassword } = user
    res.json({ success: true, data: userWithoutPassword })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

export default router
