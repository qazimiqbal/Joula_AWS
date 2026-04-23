import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  base: '/Joula/',
  plugins: [react()],
  server: {
    port: 3000,
    proxy: {
      // Forward /api/* to the live server (new PHP endpoints)
      '/api': {
        target: 'https://myjoula.com/mobile',
        changeOrigin: true,
        secure: true,
      },
      // Forward all /mobile/* requests to the live GoDaddy server
      // This avoids CORS issues when developing locally
      '/mobile': {
        target: 'https://myjoula.com',
        changeOrigin: true,
        secure: true,
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
})
