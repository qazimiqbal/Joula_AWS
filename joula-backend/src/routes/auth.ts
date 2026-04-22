import { Router, Request, Response } from 'express'
import jwt from 'jsonwebtoken'
import userService from '@services/userService'

const router = Router()

// Login
router.post('/login', async (req: Request, res: Response) => {
  try {
    const { email, password } = req.body

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        error: 'Email and password are required',
      })
    }

    const user = await userService.verifyPassword(email, password)
    if (!user) {
      return res.status(401).json({
        success: false,
        error: 'Invalid credentials',
      })
    }

    const token = jwt.sign(
      { id: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '7d' }
    )

    const { password: _, ...userWithoutPassword } = user
    res.json({
      success: true,
      data: {
        token,
        user: userWithoutPassword,
      },
    })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

// Register
router.post('/register', async (req: Request, res: Response) => {
  try {
    const { name, email, password, phone } = req.body

    if (!name || !email || !password) {
      return res.status(400).json({
        success: false,
        error: 'Name, email and password are required',
      })
    }

    const existingUser = await userService.getUserByEmail(email)
    if (existingUser) {
      return res.status(400).json({
        success: false,
        error: 'Email already registered',
      })
    }

    const user = await userService.createUser({ name, email, password, phone })
    const token = jwt.sign(
      { id: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '7d' }
    )

    const { password: _, ...userWithoutPassword } = user
    res.status(201).json({
      success: true,
      data: {
        token,
        user: userWithoutPassword,
      },
    })
  } catch (error: any) {
    res.status(500).json({ success: false, error: error.message })
  }
})

export default router
