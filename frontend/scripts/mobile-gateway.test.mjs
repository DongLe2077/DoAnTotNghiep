import assert from 'node:assert/strict'
import { randomBytes } from 'node:crypto'
import { mkdirSync, writeFileSync } from 'node:fs'
import { createServer, request } from 'node:http'
import { resolve } from 'node:path'
import { test } from 'node:test'
import { createMobileGateway } from './mobile-gateway.mjs'

const fixture = resolve('.cache/mobile-gateway-tests', randomBytes(8).toString('hex'))
mkdirSync(resolve(fixture, 'assets'), { recursive: true })
writeFileSync(resolve(fixture, 'index.html'), '<!doctype html><title>Mobile fixture</title><div id="app"></div>')
writeFileSync(resolve(fixture, 'assets/app.js'), 'console.log("public bundle")')
writeFileSync(resolve(fixture, 'manifest.webmanifest'), JSON.stringify({ name: 'Nhà Trọ', start_url: '/' }))
writeFileSync(resolve(fixture, 'sw.js'), '// public service worker fixture')
const origin = 'https://gateway-check.trycloudflare.com'
async function listen(server) { await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); return server.address().port }
async function close(server) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)) }
function send(port, path, { method = 'GET', headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const req = request({ hostname: '127.0.0.1', port, path, method, headers: { Host: new URL(origin).host, ...headers } }, response => {
      const chunks = []; response.on('data', chunk => chunks.push(chunk)); response.on('end', () => resolve({ status: response.statusCode, headers: response.headers, body: Buffer.concat(chunks).toString() }))
    })
    req.on('error', reject); req.end(body)
  })
}
test('built UI routes and public assets work; missing assets and private paths never fall back to HTML', async () => {
  const server = createMobileGateway({ directory: fixture, getOrigin: () => origin }), port = await listen(server)
  try {
    for (const path of ['/', '/login', '/owner/invoices/2', '/tenant/maintenance/1']) {
      const response = await send(port, path)
      assert.equal(response.status, 200); assert.match(response.body, /Mobile fixture/); assert.equal(response.headers['cache-control'], 'no-store')
    }
    assert.equal((await send(port, '/assets/app.js')).headers['content-type'], 'text/javascript; charset=utf-8')
    for (const path of ['/.env', '/backend/.env', '/.cache/mobile-https/access.json', '/src/main.ts', '/@fs/D:/KLTN/backend/.env', '/assets/../../backend/.env', '/assets/%252e%252e/secret.js', '/assets/missing.js']) assert.equal((await send(port, path)).status, 404, path)
    assert.equal((await send(port, '/', { headers: { Host: 'attacker.example' } })).status, 421)
    assert.equal((await send(port, '/login', { method: 'POST' })).status, 405)
  } finally { await close(server) }
})
test('gateway replaces forwarded headers, passes the exact API request, keeps secure cookies and forbids caching', async () => {
  let observed
  const upstream = createServer((req, res) => {
    const chunks = []; req.on('data', chunk => chunks.push(chunk)); req.on('end', () => {
      observed = { method: req.method, path: req.url, headers: req.headers, body: Buffer.concat(chunks).toString() }
      res.writeHead(200, { 'Content-Type': 'application/json', 'Set-Cookie': ['session=test; Secure; HttpOnly; SameSite=Lax'], 'Cache-Control': 'public, max-age=1000' }); res.end('{"ok":true}')
    })
  })
  const backendPort = await listen(upstream), gateway = createMobileGateway({ directory: fixture, backendPort, getOrigin: () => origin }), port = await listen(gateway)
  try {
    const response = await send(port, '/api/check?month=2026-10', { method: 'POST', body: '{"value":1}', headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test-csrf', 'X-Forwarded-Host': 'attacker.example', 'X-Forwarded-Proto': 'http', 'X-Forwarded-For': '192.0.2.5', Forwarded: 'host=attacker.example', 'CF-Connecting-IP': '198.51.100.9' } })
    assert.equal(response.status, 200); assert.equal(observed.method, 'POST'); assert.equal(observed.path, '/api/check?month=2026-10'); assert.equal(observed.body, '{"value":1}')
    assert.equal(observed.headers['x-forwarded-host'], new URL(origin).host); assert.equal(observed.headers['x-forwarded-proto'], 'https'); assert.equal(observed.headers['x-forwarded-port'], '443'); assert.equal(observed.headers['x-forwarded-for'], '198.51.100.9'); assert.equal(observed.headers.forwarded, undefined)
    assert.equal(observed.headers['x-xsrf-token'], 'test-csrf'); assert.match(response.headers['set-cookie'][0], /Secure; HttpOnly/); assert.equal(response.headers['cache-control'], 'no-store, private')
  } finally { await close(gateway); await close(upstream) }
})
test('public manifest has its own MIME type, revalidates on every use and is not an SPA fallback', async () => {
  const server = createMobileGateway({ directory: fixture, getOrigin: () => origin }), port = await listen(server)
  try {
    const response = await send(port, '/manifest.webmanifest')
    assert.equal(response.status, 200)
    assert.equal(response.headers['content-type'], 'application/manifest+json')
    assert.equal(response.headers['cache-control'], 'no-cache')
    assert.equal(JSON.parse(response.body).start_url, '/')
    assert.equal((await send(port, '/manifest.webmanifest', { method: 'HEAD' })).body, '')
    const worker = await send(port, '/sw.js')
    assert.equal(worker.status, 200); assert.equal(worker.headers['cache-control'], 'no-cache'); assert.equal(worker.headers['service-worker-allowed'], '/')
    assert.equal(worker.headers['content-type'], 'text/javascript; charset=utf-8')
  } finally { await close(server) }
})
test('preparation and backend loss return truthful errors instead of the frontend HTML', async () => {
  let ready = false
  const server = createMobileGateway({ directory: fixture, backendPort: 1, getOrigin: () => origin, isReady: () => ready }), port = await listen(server)
  try {
    assert.equal((await send(port, '/login')).status, 503)
    ready = true
    const response = await send(port, '/api/auth/me')
    assert.equal(response.status, 502); assert.doesNotMatch(response.body, /Mobile fixture/)
  } finally { await close(server) }
})
