import pool from '@/config/database'
import { Masjid } from '@/types'

export class MasjidService {
  async getMasjids(
    limit: number = 10,
    offset: number = 0,
    search?: string
  ): Promise<Masjid[]> {
    const connection = await pool.getConnection()
    try {
      let query = 'SELECT * FROM masjids'
      const params: any[] = []

      if (search) {
        query += ' WHERE name LIKE ? OR address LIKE ?'
        params.push(`%${search}%`, `%${search}%`)
      }

      query += ' LIMIT ? OFFSET ?'
      params.push(limit, offset)

      const [rows] = await connection.query(query, params)
      return rows as Masjid[]
    } finally {
      connection.release()
    }
  }

  async getMasjidById(id: number): Promise<Masjid | null> {
    const connection = await pool.getConnection()
    try {
      const [rows] = (await connection.query('SELECT * FROM masjids WHERE id = ?', [id])) as [Masjid[], unknown]
      return rows.length > 0 ? rows[0] : null
    } finally {
      connection.release()
    }
  }

  async searchByLocation(
    latitude: number,
    longitude: number,
    radiusKm: number = 10
  ): Promise<Masjid[]> {
    const connection = await pool.getConnection()
    try {
      const query = `
        SELECT *, 
        (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
        cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
        sin(radians(latitude)))) AS distance
        FROM masjids
        HAVING distance < ?
        ORDER BY distance
      `

      const [rows] = await connection.query(query, [latitude, longitude, latitude, radiusKm])
      return rows as Masjid[]
    } finally {
      connection.release()
    }
  }

  async createMasjid(masjidData: Partial<Masjid>): Promise<Masjid> {
    const connection = await pool.getConnection()
    try {
      await connection.query(
        'INSERT INTO masjids (name, address, latitude, longitude, phone, website) VALUES (?, ?, ?, ?, ?, ?)',
        [
          masjidData.name,
          masjidData.address,
          masjidData.latitude,
          masjidData.longitude,
          masjidData.phone,
          masjidData.website,
        ]
      )

      const [rows] = (await connection.query('SELECT * FROM masjids WHERE name = ?', [
        masjidData.name,
      ])) as [Masjid[], unknown]
      return rows[0]
    } finally {
      connection.release()
    }
  }

  async updateMasjid(id: number, masjidData: Partial<Masjid>): Promise<Masjid | null> {
    const connection = await pool.getConnection()
    try {
      const updates: string[] = []
      const values: any[] = []

      if (masjidData.name) {
        updates.push('name = ?')
        values.push(masjidData.name)
      }
      if (masjidData.address) {
        updates.push('address = ?')
        values.push(masjidData.address)
      }
      if (masjidData.phone) {
        updates.push('phone = ?')
        values.push(masjidData.phone)
      }
      if (masjidData.website) {
        updates.push('website = ?')
        values.push(masjidData.website)
      }

      values.push(id)

      if (updates.length > 0) {
        await connection.query(`UPDATE masjids SET ${updates.join(', ')} WHERE id = ?`, values)
      }

      return this.getMasjidById(id)
    } finally {
      connection.release()
    }
  }

  calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const R = 6371 // Earth's radius in km
    const dLat = ((lat2 - lat1) * Math.PI) / 180
    const dLon = ((lon2 - lon1) * Math.PI) / 180
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos((lat1 * Math.PI) / 180) *
        Math.cos((lat2 * Math.PI) / 180) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2)
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    return R * c
  }
}

export default new MasjidService()
