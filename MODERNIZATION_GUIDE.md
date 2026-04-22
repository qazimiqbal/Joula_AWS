# 🚀 Joula Modernization Guide

Complete guide for modernizing your legacy PHP/jQuery/HTML application to modern React + Node.js.

## Project Overview

This guide covers the migration of your Masjid location-based application from:
- **Old Stack**: PHP + jQuery + HTML + MySQL
- **New Stack**: React + TypeScript + Node.js/Express + MySQL

## Directory Structure

```
mobile/
├── joula-react/              # React Frontend
│   ├── src/
│   ├── public/
│   ├── index.html
│   ├── package.json
│   ├── vite.config.ts
│   └── README.md
│
├── joula-backend/            # Node.js/Express Backend
│   ├── src/
│   ├── package.json
│   ├── tsconfig.json
│   └── README.md
│
└── [old PHP files]           # Keep for reference during migration
```

## Phase 1: Frontend Setup ✅

### 1.1 Install React Frontend

```bash
cd joula-react
npm install
```

### 1.2 Create .env file

```bash
cp .env.example .env
```

Update `.env` with your local API URL:
```
VITE_API_URL=http://localhost:5000/api
```

### 1.3 Start Development Server

```bash
npm run dev
```

Frontend available at: `http://localhost:3000`

## Phase 2: Backend Setup

### 2.1 Install Backend Dependencies

```bash
cd joula-backend
npm install
```

### 2.2 Configure Environment

```bash
cp .env.example .env
```

Update database credentials:
```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_mysql_password
DB_NAME=joula
JWT_SECRET=your-secure-random-string
```

### 2.3 Database Migration

Copy the SQL from [Database Setup](#database-setup) section and run in MySQL:

```bash
mysql -u root -p < setup.sql
```

Or use MySQL Workbench/phpMyAdmin

### 2.4 Start Backend Server

```bash
npm run dev
```

Backend available at: `http://localhost:5000/api`

## Phase 3: Data Migration from PHP to MySQL

### Option A: Automated Migration Script

Create a migration script from your existing PHP application:

```php
<?php
// migrate_to_new_db.php - Run this once to migrate existing data

$oldDb = new mysqli('localhost', 'user', 'pass', 'old_joula');
$newDb = new mysqli('localhost', 'root', 'password', 'joula');

// Migrate users
$result = $oldDb->query("SELECT * FROM users");
while ($row = $result->fetch_assoc()) {
    $stmt = $newDb->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $row['name'], $row['email'], $row['password'], $row['phone'], $row['role']);
    $stmt->execute();
}

// Migrate masjids
$result = $oldDb->query("SELECT * FROM masjids");
while ($row = $result->fetch_assoc()) {
    $stmt = $newDb->prepare("INSERT INTO masjids (name, address, latitude, longitude, phone, website) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddss", $row['name'], $row['address'], $row['lat'], $row['lon'], $row['phone'], $row['website']);
    $stmt->execute();
}

echo "Migration complete!";
?>
```

### Option B: Manual Migration

1. Export data from existing PHP app
2. Format and import to new MySQL database
3. Verify data integrity

## Phase 4: API Integration

### 4.1 Update Frontend API URL

Edit [joula-react/.env](joula-react/.env.example):

```env
VITE_API_URL=http://localhost:5000/api
```

### 4.2 Test API Calls

The frontend automatically connects to the backend through:
- `src/services/api.ts` - All API calls
- `src/context/AuthContext.tsx` - Authentication

## Phase 5: Feature Implementation

### Completed Features ✅
- User Login/Registration
- Dashboard with Masjid list
- User Profile management
- Map view placeholder
- Location search functionality

### Features to Implement 🔄
- Interactive map with Leaflet/Google Maps
- Prayer times display
- Advanced search filters
- User ratings & reviews
- Notifications
- Admin panel for Masjid management
- Mobile app (React Native)

## Phase 6: Deployment to GoDaddy

### 6.1 Frontend Deployment

Build React app:
```bash
cd joula-react
npm run build
```

Upload `dist/` folder contents to `/Joula/frontend/` via SFTP

### 6.2 Backend Deployment

Build backend:
```bash
cd joula-backend
npm run build
```

Upload to hosting:
1. Upload `dist/` and `node_modules/` via SFTP
2. Create `.env` file on server
3. Run `npm start` or use PM2

### 6.3 Database Backup

Before deployment:
```bash
mysqldump -u root -p joula > joula_backup_$(date +%Y%m%d).sql
```

## Common PHP to React Mappings

| PHP Feature | React Equivalent |
|-------------|------------------|
| $_SESSION | React Context API |
| isset($_GET) | useSearchParams() |
| mysql query | axios/Fetch API |
| echo HTML | JSX return statements |
| Redirect | useNavigate() |
| Include file | Import component |
| POST form | Controlled form + API call |

## Troubleshooting

### Frontend won't connect to backend
- Check if backend is running on port 5000
- Verify `.env` has correct API_URL
- Check browser console for CORS errors

### Database connection failed
- Verify MySQL is running
- Check credentials in `.env`
- Ensure database `joula` exists

### Build errors
- Run `npm install` to ensure all dependencies
- Clear node_modules: `rm -rf node_modules && npm install`
- Check Node.js version: `node --version` (need 16+)

## Testing Checklist

- [ ] Backend server starts without errors
- [ ] Frontend loads at localhost:3000
- [ ] Login works with credentials
- [ ] Dashboard displays masjids
- [ ] Search functionality works
- [ ] Map loads (even as placeholder)
- [ ] Profile update works
- [ ] Logout redirects to login

## Performance Tips

1. **Frontend Optimization**
   - Enable code splitting: `React.lazy()`
   - Use React.memo for expensive components
   - Lazy load images
   - Minify CSS/JavaScript

2. **Backend Optimization**
   - Add database indexes
   - Use pagination (already implemented)
   - Cache frequently accessed data
   - Monitor query performance

3. **Database Optimization**
   - Index location columns for geo queries
   - Archive old data
   - Regular backups

## Security Checklist

- [ ] Change JWT_SECRET to strong random string
- [ ] Use HTTPS in production
- [ ] Validate all user inputs
- [ ] Hash passwords (bcryptjs ✓)
- [ ] Set CORS correctly for production domain
- [ ] Implement rate limiting
- [ ] Use environment variables for secrets
- [ ] Regular security audits

## Next Steps

1. **Install dependencies** in both directories
2. **Set up database** using provided SQL
3. **Configure environment** variables
4. **Start both servers** (frontend + backend)
5. **Test basic flows** (login, search, profile)
6. **Implement remaining features**
7. **Deploy to production**

## Support & Resources

- **React Docs**: https://react.dev
- **Express Docs**: https://expressjs.com
- **TypeScript Docs**: https://www.typescriptlang.org
- **Vite Docs**: https://vitejs.dev
- **Material-UI Docs**: https://mui.com

## Contact

For questions about this modernization: support@myjoula.com

---

**Last Updated**: April 2026  
**Version**: 1.0.0
