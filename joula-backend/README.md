# 🕌 Joula Backend API

Node.js/Express REST API backend for Joula Masjid Finder application.

## Features

- ✅ User authentication with JWT
- ✅ Masjid management and search
- ✅ Location-based queries
- ✅ Distance calculations
- ✅ Prayer times management
- ✅ MySQL database integration
- ✅ TypeScript for type safety

## Tech Stack

- **Runtime**: Node.js
- **Framework**: Express.js
- **Language**: TypeScript
- **Database**: MySQL 8.0+
- **Authentication**: JWT (JSON Web Tokens)
- **Security**: bcryptjs for password hashing
- **Validation**: Built-in validation

## Installation

### 1. Prerequisites

- Node.js 16+ installed
- MySQL 8.0+ running
- npm or yarn package manager

### 2. Clone and Install

```bash
cd joula-backend
npm install
```

### 3. Environment Configuration

Create a `.env` file in the project root:

```env
NODE_ENV=development
PORT=5000

# Database
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=joula

# JWT
JWT_SECRET=your-super-secret-jwt-key-change-this

# Frontend URL
FRONTEND_URL=http://localhost:3000
```

### 4. Database Setup

Create the database and tables:

```sql
CREATE DATABASE joula;

USE joula;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  role ENUM('user', 'admin') DEFAULT 'user',
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE masjids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address TEXT NOT NULL,
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  phone VARCHAR(20),
  website VARCHAR(255),
  members INT,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_location (latitude, longitude)
);

CREATE TABLE prayer_times (
  id INT AUTO_INCREMENT PRIMARY KEY,
  masjidId INT NOT NULL,
  date DATE NOT NULL,
  fajr TIME,
  dhuhr TIME,
  asr TIME,
  maghrib TIME,
  isha TIME,
  FOREIGN KEY (masjidId) REFERENCES masjids(id) ON DELETE CASCADE,
  UNIQUE KEY unique_date (masjidId, date)
);
```

### 5. Run Development Server

```bash
npm run dev
```

The API will be available at `http://localhost:5000/api`

## Available Scripts

- `npm run dev` - Start development server with auto-reload
- `npm run build` - Build TypeScript to JavaScript
- `npm start` - Run production build
- `npm run lint` - Run ESLint checks

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration

### Users
- `GET /api/users/:id` - Get user profile (requires auth)
- `PUT /api/users/:id` - Update user profile (requires auth)

### Masjids
- `GET /api/masjids` - Get all masjids with pagination
- `GET /api/masjids/:id` - Get masjid details
- `GET /api/masjids/search/location?latitude=X&longitude=Y&radius=10` - Search nearby masjids
- `POST /api/masjids` - Create masjid (requires auth)
- `PUT /api/masjids/:id` - Update masjid (requires auth)

### Distance
- `POST /api/distance/calculate` - Calculate distance between coordinates

## Project Structure

```
joula-backend/
├── src/
│   ├── config/          # Database and app config
│   ├── controllers/     # Route controllers
│   ├── middleware/      # Auth and error handling
│   ├── models/          # Data models
│   ├── routes/          # API routes
│   ├── services/        # Business logic
│   ├── types/           # TypeScript types
│   ├── utils/           # Utility functions
│   └── index.ts         # App entry point
├── dist/                # Compiled JavaScript (after build)
├── package.json
├── tsconfig.json
└── .env.example
```

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Include the token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

## Error Handling

All API responses follow this format:

```json
{
  "success": boolean,
  "data": {...},
  "message": "optional message",
  "error": "optional error message"
}
```

## Security

- Passwords are hashed using bcryptjs
- JWT tokens expire after 7 days
- CORS enabled for frontend integration
- Input validation on all endpoints

## Deployment

To deploy to production:

1. Build the project: `npm run build`
2. Set production environment variables
3. Use a process manager (PM2, systemd, etc.)
4. Set up a reverse proxy (nginx)

## Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check DB credentials in .env file
- Ensure database exists

### Port Already in Use
Change the PORT in .env file or kill the process using the port

### JWT Errors
- Ensure JWT_SECRET is set in .env
- Check token expiration
- Verify Authorization header format

## Support

For issues or questions: support@myjoula.com

## License

MIT
