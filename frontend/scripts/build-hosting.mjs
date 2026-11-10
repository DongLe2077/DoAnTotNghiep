import { cpSync, existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { createHash, randomBytes } from 'node:crypto'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const workspace = resolve(frontend, '..')
const backend = join(workspace, 'backend')
const demo = process.argv.includes('--demo')
const release = `${new Date().toISOString().replace(/[:.]/g, '-')}-${randomBytes(3).toString('hex')}`
const output = join(workspace, '.cache', 'deployment', 'rentbase', 'packages', release)
const stage = join(output, 'app')
const build = join(output, 'frontend')
mkdirSync(stage, { recursive: true })
function run(command, args, options = {}) {
  const result = spawnSync(command, args, { cwd: frontend, stdio: 'inherit', windowsHide: true, ...options })
  if (result.error || result.status !== 0) throw result.error ?? new Error(`${command} failed (${result.status})`)
}
run(process.execPath, ['node_modules/vue-tsc/bin/vue-tsc.js', '--noEmit'])
run(process.execPath, ['node_modules/vite/bin/vite.js', 'build', '--outDir', build], {
  env: { ...process.env, VITE_DEMO_MODE: demo ? '1' : '0', VITE_PRESENTATION_MODE: '0' },
})
const reject = /^(?:\.env(?:\..*)?|auth\.json|\.npmrc|\.git|node_modules|vendor|\.cache)$/
const secretExtension = /\.(?:pem|key|p12|pfx|sqlite(?:-.*)?|db|log)$/i
function include(source) {
  const name = source.split(/[\\/]/).at(-1)
  const rel = relative(backend, source).replaceAll('\\', '/')
  return !reject.test(name) && !secretExtension.test(name) && !rel.startsWith('bootstrap/cache/')
}
for (const directory of ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'lang']) {
  const source = join(backend, directory)
  if (existsSync(source)) cpSync(source, join(stage, directory), { recursive: true, filter: include })
}
for (const file of ['artisan', 'composer.json', 'composer.lock']) cpSync(join(backend, file), join(stage, file))
cpSync(build, join(stage, 'public'), { recursive: true })
for (const directory of ['bootstrap/cache', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']) {
  mkdirSync(join(stage, directory), { recursive: true })
}
const files = []
function scan(directory) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const absolute = join(directory, entry.name)
    if (entry.isSymbolicLink()) throw new Error('Package may not contain symbolic links.')
    if (entry.isDirectory()) scan(absolute)
    else {
      if (reject.test(entry.name) || secretExtension.test(entry.name)) throw new Error('Private/generated file in package.')
      files.push({ path: relative(stage, absolute).replaceAll('\\', '/'), sha256: createHash('sha256').update(readFileSync(absolute)).digest('hex') })
    }
  }
}
scan(stage)
const archive = join(output, 'rentbase.tar.gz')
run('tar.exe', ['-czf', archive, '-C', stage, '.'])
const metadata = { release, demo, archive, sourceFiles: files.length, archiveSha256: createHash('sha256').update(readFileSync(archive)).digest('hex'), files }
writeFileSync(join(output, 'manifest.json'), JSON.stringify(metadata, null, 2))
writeFileSync(join(workspace, '.cache/deployment/rentbase/package-latest.json'), JSON.stringify(metadata, null, 2))
console.log(JSON.stringify({ release, demo, archive, sourceFiles: files.length, archiveSha256: metadata.archiveSha256 }))
