# 🕌 Joula - Modern React Application

Modern React + TypeScript web application for Masjid location discovery and management.

## Features

- ✅ User authentication and profiles
- ✅ Masjid search and discovery
- ✅ Location-based services
- ✅ Distance calculations
- ✅ Prayer times
- ✅ Responsive Material-UI design
- ✅ TypeScript for type safety

## Tech Stack

- **Frontend**: React 18 + TypeScript + Vite
- **UI Framework**: Material-UI (MUI)
- **State Management**: React Context API
- **HTTP Client**: Axios
- **Router**: React Router v6
- **Maps**: Leaflet (optional)
- **Backend API**: Node.js + Express (separate)
- **Database**: MySQL

## Project Structure

```
joula-react/
├── src/
│   ├── components/          # Reusable React components
│   ├── pages/               # Page components (Login, Dashboard, etc.)
│   ├── services/            # API service and business logic
│   ├── context/             # React Context (Auth, etc.)
│   ├── hooks/               # Custom React hooks
│   ├── types/               # TypeScript type definitions
│   ├── App.tsx              # Main app component
│   ├── main.tsx             # React entry point
│   └── App.css              # Global styles
├── public/                  # Static assets
├── index.html               # HTML entry point
├── package.json             # Dependencies
├── tsconfig.json            # TypeScript config
├── vite.config.ts           # Vite build config
└── .gitignore               # Git ignore rules
```

## Setup Instructions

### 1. Install Dependencies

```bash
cd joula-react
npm install
```

### 2. Environment Configuration

Create a `.env` file in the project root:

```
VITE_API_URL=http://localhost:5000/api
VITE_MAP_API_KEY=your_map_api_key_here
```

### 3. Development Server

```bash
npm run dev
```

The app will run on `http://localhost:3000`

### 4. Build for Production

```bash
npm run build
```

Output will be in the `dist/` folder

## Available Scripts

- `npm run dev` - Start development server with hot reload
- `npm run build` - Build for production
- `npm run preview` - Preview production build
- `npm run lint` - Run ESLint checks

## Key Pages

- **/login** - User authentication
- **/dashboard** - Main dashboard with masjid listings
- **/map** - Interactive map with search functionality
- **/profile** - User profile management

## API Integration

The app communicates with a Node.js/Express backend via REST API. Key endpoints:

- `POST /api/auth/login` - User login
- `GET /api/masjids` - Get all masjids
- `GET /api/masjids/:id` - Get masjid details
- `GET /api/masjids/search/location` - Search by location
- `GET /api/users/:id` - Get user info
- `PUT /api/users/:id` - Update user profile

See `src/services/api.ts` for full API documentation.

## Backend Setup

A separate Node.js/Express backend is required. See the backend README for setup instructions.

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## License

MIT

## Support

For issues or questions, please contact: support@myjoula.com
