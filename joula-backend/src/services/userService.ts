import pool from '@/config/database'
import { User } from '@/types'
import bcrypt from 'bcryptjs'

export class UserService {
  async getUserById(id: number): Promise<User | null> {
    const connection = await pool.getConnection()
    try {
      const [rows] = (await connection.query('SELECT * FROM users WHERE id = ?', [id])) as [User[], unknown]
      return rows.length > 0 ? rows[0] : null
    } finally {
      connection.release()
    }
  }

  async getUserByEmail(email: string): Promise<User | null> {
    const connection = await pool.getConnection()
    try {
      const [rows] = (await connection.query('SELECT * FROM users WHERE email = ?', [email])) as [User[], unknown]
      return rows.length > 0 ? rows[0] : null
    } finally {
      connection.release()
    }
  }

  async createUser(userData: Partial<User>): Promise<User> {
    const connection = await pool.getConnection()
    try {
      const hashedPassword = await bcrypt.hash(userData.password || '', 10)
      await connection.query(
        'INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)',
        [userData.name, userData.email, hashedPassword, userData.phone, userData.role || 'user']
      )

      const [rows] = (await connection.query('SELECT * FROM users WHERE email = ?', [userData.email])) as [User[], unknown]
      return rows[0]
    } finally {
      connection.release()
    }
  }

  async updateUser(id: number, userData: Partial<User>): Promise<User | null> {
    const connection = await pool.getConnection()
    try {
      const updates: string[] = []
      const values: any[] = []

      if (userData.name) {
        updates.push('name = ?')
        values.push(userData.name)
      }
      if (userData.email) {
        updates.push('email = ?')
        values.push(userData.email)
      }
      if (userData.phone) {
        updates.push('phone = ?')
        values.push(userData.phone)
      }

      values.push(id)

      if (updates.length > 0) {
        await connection.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, values)
      }

      return this.getUserById(id)
    } finally {
      connection.release()
    }
  }

  async verifyPassword(email: string, password: string): Promise<User | null> {
    const user = await this.getUserByEmail(email)
    if (!user) return null

    const isPasswordValid = await bcrypt.compare(password, user.password)
    return isPasswordValid ? user : null
  }
}

export default new UserService()
