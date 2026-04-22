# 🕌 Joula - Modern Masjid Finder Application

Complete modernization of your legacy PHP/jQuery application to a modern, scalable React + Node.js stack.

## 📦 What's Included

This is a **complete web application** with separate frontend and backend:

### Frontend: React Application
- Modern React 18 with TypeScript
- Material-UI design system
- Fast development with Vite
- Responsive & mobile-friendly
- Location services integration

**Location**: `joula-react/`

### Backend: Node.js REST API
- Express.js with TypeScript
- JWT authentication
- MySQL database integration
- RESTful API endpoints
- Geolocation search

**Location**: `joula-backend/`

## 🚀 Quick Start (5 minutes)

### For Windows Users
```bash
# Run the setup script
setup.bat
```

### For macOS/Linux Users
```bash
# Run the setup script
bash setup.sh
```

### Manual Setup

**Terminal 1 - Backend:**
```bash
cd joula-backend
npm install
cp .env.example .env
# Edit .env with your MySQL credentials
npm run dev
# Server running at http://localhost:5000
```

**Terminal 2 - Frontend:**
```bash
cd joula-react
npm install
npm run dev
# App running at http://localhost:3000
```

Open browser: **http://localhost:3000**

## 📚 Documentation

| Document | Purpose |
|----------|---------|
| [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) | High-level project structure & status |
| [MODERNIZATION_GUIDE.md](MODERNIZATION_GUIDE.md) | Complete migration guide from PHP |
| [joula-react/README.md](joula-react/README.md) | Frontend setup & development |
| [joula-backend/README.md](joula-backend/README.md) | Backend API documentation |

## 🛠️ Tech Stack

| Component | Technology |
|-----------|-----------|
| **Frontend** | React 18 + TypeScript + Vite |
| **UI Library** | Material-UI (MUI) |
| **Backend** | Node.js + Express.js |
| **Language** | TypeScript |
| **Database** | MySQL 8.0+ |
| **HTTP Client** | Axios |
| **Auth** | JWT (JSON Web Tokens) |
| **Routing** | React Router v6 |

## ✨ Features

### User Features
- ✅ User registration & login
- ✅ Profile management
- ✅ Search masjids
- ✅ Find nearby locations
- ✅ View masjid details
- ✅ Distance calculations

### Admin Features
- ✅ Manage masjids
- ✅ User management
- ✅ Prayer time updates
- ✅ Analytics dashboard (coming soon)

## 📋 Pre-requisites

- **Node.js** 16.x or higher
- **npm** or **yarn**
- **MySQL** 8.0 or higher
- **Git** (optional)

### Check your versions:
```bash
node --version      # Should be v16+
npm --version       # Should be v8+
mysql --version     # Should be v8+
```

## 🔧 Project Structure

```
mobile/
├── joula-react/              # React Frontend Application
│   ├── src/
│   │   ├── components/       # Reusable React components
│   │   ├── pages/            # Page components
│   │   ├── services/         # API integration
│   │   ├── context/          # React Context API
│   │   ├── types/            # TypeScript interfaces
│   │   ├── hooks/            # Custom React hooks
│   │   ├── App.tsx           # Main application component
│   │   └── main.tsx          # React entry point
│   ├── public/               # Static assets
│   ├── index.html            # HTML template
│   ├── package.json          # Dependencies & scripts
│   ├── vite.config.ts        # Vite configuration
│   ├── tsconfig.json         # TypeScript configuration
│   └── README.md             # Frontend documentation
│
├── joula-backend/            # Node.js/Express Backend API
│   ├── src/
│   │   ├── config/           # Database configuration
│   │   ├── controllers/      # Route handlers
│   │   ├── middleware/       # Auth & error handling
│   │   ├── routes/           # API routes
│   │   ├── services/         # Business logic
│   │   ├── types/            # TypeScript interfaces
│   │   ├── utils/            # Utility functions
│   │   └── index.ts          # Server entry point
│   ├── dist/                 # Compiled JavaScript (after build)
│   ├── package.json          # Dependencies & scripts
│   ├── tsconfig.json         # TypeScript configuration
│   └── README.md             # Backend documentation
│
├── PROJECT_OVERVIEW.md       # Project status & structure
├── MODERNIZATION_GUIDE.md    # Migration guide from PHP
├── setup.sh                  # Setup script for macOS/Linux
├── setup.bat                 # Setup script for Windows
└── README.md                 # This file
```

## 🌐 API Endpoints

Base URL: `http://localhost:5000/api`

### Authentication
- `POST /auth/login` - User login
- `POST /auth/register` - User registration

### Users
- `GET /users/:id` - Get user profile
- `PUT /users/:id` - Update user profile

### Masjids
- `GET /masjids` - List all masjids
- `GET /masjids/:id` - Get masjid details
- `GET /masjids/search/location` - Search nearby
- `POST /masjids` - Create masjid (admin)
- `PUT /masjids/:id` - Update masjid (admin)

### Distance
- `POST /distance/calculate` - Calculate distance

See [Backend README](joula-backend/README.md) for full documentation.

## 🗄️ Database Setup

### Create Database
```sql
CREATE DATABASE joula;
```

### Create Tables

See [joula-backend/README.md](joula-backend/README.md#database-setup) for complete SQL schema.

## 🔐 Environment Configuration

### Frontend (.env)
```env
VITE_API_URL=http://localhost:5000/api
```

### Backend (.env)
```env
NODE_ENV=development
PORT=5000
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=joula
JWT_SECRET=your-secret-key-here
FRONTEND_URL=http://localhost:3000
```

Example files provided: `.env.example` in each directory

## 📦 Build & Deploy

### Build Frontend
```bash
cd joula-react
npm run build
# Output in: dist/
```

### Build Backend
```bash
cd joula-backend
npm run build
# Output in: dist/
```

### Upload to GoDaddy

Your SFTP config is ready in `.vscode/sftp.json`:
- **Host**: ftp.myjoula.com
- **Remote Path**: /Joula
- **Upload**: `dist/` folders to respective directories

## 🧪 Testing

### Frontend
```bash
cd joula-react
npm run lint     # Check code quality
npm run build    # Build & test
```

### Backend
```bash
cd joula-backend
npm run lint     # Check code quality
npm run build    # Build & test
```

## 🚨 Troubleshooting

### Issue: "Cannot find module"
```bash
# Solution: Reinstall dependencies
rm -rf node_modules package-lock.json
npm install
```

### Issue: "Port already in use"
```bash
# Kill process on port 3000 or 5000
# macOS/Linux:
lsof -i :3000 | grep LISTEN | awk '{print $2}' | xargs kill -9

# Windows:
netstat -ano | findstr :3000
taskkill /PID <PID> /F
```

### Issue: "MySQL connection error"
- Check MySQL is running
- Verify credentials in `.env`
- Ensure database exists: `CREATE DATABASE joula;`

### Issue: "CORS error"
- Make sure backend is running at correct URL
- Check `VITE_API_URL` in frontend `.env`
- Verify CORS is enabled in backend

## 📖 Learning Resources

- **React**: https://react.dev
- **TypeScript**: https://www.typescriptlang.org/docs/
- **Express**: https://expressjs.com/
- **Material-UI**: https://mui.com/
- **Vite**: https://vitejs.dev/
- **MySQL**: https://dev.mysql.com/doc/

## 🎯 Development Workflow

1. **Start Servers**
   - Backend: `npm run dev` in `joula-backend/`
   - Frontend: `npm run dev` in `joula-react/`

2. **Make Changes**
   - Both apps support hot-reload
   - Changes automatically refresh in browser

3. **Test Features**
   - Login at http://localhost:3000
   - Test API endpoints
   - Check console for errors

4. **Build & Deploy**
   - Run `npm run build` in each directory
   - Upload `dist/` folders to server

## 🔄 Migrating from Old PHP App

Complete migration guide in [MODERNIZATION_GUIDE.md](MODERNIZATION_GUIDE.md)

Key steps:
1. Export data from old PHP database
2. Import into new MySQL database
3. Test all features
4. Deploy to production
5. Monitor for issues

## ✅ Feature Checklist

- [x] User authentication
- [x] Masjid CRUD operations
- [x] Location-based search
- [x] Distance calculations
- [ ] Interactive map (Leaflet/Google Maps)
- [ ] Prayer times display
- [ ] Admin dashboard
- [ ] User reviews & ratings
- [ ] Push notifications
- [ ] Mobile app (React Native)

## 🤝 Contributing

Guidelines for contributing to this project:

1. Create feature branch: `git checkout -b feature/your-feature`
2. Make changes and test
3. Commit: `git commit -am 'Add feature'`
4. Push: `git push origin feature/your-feature`
5. Create Pull Request

## 📞 Support

- **Issues**: Check troubleshooting guide
- **Questions**: Contact support@myjoula.com
- **Docs**: See README files in each directory

## 📄 License

MIT License - See LICENSE file for details

## 🎉 Next Steps

1. ✅ **Setup complete** - You're here!
2. 🔧 **Configure database** - Update `.env` files
3. 🚀 **Start development** - Run setup script
4. 🧪 **Test features** - Use provided test flows
5. 📦 **Deploy** - Build and upload to server

---

**Last Updated**: April 2026  
**Version**: 1.0.0  
**Status**: ✅ Ready for Development

Happy coding! 🚀
