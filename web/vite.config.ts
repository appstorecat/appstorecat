import { defineConfig, type Plugin } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

function runtimeConfigPlugin(): Plugin {
  return {
    name: 'appstorecat-runtime-config',
    configureServer(server) {
      server.middlewares.use('/config.js', (_req, res) => {
        const apiUrl = process.env.BACKEND_API_URL ?? ''
        const gaId = process.env.GA_MEASUREMENT_ID ?? ''
        const body = `window.__BACKEND_API_URL__=${JSON.stringify(apiUrl)};window.__GA_MEASUREMENT_ID__=${JSON.stringify(gaId)};`
        res.setHeader('Content-Type', 'application/javascript')
        res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
        res.end(body)
      })
    },
  }
}

export default defineConfig(({ command }) => {
  const port = Number(process.env.PORT || 5173)
  const backendUrl = process.env.BACKEND_URL || 'http://localhost:7460'

  return {
    plugins: [react(), tailwindcss(), runtimeConfigPlugin()],
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
