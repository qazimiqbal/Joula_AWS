import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.tsx'
import AppThemeProvider from './theme/AppThemeProvider'
import 'leaflet/dist/leaflet.css'

if (window.location.pathname === '/Joula' || window.location.pathname.startsWith('/Joula/')) {
  const nextPath = window.location.pathname.replace(/^\/Joula/, '') || '/'
  window.location.replace(`${nextPath}${window.location.search}${window.location.hash}`)
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <AppThemeProvider>
      <App />
    </AppThemeProvider>
  </React.StrictMode>,
)
