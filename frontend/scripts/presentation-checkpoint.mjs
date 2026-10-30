import { spawnSync } from 'node:child_process'
import { createHash, randomBytes } from 'node:crypto'
import { cpSync, existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, realpathSync, writeFileSync } from 'node:fs'
import { createServer } from 'node:net'
import { dirname, isAbsolute, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..'), workspace = resolve(frontend, '..')
const source = resolve(frontend, '.cache/presentation'), checkpoints = resolve(frontend, '.cache/presentation-checkpoints')
if (process.argv.length > 2) throw new Error('Lệnh này tạo bản sao và kiểm tra khôi phục vào thư mục mới, không nhận đường dẫn ghi đè.')
for (const port of [5175, 18002]) {
  await new Promise((resolve, reject) => {
    const probe = createServer()
    probe.once('error', () => reject(new Error(`Hãy dừng bản trình cổng ${port} trước khi sao lưu để giữ CSDL và ảnh cùng thời điểm.`)))
    probe.listen(port, '127.0.0.1', () => probe.close(resolve))
  })
}
if (!existsSync(resolve(source, 'owner-demo.sqlite')) || !existsSync(resolve(source, 'scenario.json'))) throw new Error('Chạy npm run presentation lần đầu rồi dừng trước khi tạo bản sao.')
const runtime = JSON.parse(readFileSync(resolve(source, 'demo-runtime.json'), 'utf8'))
if (!/^[a-f0-9]{16}$/.test(runtime.runId)) throw new Error('Invalid presentation storage identity.')
const storage = resolve(source, 'runs', runtime.runId, 'storage/app/private/maintenance')
const id = `${new Date().toISOString().replace(/[:.]/g, '-')}-${randomBytes(3).toString('hex')}`
const destination = resolve(checkpoints, id)
if (realpathSync(source) !== source || existsSync(checkpoints) && realpathSync(checkpoints) !== checkpoints) throw new Error('Unexpected presentation snapshot paths.')
mkdirSync(destination, { recursive: true })
const php = process.env.PHP_BINARY || 'php'
const snapshot = spawnSync(php, [resolve(frontend, 'tests/e2e/support/snapshot-presentation.php'), destination], { cwd: frontend, encoding: 'utf8', windowsHide: true })
if (snapshot.error) throw snapshot.error
if (snapshot.status !== 0) throw new Error(snapshot.stderr || snapshot.stdout)
cpSync(storage, resolve(destination, 'photos'), { recursive: true, errorOnExist: true, force: false })
cpSync(resolve(source, 'scenario.json'), resolve(destination, 'scenario.json'), { errorOnExist: true, force: false })

function safeFile(root, path) {
  const absolute = resolve(root, path), rel = relative(root, absolute)
  if (!rel || rel === '..' || rel.startsWith(`..\\`) || rel.startsWith('../') || isAbsolute(rel) || realpathSync(absolute) !== absolute || !lstatSync(absolute).isFile() || lstatSync(absolute).isSymbolicLink()) throw new Error(`Unsafe snapshot file: ${path}`)
  return absolute
}
function hash(path) { return createHash('sha256').update(readFileSync(path)).digest('hex') }
function files(root, prefix = '') {
  const result = []
  for (const item of readdirSync(resolve(root, prefix), { withFileTypes: true })) {
    if (item.isSymbolicLink()) throw new Error('Symlinks cannot enter presentation snapshots.')
    const path = `${prefix}${item.name}`
    if (item.isDirectory()) result.push(...files(root, `${path}/`))
    else if (item.isFile()) result.push(path)
  }
  return result.sort()
}
const git = (...args) => {
  const result = spawnSync('git', args, { cwd: workspace, encoding: 'utf8', windowsHide: true })
  if (result.status !== 0) throw new Error(result.stderr || 'Cannot identify the current source.')
  return result.stdout
}
const sourceFiles = git('ls-files', '-c', '-o', '--exclude-standard', '-z').split('\0').filter(Boolean)
const sourceHashes = {}
for (const path of [...new Set(sourceFiles)].sort()) {
  // Defense in depth even if a local secret was accidentally tracked.
  if (path.split('/').some(part => ['.git', '.codex', '.agents', '.aws', 'node_modules', 'vendor', '.cache'].includes(part) || /^\.env(?:\.|$)/.test(part) && part !== '.env.example') || /(?:^|\/)(?:auth\.json|\.npmrc)$|\.(?:pem|key|p12|pfx|sqlite|db|log)$/i.test(path)) continue
  const input = resolve(workspace, path)
  if (!existsSync(input)) continue
  safeFile(workspace, path)
  const output = resolve(destination, 'source', path)
  mkdirSync(dirname(output), { recursive: true })
  cpSync(input, output, { errorOnExist: true, force: false })
  sourceHashes[path] = hash(output)
}
if (existsSync(resolve(frontend, 'dist'))) cpSync(resolve(frontend, 'dist'), resolve(destination, 'frontend-dist'), { recursive: true })
const photoHashes = Object.fromEntries(files(resolve(destination, 'photos')).map(path => [path, hash(safeFile(resolve(destination, 'photos'), path))]))
const restored = resolve(destination, 'restored')
mkdirSync(restored)
cpSync(resolve(destination, 'database.sqlite'), resolve(restored, 'database.sqlite'), { errorOnExist: true, force: false })
cpSync(resolve(destination, 'photos'), resolve(restored, 'photos'), { recursive: true, errorOnExist: true, force: false })
const verified = spawnSync(php, [resolve(frontend, 'tests/e2e/support/snapshot-presentation.php'), destination, 'verify'], { cwd: frontend, encoding: 'utf8', windowsHide: true })
if (verified.error) throw verified.error
if (verified.status !== 0) throw new Error(verified.stderr || verified.stdout)
const verification = JSON.parse(verified.stdout)
for (const [path, expected] of Object.entries(photoHashes)) if (hash(safeFile(resolve(restored, 'photos'), path)) !== expected) throw new Error('Restored photo differs from the backup.')
const manifest = { version: 1, createdAt: new Date().toISOString(), gitHead: git('rev-parse', 'HEAD').trim(), sourceFiles: sourceHashes,
  databaseSha256: hash(resolve(destination, 'database.sqlite')), photos: photoHashes,
  restoreVerification: verification, location: destination, syntheticDataOnly: true }
writeFileSync(resolve(destination, 'manifest.json'), JSON.stringify(manifest, null, 2))
process.stdout.write(`Bản sao: ${destination}\nĐã khôi phục thử vào thư mục restored riêng: ${Object.keys(sourceHashes).length} tệp mã nguồn, ${Object.keys(photoHashes).length} ảnh; CSDL và dữ liệu đối chiếu đạt.\nCSDL MySQL và bản trình đang dùng được giữ nguyên.\n`)
