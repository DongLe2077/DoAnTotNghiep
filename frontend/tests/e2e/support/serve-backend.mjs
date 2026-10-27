import { spawn, spawnSync } from 'node:child_process'
import { randomBytes } from 'node:crypto'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const supportPath = dirname(fileURLToPath(import.meta.url))
const frontendPath = resolve(supportPath, '../../..')
const backendPath = resolve(frontendPath, '../backend')
const isolatedPath = resolve(frontendPath, '.cache/e2e')
const demoMode = process.env.E2E_DEMO === '1'
let runId = randomBytes(8).toString('hex')
if (demoMode) {
  mkdirSync(isolatedPath, { recursive: true })
  const manifest = resolve(isolatedPath, 'demo-runtime.json')
  if (existsSync(manifest)) {
    const previous = JSON.parse(readFileSync(manifest, 'utf8'))
    if (!/^[a-f0-9]{16}$/.test(previous.runId)) throw new Error('Invalid demo storage identity.')
    runId = previous.runId
  } else writeFileSync(manifest, JSON.stringify({ runId }))
}
const storagePath = resolve(isolatedPath, 'runs', runId, 'storage')
const cachePath = resolve(isolatedPath, 'runs', runId, 'bootstrap-cache')
const php = process.env.PHP_BINARY || 'php'
const port = process.env.E2E_API_PORT || '18000'
const frontendPort = process.env.E2E_FRONTEND_PORT || '15173'
// Laravel's cache-path resolver treats relative paths consistently on Windows.
const cacheFile = (name) => relative(backendPath, resolve(cachePath, name)).replaceAll('\\', '/')

if (!/^\d{2,5}$/.test(port) || !/^\d{2,5}$/.test(frontendPort)) {
  throw new Error('E2E server ports must be numeric.')
}

for (const directory of [
  isolatedPath,
  cachePath,
  resolve(storagePath, 'framework/cache/data'),
  resolve(storagePath, 'framework/sessions'),
  resolve(storagePath, 'framework/views'),
  resolve(storagePath, 'logs'),
  resolve(storagePath, 'app/private'),
  resolve(storagePath, 'app/public'),
]) {
  mkdirSync(directory, { recursive: true })
}

// Explicit overrides keep the normal Laravel .env and rental_management untouched.
// A fresh key and cache prefix also prevent sessions/throttles leaking between runs.
const env = {
  ...process.env,
  APP_ENV: 'e2e',
  APP_NAME: 'Rental E2E',
  APP_DEBUG: 'false',
  APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
  APP_URL: `http://127.0.0.1:${port}`,
  APP_CONFIG_CACHE: cacheFile('config.php'),
  APP_ROUTES_CACHE: cacheFile('routes.php'),
  APP_EVENTS_CACHE: cacheFile('events.php'),
  APP_SERVICES_CACHE: cacheFile('services.php'),
  APP_PACKAGES_CACHE: cacheFile('packages.php'),
  APP_MAINTENANCE_DRIVER: 'file',
  LARAVEL_STORAGE_PATH: storagePath,
  E2E_ISOLATED_PATH: isolatedPath,
  E2E_RUN_ID: runId,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: resolve(isolatedPath, demoMode ? 'owner-demo.sqlite' : 'auth.sqlite'),
  DB_URL: '',
  DB_USERNAME: '',
  DB_PASSWORD: '',
  DB_FOREIGN_KEYS: 'true',
  SESSION_DRIVER: 'file',
  SESSION_COOKIE: demoMode ? 'rental_owner_demo_session' : 'rental_e2e_session',
  SESSION_DOMAIN: 'null',
  SESSION_SECURE_COOKIE: 'false',
  SESSION_HTTP_ONLY: 'true',
  SESSION_SAME_SITE: 'lax',
  SESSION_PATH: '/',
  CACHE_STORE: 'file',
  CACHE_PREFIX: `rental_e2e_${runId}_`,
  LOG_CHANNEL: 'single',
  MAIL_MAILER: 'array',
  QUEUE_CONNECTION: 'sync',
  BCRYPT_ROUNDS: '4',
  CORS_ALLOWED_ORIGINS: `http://127.0.0.1:${frontendPort}`,
}

// Browser tests always reset their own fixture; the manual demo retains user edits.
const scripts = demoMode && existsSync(env.DB_DATABASE) ? [] : ['prepare-backend.php']
if (demoMode) scripts.push('prepare-demo.php')
for (const script of scripts) {
  const prepared = spawnSync(php, [resolve(supportPath, script)], {
    cwd: backendPath,
    env,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    windowsHide: true,
  })
  if (prepared.error) throw prepared.error
  process.stdout.write(prepared.stdout || '')
  process.stderr.write(prepared.stderr || '')
  const ready = script === 'prepare-backend.php' ? 'Isolated E2E auth fixtures ready' : '"demo_accounts_ready":true'
  if (prepared.status !== 0 || !prepared.stdout.includes(ready)) process.exit(prepared.status || 1)
}

const server = spawn(php, [
  '-S', `127.0.0.1:${port}`,
  '-t', resolve(backendPath, 'public'),
  resolve(supportPath, 'router.php'),
], {
  cwd: backendPath,
  env,
  stdio: 'inherit',
  windowsHide: true,
})

server.on('error', (error) => {
  console.error(error.message)
  process.exitCode = 1
})
server.on('exit', (code, signal) => {
  process.exit(code ?? (signal ? 0 : 1))
})
for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => server.kill(signal))
}
if (process.send) process.on('message', message => { if (message === 'shutdown') server.kill() })
