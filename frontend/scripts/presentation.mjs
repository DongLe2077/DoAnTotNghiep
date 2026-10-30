import { spawn } from 'node:child_process'
import { createServer } from 'node:net'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
try {
  for (const port of [5175, 18002]) {
    await new Promise((resolve, reject) => {
      const probe = createServer()
      probe.once('error', () => reject(new Error(`Cổng ${port} đang được sử dụng. Nếu bản trình đã chạy, mở http://127.0.0.1:5175/login; dừng bản cũ trước khi chạy thêm.`)))
      probe.listen(port, '127.0.0.1', () => probe.close(resolve))
    })
  }
} catch (error) { process.stderr.write(`${error.message}\n`); process.exit(1) }
const env = { ...process.env, E2E_DEMO: '1', E2E_PRESENTATION: '1', VITE_DEMO_MODE: '1', VITE_PRESENTATION_MODE: '1', E2E_API_PORT: '18002', E2E_FRONTEND_PORT: '5175', API_PROXY_TARGET: 'http://127.0.0.1:18002' }
const children = []
let stopped = false
function stop() {
  if (stopped) return
  stopped = true
  for (const child of children) {
    if (child.connected) child.send('shutdown')
    else child.kill()
  }
}
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, stop)
for (const args of [['tests/e2e/support/serve-backend.mjs'], ['node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', '5175', '--strictPort']]) {
  const child = spawn(process.execPath, args, { cwd: root, env, stdio: args[0].includes('serve-backend') ? ['inherit', 'inherit', 'inherit', 'ipc'] : 'inherit', windowsHide: true })
  children.push(child)
  child.once('error', error => { process.stderr.write(`${error.message}\n`); stop(); process.exitCode = 1 })
  child.once('exit', code => { if (!stopped) { stop(); process.exitCode = code ?? 1 } })
}
process.stdout.write('\nBản trình riêng: http://127.0.0.1:5175/login\nChủ trọ: admin / 123 · Người thuê một người: user / 123\nĐại diện nhóm: nhom_demo / 123 · Thành viên: thanhvien_demo / 123\nDữ liệu giả được giữ sau khởi động lại; tách khỏi MySQL và bản thử cổng 5174.\nHướng dẫn: docs/presentation-guide.md\n\n')
