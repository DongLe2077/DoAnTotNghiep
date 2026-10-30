import { spawnSync } from 'node:child_process'
import { createHash, randomBytes } from 'node:crypto'
import { cpSync, existsSync, lstatSync, mkdirSync, readFileSync, realpathSync, renameSync, writeFileSync } from 'node:fs'
import { createServer } from 'node:net'
import { dirname, isAbsolute, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..'), root = resolve(frontend, '.cache/presentation')
const checkpoints = resolve(frontend, '.cache/presentation-checkpoints'), id = process.argv[2] ?? ''
if (process.argv.length !== 3 || !/^\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2}-\d{3}Z-[a-f0-9]{6}$/.test(id)) throw new Error('Dùng npm run presentation:restore -- MA_BAN_SAO (tên thư mục bản sao, không phải đường dẫn).')
for (const port of [5175, 18002]) await new Promise((resolve, reject) => {
  const probe = createServer()
  probe.once('error', () => reject(new Error(`Dừng bản trình cổng ${port} trước khi khôi phục.`)))
  probe.listen(port, '127.0.0.1', () => probe.close(resolve))
})
function checkedFile(base, path) {
  const target = resolve(base, path), rel = relative(base, target)
  if (!rel || rel === '..' || rel.startsWith('..\\') || rel.startsWith('../') || isAbsolute(rel) || realpathSync(target) !== target || !lstatSync(target).isFile()) throw new Error(`Unsafe restore file: ${path}`)
  return target
}
const checkpoint = resolve(checkpoints, id)
if (realpathSync(checkpoint) !== checkpoint || realpathSync(root) !== root) throw new Error('Unexpected presentation paths.')
const manifest = JSON.parse(readFileSync(checkedFile(checkpoint, 'manifest.json'), 'utf8'))
const hash = path => createHash('sha256').update(readFileSync(path)).digest('hex')
if (manifest.syntheticDataOnly !== true || hash(checkedFile(checkpoint, 'database.sqlite')) !== manifest.databaseSha256) throw new Error('Checkpoint database hash failed.')
for (const [path, expected] of Object.entries(manifest.photos ?? {})) if (hash(checkedFile(resolve(checkpoint, 'photos'), path)) !== expected) throw new Error('Checkpoint photo hash failed.')
const php = process.env.PHP_BINARY || 'php', helper = resolve(frontend, 'tests/e2e/support/snapshot-presentation.php')
function verify(mode) {
  const result = spawnSync(php, [helper, checkpoint, mode], { cwd: frontend, encoding: 'utf8', windowsHide: true })
  if (result.error) throw result.error
  if (result.status !== 0) throw new Error(result.stderr || result.stdout)
  return JSON.parse(result.stdout)
}
verify('verify')
const runtime = JSON.parse(readFileSync(checkedFile(root, 'demo-runtime.json'), 'utf8'))
if (!/^[a-f0-9]{16}$/.test(runtime.runId)) throw new Error('Invalid active presentation storage identity.')
const activeDatabase = checkedFile(root, 'owner-demo.sqlite'), activePhotos = resolve(root, 'runs', runtime.runId, 'storage/app/private/maintenance')
if (['-wal', '-shm', '-journal'].some(suffix => existsSync(`${activeDatabase}${suffix}`))) throw new Error('CSDL còn tệp giao dịch SQLite; hãy kiểm tra và dừng sạch bản trình trước khi khôi phục.')
if (realpathSync(activePhotos) !== activePhotos || !lstatSync(activePhotos).isDirectory()) throw new Error('Unexpected active photo directory.')
const safetyRoot = resolve(frontend, '.cache/presentation-restore-safety'), safety = resolve(safetyRoot, `${Date.now()}-${randomBytes(4).toString('hex')}`)
if (existsSync(safetyRoot) && realpathSync(safetyRoot) !== safetyRoot) throw new Error('Unexpected restore safety directory.')
mkdirSync(safety, { recursive: true })
if (realpathSync(safety) !== safety) throw new Error('Unexpected resolved restore directory.')
const nextDatabase = resolve(safety, 'prepared.sqlite'), nextPhotos = resolve(safety, 'prepared-photos')
cpSync(resolve(checkpoint, 'database.sqlite'), nextDatabase, { errorOnExist: true, force: false })
cpSync(resolve(checkpoint, 'photos'), nextPhotos, { recursive: true, errorOnExist: true, force: false })
const previousDatabase = resolve(safety, 'previous.sqlite'), previousPhotos = resolve(safety, 'previous-photos')
let databaseMoved = false, photosMoved = false, databaseInstalled = false, photosInstalled = false
try {
  // Every move is between verified exact presentation paths inside this workspace.
  renameSync(activeDatabase, previousDatabase); databaseMoved = true
  renameSync(activePhotos, previousPhotos); photosMoved = true
  renameSync(nextDatabase, activeDatabase); databaseInstalled = true
  renameSync(nextPhotos, activePhotos); photosInstalled = true
  const result = verify('verify-active')
  if (existsSync(resolve(root, 'scenario.json'))) cpSync(resolve(root, 'scenario.json'), resolve(safety, 'previous-scenario.json'))
  cpSync(resolve(checkpoint, 'scenario.json'), resolve(root, 'scenario.json'))
  writeFileSync(resolve(safety, 'restore.json'), JSON.stringify({ checkpoint: id, restoredAt: new Date().toISOString(), result }, null, 2))
  process.stdout.write(`Đã khôi phục dữ liệu bản trình từ ${id}.\nBản trước khôi phục được giữ tại: ${safety}\nMã nguồn và MySQL được giữ nguyên. Chạy npm run presentation để mở lại.\n`)
} catch (error) {
  if (photosInstalled) renameSync(activePhotos, resolve(safety, 'failed-photos'))
  if (databaseInstalled) renameSync(activeDatabase, resolve(safety, 'failed.sqlite'))
  if (photosMoved) renameSync(previousPhotos, activePhotos)
  if (databaseMoved) renameSync(previousDatabase, activeDatabase)
  if (existsSync(resolve(safety, 'previous-scenario.json'))) cpSync(resolve(safety, 'previous-scenario.json'), resolve(root, 'scenario.json'))
  throw error
}
