param(
    [string]$BaseUrl = 'http://127.0.0.1:8000'
)

$ErrorActionPreference = 'Stop'
$taskBaseUri = [Uri]($BaseUrl.TrimEnd('/') + '/')
if (-not $taskBaseUri.IsAbsoluteUri -or $taskBaseUri.Scheme -notin 'http', 'https') {
    throw 'BaseUrl phải là địa chỉ HTTP hoặc HTTPS hợp lệ.'
}

function Get-AuthXsrfHeaders {
    param($CookieSession, [Uri]$BaseUri)

    $taskXsrfCookie = $CookieSession.Cookies.GetCookies($BaseUri)['XSRF-TOKEN']
    if ($null -eq $taskXsrfCookie) {
        throw 'Chưa có cookie XSRF-TOKEN.'
    }

    return @{
        Accept = 'application/json'
        'X-XSRF-TOKEN' = [Uri]::UnescapeDataString($taskXsrfCookie.Value)
    }
}

function Assert-AuthDenied {
    param([scriptblock]$Action, [int]$ExpectedStatus)

    try {
        & $Action | Out-Null
    } catch {
        if ($null -ne $_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq $ExpectedStatus) {
            return
        }
        throw
    }

    throw "Yêu cầu đáng lẽ phải bị từ chối với HTTP $ExpectedStatus."
}

$taskCredential = Get-Credential -Message 'Nhập tài khoản ứng dụng trong bảng users'
if ($null -eq $taskCredential) {
    throw 'Chưa nhập tài khoản ứng dụng.'
}

$taskLoggedIn = $false
try {
    Invoke-WebRequest -Uri ($taskBaseUri.AbsoluteUri + 'api/auth/csrf-cookie') -SessionVariable taskCookieSession -UseBasicParsing | Out-Null
    $taskLoginBody = @{
        username = $taskCredential.UserName
        password = $taskCredential.GetNetworkCredential().Password
    } | ConvertTo-Json -Compress

    $taskLogin = Invoke-RestMethod -Method Post -Uri ($taskBaseUri.AbsoluteUri + 'api/auth/login') -WebSession $taskCookieSession -ContentType 'application/json' -Headers (Get-AuthXsrfHeaders $taskCookieSession $taskBaseUri) -Body $taskLoginBody
    $taskLoggedIn = $true
    $taskLoginBody = $null
    Write-Host ('Đăng nhập OK. Vai trò: ' + $taskLogin.data.role)

    $taskProfile = Invoke-RestMethod -Uri ($taskBaseUri.AbsoluteUri + 'api/auth/me') -WebSession $taskCookieSession -Headers @{Accept='application/json'}
    if ($taskProfile.data.id -ne $taskLogin.data.id) {
        throw 'Thông tin tài khoản trong phiên không khớp.'
    }
    Write-Host 'Lấy thông tin tài khoản OK.'

    $taskRole = $taskProfile.data.role
    if ($taskRole -notin 'owner', 'tenant') {
        throw 'Vai trò không hợp lệ.'
    }
    $taskOtherRole = if ($taskRole -eq 'owner') { 'tenant' } else { 'owner' }
    Invoke-RestMethod -Uri ($taskBaseUri.AbsoluteUri + "api/$taskRole/access") -WebSession $taskCookieSession -Headers @{Accept='application/json'} | Out-Null
    Assert-AuthDenied -ExpectedStatus 403 -Action {
        Invoke-RestMethod -Uri ($taskBaseUri.AbsoluteUri + "api/$taskOtherRole/access") -WebSession $taskCookieSession -Headers @{Accept='application/json'}
    }
    Write-Host 'Phân quyền OK: vào được khu vực của mình và bị chặn khu vực vai trò khác.'

    Invoke-RestMethod -Method Post -Uri ($taskBaseUri.AbsoluteUri + 'api/auth/logout') -WebSession $taskCookieSession -Headers (Get-AuthXsrfHeaders $taskCookieSession $taskBaseUri) | Out-Null
    $taskLoggedIn = $false
    Assert-AuthDenied -ExpectedStatus 401 -Action {
        Invoke-RestMethod -Uri ($taskBaseUri.AbsoluteUri + 'api/auth/me') -WebSession $taskCookieSession -Headers @{Accept='application/json'}
    }
    Write-Host 'Đăng xuất OK; phiên không còn quyền truy cập.'
} finally {
    if ($taskLoggedIn) {
        try {
            Invoke-RestMethod -Method Post -Uri ($taskBaseUri.AbsoluteUri + 'api/auth/logout') -WebSession $taskCookieSession -Headers (Get-AuthXsrfHeaders $taskCookieSession $taskBaseUri) | Out-Null
        } catch {
            Write-Warning 'Chưa xác nhận được việc đăng xuất phiên kiểm tra.'
        }
    }
    $taskLoginBody = $null
    $taskCredential = $null
}
