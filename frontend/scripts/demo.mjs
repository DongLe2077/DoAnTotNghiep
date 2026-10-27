import { spawn } from 'node:child_process'
import { createServer } from 'node:net'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
async function requireFree(port) {
  await new Promise((resolve, reject) => {
    const probe = createServer()
    probe.once('error', () => reject(new Error(`Cổng ${port} đang được sử dụng. Hãy đóng bản chạy thử cũ trước.`)))
    probe.listen(port, '127.0.0.1', () => probe.close(resolve))
  })
}
await requireFree(5174); await requireFree(18001)
const env = { ...process.env, E2E_DEMO: '1', VITE_DEMO_MODE: '1', E2E_API_PORT: '18001', E2E_FRONTEND_PORT: '5174', API_PROXY_TARGET: 'http://127.0.0.1:18001' }
const children = []
let stopped = false
function stop() {
  if (stopped) return
  stopped = true
  for (const child of children) {
    // Let the backend supervisor close its PHP child before exiting on Windows.
    if (child.connected) child.send('shutdown')
    else child.kill()
  }
}
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, stop)
for (const args of [['tests/e2e/support/serve-backend.mjs'], ['node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', '5174', '--strictPort']]) {
  const child = spawn(process.execPath, args, { cwd: root, env, stdio: args[0].includes('serve-backend') ? ['inherit', 'inherit', 'inherit', 'ipc'] : 'inherit', windowsHide: true })
  children.push(child)
  child.once('error', error => { process.stderr.write(`${error.message}\n`); stop(); process.exitCode = 1 })
  child.once('exit', code => { if (!stopped) { stop(); process.exitCode = code ?? 1 } })
}
process.stdout.write('\nBản chạy thử riêng: http://127.0.0.1:5174/login\nTài khoản chủ trọ: admin / 123\nTài khoản người thuê: user / 123\nCác tab cùng trình duyệt dùng chung phiên đăng nhập.\nDữ liệu chạy thử được giữ khi khởi động lại.\n\n')
