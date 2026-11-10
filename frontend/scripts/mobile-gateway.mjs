import { createReadStream, existsSync, realpathSync, statSync } from 'node:fs'
import { createServer, request as upstreamRequest } from 'node:http'
import { isIP } from 'node:net'
import { extname, isAbsolute, relative, resolve } from 'node:path'

const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.png': 'image/png', '.ico': 'image/x-icon', '.webmanifest': 'application/manifest+json' }
export function createMobileGateway({ directory, backendPort = 18003, getOrigin, isReady = () => true }) {
  const root = realpathSync(directory)
  const server = createServer((req, res) => {
    res.setHeader('X-Content-Type-Options', 'nosniff')
    res.setHeader('Referrer-Policy', 'same-origin')
    res.setHeader('Cache-Control', 'no-store')
    res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'")
    const reply = (status, message) => { if (res.headersSent) return; res.writeHead(status, { 'Content-Type': 'text/plain; charset=utf-8' }); res.end(message) }
    const origin = getOrigin(), host = origin ? new URL(origin).host : null
    if (![host, '127.0.0.1:5176', 'localhost:5176'].filter(Boolean).includes(req.headers.host)) return reply(421, 'Địa chỉ thử không hợp lệ.')
    if (!isReady()) return reply(503, 'Bản thử điện thoại đang chuẩn bị. Vui lòng tải lại sau một chút.')
    if (req.method === 'OPTIONS') return reply(405, 'Không hỗ trợ yêu cầu này.')
    let path
    try { path = decodeURIComponent(new URL(req.url, 'http://127.0.0.1').pathname) } catch { return reply(400, 'Đường dẫn không hợp lệ.') }
    if (path.includes('\\') || path.includes('\0') || path.split('/').some(part => part.startsWith('.')) || /\.(?:php|sqlite|db|log|map|pem|key)(?:\/|$)/i.test(path)) return reply(404, 'Không tìm thấy nội dung.')
    if (/^\/api(?:\/|$)/.test(path) || path === '/up') {
      if (!host) return reply(503, 'Địa chỉ HTTPS chưa sẵn sàng.')
      const headers = { ...req.headers, host, 'x-forwarded-host': host, 'x-forwarded-proto': 'https', 'x-forwarded-port': '443' }
      delete headers.forwarded
      delete headers['x-forwarded-prefix']
      delete headers.connection
      const connecting = req.headers['cf-connecting-ip']
      headers['x-forwarded-for'] = typeof connecting === 'string' && isIP(connecting) ? connecting : req.socket.remoteAddress || '127.0.0.1'
      const proxy = upstreamRequest({ hostname: '127.0.0.1', port: backendPort, path: req.url, method: req.method, headers, timeout: 90_000 }, response => {
        if (res.headersSent || res.destroyed) { response.destroy(); return }
        const responseHeaders = { ...response.headers, 'cache-control': 'no-store, private', 'x-content-type-options': 'nosniff' }
        delete responseHeaders['x-powered-by']
        delete responseHeaders.connection
        res.writeHead(response.statusCode || 502, responseHeaders)
        response.pipe(res)
      })
      proxy.on('timeout', () => proxy.destroy(new Error('upstream timeout')))
      proxy.on('error', () => reply(502, 'Máy chủ thử chưa sẵn sàng. Vui lòng thử lại.'))
      let size = 0
      req.on('data', chunk => { size += chunk.length; if (size > 28 * 1024 * 1024) { reply(413, 'Dữ liệu gửi vượt giới hạn.'); proxy.destroy(); req.unpipe(proxy) } })
      req.on('aborted', () => proxy.destroy())
      res.on('close', () => { if (!res.writableFinished) proxy.destroy() })
      req.pipe(proxy)
      return
    }
    if (!['GET', 'HEAD'].includes(req.method)) return reply(405, 'Không hỗ trợ yêu cầu này.')
    const asset = ['/favicon.svg', '/manifest.webmanifest', '/sw.js', '/index.html'].includes(path) || /^\/(?:assets|fonts|icons)\//.test(path)
    if (!asset && !/^\/(?:$|login$|account$|notifications$|forbidden$|connection-error$|owner(?:\/|$)|tenant(?:\/|$))/.test(path)) return reply(404, 'Không tìm thấy trang.')
    const file = resolve(root, asset ? `.${path}` : 'index.html'), rel = relative(root, file)
    if (!rel || rel === '..' || rel.startsWith('../') || rel.startsWith('..\\') || isAbsolute(rel) || !existsSync(file) || !statSync(file).isFile() || realpathSync(file) !== file) return reply(404, 'Không tìm thấy tệp.')
    const type = types[extname(file)]
    if (!type) return reply(404, 'Không tìm thấy tệp.')
    res.writeHead(200, { 'Content-Type': type, 'Content-Length': statSync(file).size, 'Cache-Control': ['/manifest.webmanifest', '/sw.js'].includes(path) ? 'no-cache' : path === '/index.html' ? 'no-store' : asset ? 'public, max-age=604800' : 'no-store', ...(path === '/sw.js' ? { 'Service-Worker-Allowed': '/' } : {}) })
    if (req.method === 'HEAD') return res.end()
    const stream = createReadStream(file)
    stream.on('error', () => res.destroy())
    stream.pipe(res)
  })
  server.headersTimeout = 30_000
  server.requestTimeout = 100_000
  return server
}
