$ErrorActionPreference = 'Stop'
$taskFrontend = Split-Path -Parent $PSScriptRoot
$taskToolRoot = Join-Path $taskFrontend '.cache\tools'
$taskVersion = '2026.10.6'
$taskSha256 = 'd521b3bf68982165f0d06d76732f22d9a105ef057ff3ae4c0986f3a23190950f'
$taskUrl = "https://github.com/cloudflare/cloudflared/releases/download/$taskVersion/cloudflared-windows-amd64.exe"
$taskBinary = Join-Path $taskToolRoot 'cloudflared.exe'
function Get-TaskSha256([string] $taskPath) {
    $taskStream = [System.IO.File]::OpenRead($taskPath)
    $taskHasher = [System.Security.Cryptography.SHA256]::Create()
    try { return ([System.BitConverter]::ToString($taskHasher.ComputeHash($taskStream))).Replace('-', '').ToLowerInvariant() }
    finally { $taskStream.Dispose(); $taskHasher.Dispose() }
}
[System.IO.Directory]::CreateDirectory($taskToolRoot) | Out-Null
if (-not [System.IO.File]::Exists($taskBinary) -or (Get-TaskSha256 $taskBinary) -ne $taskSha256) {
    $taskDownload = Join-Path $taskToolRoot 'cloudflared-download.exe'
    Invoke-WebRequest -Uri $taskUrl -OutFile $taskDownload -TimeoutSec 180 -UseBasicParsing
    if ((Get-TaskSha256 $taskDownload) -ne $taskSha256) {
        throw 'Cloudflare download does not match the official SHA-256. The file was not installed.'
    }
    Move-Item -LiteralPath $taskDownload -Destination $taskBinary -Force
}
@{ version = $taskVersion; sha256 = $taskSha256; download = $taskUrl } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $taskToolRoot 'cloudflared-release.json') -Encoding utf8
Write-Output "Verified official Cloudflare tool $taskVersion."
& $taskBinary --version
