import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  return {
    plugins: [vue(), VitePWA({
      strategies: 'injectManifest', srcDir: 'src', filename: 'sw.ts',
      injectRegister: false, manifest: false, registerType: 'prompt', includeAssets: [],
      injectManifest: {
        globPatterns: ['index.html', 'assets/**/*.{js,css}', 'fonts/**/*.woff2', 'icons/*.{png,svg}', 'favicon.svg'],
        maximumFileSizeToCacheInBytes: 2 * 1024 * 1024,
      },
      devOptions: { enabled: false },
    })],
    resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      watch: { ignored: ['**/.cache/**'] },
      // Retain Vite's sensitive-file exclusions and keep disposable databases/photos private.
      fs: { deny: ['.env', '.env.*', '*.{crt,pem,key,p12,pfx,cer,der}', '.npmrc', '.yarnrc.yml', '**/.git/**', '**/.cache/**'] },
      proxy: {
        '/api': {
          target: env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
  }
})
