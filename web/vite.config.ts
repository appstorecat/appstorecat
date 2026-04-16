import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig(({ command }) => {
  const port = Number(process.env.PORT || 5173)
  const backendUrl = process.env.BACKEND_URL || 'http://localhost:7460'

  return {
    plugins: [react(), tailwindcss()],
    define: {
      __APP_VERSION__: JSON.stringify(process.env.npm_package_version || '0.0.0'),
    },
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    server: {
      port,
      host: '0.0.0.0',
      proxy: command === 'serve'
        ? {
            '/api': { target: backendUrl, changeOrigin: true },
            '/storage': { target: backendUrl, changeOrigin: true },
          }
        : undefined,
    },
  }
})
