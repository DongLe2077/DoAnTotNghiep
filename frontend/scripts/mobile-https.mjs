import { spawn, spawnSync } from 'node:child_process'
import { createHash, randomBytes } from 'node:crypto'
import { createWriteStream, existsSync, mkdirSync, readFileSync, realpathSync, writeFileSync } from 'node:fs'
import { get } from 'node:http'
import { createServer } from 'node:net'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { setTimeout as pause } from 'node:timers/promises'
import { createMobileGateway } from './mobile-gateway.mjs'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..'), root = resolve(frontend, '.cache/mobile-https')
const cloudflared = resolve(frontend, '.cache/tools/cloudflared.exe')
const expectedCloudflared = 'd521b3bf68982165f0d06d76732f22d9a105ef057ff3ae4c0986f3a23190950f'
const gatewayPort = 5176, backendPort = 18003
let origin = '', ready = false, stopped = false, ownsRuntime = false, gateway
const children = [], streams = []
function stop() {
  if (stopped) return
  stopped = true; ready = false
  for (const child of children) { if (child.connected) child.send('shutdown'); else child.kill() }
  gateway?.closeAllConnections(); gateway?.close()
  for (const stream of streams) stream.end()
  if (ownsRuntime && existsSync(root)) writeFileSync(resolve(root, 'status.json'), JSON.stringify({ origin, running: false, stoppedAt: new Date().toISOString() }, null, 2))
}
function fail(error) { process.stderr.write(`${error.message}\n`); process.exitCode = 1; stop() }
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, stop)
function monitor(child, name) {
  children.push(child)
  child.once('error', fail)
  child.once('exit', code => { if (!stopped) fail(new Error(`${name} đã dừng (${code ?? 'signal'}). Kiểm tra nhật ký trong .cache/mobile-https.`)) })
}
try {
  for (const port of [gatewayPort, backendPort]) await new Promise((resolve, reject) => {
    const probe = createServer()
    probe.once('error', () => reject(new Error(`Cổng ${port} đang được sử dụng. Kiểm tra bản thử điện thoại đang chạy trước khi mở thêm.`)))
    probe.listen(port, '127.0.0.1', () => probe.close(resolve))
  })
  if (!existsSync(cloudflared) || createHash('sha256').update(readFileSync(cloudflared)).digest('hex') !== expectedCloudflared) throw new Error('Chạy npm run mobile:https:setup trước để có công cụ Cloudflare chính thức đã kiểm tra.')
  if (existsSync(root) && realpathSync(root) !== root) throw new Error('Unexpected mobile runtime path.')
  mkdirSync(root, { recursive: true })
  const accessPath = resolve(root, 'access.json')
  if (!existsSync(accessPath)) {
    if (existsSync(resolve(root, 'owner-demo.sqlite'))) throw new Error('Thiếu access.json của dữ liệu đã có. Hãy khôi phục tệp này; chương trình không tự đặt lại mật khẩu.')
    const accounts = {}
    for (const [username, role, prefix] of [['admin', 'owner', 'Owner'], ['user', 'tenant', 'User'], ['nhom_demo', 'tenant', 'Group'], ['thanhvien_demo', 'tenant', 'Member'], ['phong_demo', 'tenant', 'Room']]) accounts[username] = { role, password: `${prefix}-${randomBytes(9).toString('hex')}` }
    writeFileSync(accessPath, JSON.stringify({ version: 1, accounts }, null, 2), { mode: 0o600, flag: 'wx' })
  }
  const access = JSON.parse(readFileSync(accessPath, 'utf8'))
  process.stdout.write('Đang kiểm tra và đóng gói giao diện cho bản thử điện thoại…\n')
  const buildEnv = { ...process.env, VITE_DEMO_MODE: '0', VITE_PRESENTATION_MODE: '0' }
  const dist = resolve(root, 'dist')
  if (existsSync(dist) && realpathSync(dist) !== dist) throw new Error('Unexpected mobile build directory.')
  let buildLog = ''
  for (const args of [['node_modules/vue-tsc/bin/vue-tsc.js', '--noEmit'], ['node_modules/vite/bin/vite.js', 'build', '--outDir', '.cache/mobile-https/dist']]) {
    const result = spawnSync(process.execPath, args, { cwd: frontend, env: buildEnv, encoding: 'utf8', windowsHide: true })
    buildLog += `${result.stdout || ''}\n${result.stderr || ''}\n`
    writeFileSync(resolve(root, 'build.log'), buildLog)
    if (result.error) throw result.error
    if (result.status !== 0) throw new Error('Đóng gói chưa thành công. Xem .cache/mobile-https/build.log.')
  }
  gateway = createMobileGateway({ directory: dist, getOrigin: () => origin, isReady: () => ready })
  await new Promise((resolve, reject) => { gateway.once('error', reject); gateway.listen(gatewayPort, '127.0.0.1', resolve) })
  ownsRuntime = true
  process.stdout.write('Đang mở địa chỉ HTTPS tạm…\n')
  const tunnelLog = createWriteStream(resolve(root, 'tunnel.log'), { flags: 'a' }); streams.push(tunnelLog)
  tunnelLog.once('error', fail)
  const tunnel = spawn(cloudflared, ['tunnel', '--no-autoupdate', '--url', `http://127.0.0.1:${gatewayPort}`, '--protocol', 'http2', '--loglevel', 'info'], { cwd: frontend, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true })
  monitor(tunnel, 'Kết nối HTTPS')
  origin = await new Promise((resolve, reject) => {
    const deadline = setTimeout(() => reject(new Error('Chưa nhận được địa chỉ HTTPS. Kiểm tra .cache/mobile-https/tunnel.log.')), 45_000)
    function receive(chunk) {
      if (!tunnelLog.writableEnded) tunnelLog.write(chunk)
      const match = chunk.toString().match(/https:\/\/[a-z0-9-]+\.trycloudflare\.com\b/)
      if (match) { clearTimeout(deadline); resolve(match[0]) }
    }
    tunnel.stdout.on('data', receive); tunnel.stderr.on('data', receive)
    tunnel.once('error', error => { clearTimeout(deadline); reject(error) })
    tunnel.once('exit', () => { clearTimeout(deadline); reject(new Error('Kết nối HTTPS dừng trước khi có địa chỉ.')) })
  })
  if (stopped) throw new Error('Bản thử đã dừng.')
  const backendLog = createWriteStream(resolve(root, 'backend.log'), { flags: 'a' }); streams.push(backendLog)
  backendLog.once('error', fail)
  const backend = spawn(process.execPath, ['tests/e2e/support/serve-backend.mjs'], {
    cwd: frontend, env: { ...process.env, PAYOS_ENABLED: 'false', BROADCAST_CONNECTION: 'null', PUSHER_APP_ID: '', PUSHER_APP_KEY: '', PUSHER_APP_SECRET: '', PUSHER_APP_CLUSTER: '', E2E_DEMO: '1', E2E_PRESENTATION: '1', E2E_MOBILE: '1', E2E_API_PORT: String(backendPort), E2E_FRONTEND_PORT: String(gatewayPort), MOBILE_PUBLIC_ORIGIN: origin },
    stdio: ['ignore', 'pipe', 'pipe', 'ipc'], windowsHide: true,
  })
  monitor(backend, 'Máy chủ dữ liệu thử')
  backend.stdout.on('data', chunk => { if (!backendLog.writableEnded) backendLog.write(chunk) }); backend.stderr.on('data', chunk => { if (!backendLog.writableEnded) backendLog.write(chunk) })
  async function healthy() { return new Promise(resolve => { const req = get(`http://127.0.0.1:${backendPort}/up`, response => { response.resume(); resolve(response.statusCode === 200) }); req.on('error', () => resolve(false)); req.setTimeout(1500, () => { req.destroy(); resolve(false) }) }) }
  const deadline = Date.now() + 120_000
  while (!stopped && Date.now() < deadline && !await healthy()) await pause(500)
  if (stopped || !await healthy()) throw new Error('Dữ liệu thử chưa sẵn sàng. Xem .cache/mobile-https/backend.log.')
  ready = true
  writeFileSync(resolve(root, 'status.json'), JSON.stringify({ origin, running: true, startedAt: new Date().toISOString(), gatewayPort, backendPort }, null, 2))
  const text = `Địa chỉ thử điện thoại: ${origin}/login\nChủ trọ: admin / ${access.accounts.admin.password}\nNgười thuê: user / ${access.accounts.user.password}\nĐây là mật khẩu ban đầu của bản dữ liệu mẫu riêng.\nGiữ cửa sổ này và máy tính có mạng. Ctrl+C để dừng; chạy lại có địa chỉ HTTPS mới.\n`
  writeFileSync(resolve(root, 'access.txt'), text, { mode: 0o600 })
  process.stdout.write(`\n${text}\nThông tin truy cập riêng: ${accessPath}\n`)
} catch (error) { fail(error) }
