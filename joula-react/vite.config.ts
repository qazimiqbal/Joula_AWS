import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiProxyTarget = env.VITE_API_PROXY_TARGET || 'https://myjoula.com/Joula'
  const isLocalPhpApi = /^https?:\/\/(localhost|127\.0\.0\.1):8000/.test(apiProxyTarget)

  return {
  base: '/Joula/',
  plugins: [react()],
  server: {
    port: 3000,
    proxy: {
      // Forward /api/* to the live server (new PHP endpoints)
      '/api': {
        target: apiProxyTarget,
        changeOrigin: true,
        secure: !isLocalPhpApi,
        rewrite: isLocalPhpApi ? (requestPath) => requestPath.replace(/^\/api/, '') : undefined,
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        },
        configure: (proxy) => {
          proxy.on('error', (err, _req, res) => {
            console.warn('[proxy /api] error:', err.message);
            if (res && !res.headersSent) {
              (res as any).writeHead(502, { 'Content-Type': 'application/json' });
              (res as any).end(JSON.stringify({ error: 'Proxy error', detail: err.message }));
            }
          });
        },
      },
      // US Census geocoder proxy for local development fallback
      '/census': {
        target: 'https://geocoding.geo.census.gov',
        changeOrigin: true,
        secure: true,
        rewrite: (path) => path.replace(/^\/census/, ''),
      },
      // Nominatim fallback proxy for local development
      '/nominatim': {
        target: 'https://nominatim.openstreetmap.org',
        changeOrigin: true,
        secure: true,
        rewrite: (path) => path.replace(/^\/nominatim/, ''),
        headers: {
          'User-Agent': 'MyJoula/1.0',
        },
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@components': path.resolve(__dirname, './src/components'),
      '@pages': path.resolve(__dirname, './src/pages'),
      '@services': path.resolve(__dirname, './src/services'),
      '@types': path.resolve(__dirname, './src/types'),
    }
  }
}})
