# SQL Schema Comparator - Portable Windows Distribution Builder
$ErrorActionPreference = "Stop"

$phpUrl = "https://windows.php.net/downloads/releases/archives/php-8.2.19-nts-Win32-vs16-x64.zip"
$sqlSrvUrl = "https://github.com/microsoft/msphpsql/releases/download/v5.11.1/Windows-8.2.zip"

$baseDir = Get-Location
$buildDir = Join-Path $baseDir "dist"
$appDistDir = Join-Path $buildDir "SQLDBCompare"
$appWebDir = Join-Path $appDistDir "app"
$appPhpDir = Join-Path $appDistDir "php"
$cacheDir = Join-Path $baseDir "build_cache"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Building SQL Schema Comparator Portable Package" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Clean output directories
if (Test-Path $buildDir) {
    Write-Host "Cleaning existing dist directory..." -ForegroundColor Yellow
    Remove-Item $buildDir -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $appWebDir | Out-Null
New-Item -ItemType Directory -Force -Path $appPhpDir | Out-Null

if (!(Test-Path $cacheDir)) {
    New-Item -ItemType Directory -Force -Path $cacheDir | Out-Null
}

# 2. Compile C# Launcher
Write-Host "Compiling Launcher.cs..." -ForegroundColor Green
$cscPath = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
if (!(Test-Path $cscPath)) {
    throw "C# Compiler not found at $cscPath"
}
$launcherDest = Join-Path $appDistDir "SQLDBCompare.exe"
& $cscPath /nologo /out:$launcherDest Launcher.cs
Write-Host "Launcher compiled successfully to SQLDBCompare.exe" -ForegroundColor Green

# 3. Copy Web Application Files
Write-Host "Copying web application files..." -ForegroundColor Green
$webDirs = @("config", "engine", "assets", "uploads", "output")
foreach ($dir in $webDirs) {
    $srcDir = Join-Path $baseDir $dir
    if (Test-Path $srcDir) {
        $destDir = Join-Path $appWebDir $dir
        Copy-Item -Path $srcDir -Destination $destDir -Recurse -Force
    }
}

$webFiles = @("compare.php", "connect.php", "index.php", "report.php", "upload.php")
foreach ($file in $webFiles) {
    $srcFile = Join-Path $baseDir $file
    if (Test-Path $srcFile) {
        Copy-Item -Path $srcFile -Destination $appWebDir -Force
    }
}

# Clean any temporary testing output inside dist app folders
$tempFixScripts = Join-Path $appWebDir "output\fix_scripts"
if (Test-Path $tempFixScripts) {
    Get-ChildItem $tempFixScripts -Exclude ".gitignore" | Remove-Item -Force
}
$tempUploads = Join-Path $appWebDir "uploads"
if (Test-Path $tempUploads) {
    Get-ChildItem $tempUploads -Exclude ".gitignore" | Remove-Item -Force
}

# 4. Download PHP Runtime
$phpZipPath = Join-Path $cacheDir "php-8.2.19-nts-x64.zip"
if (Test-Path $phpZipPath) {
    Write-Host "Using cached PHP ZIP: $phpZipPath" -ForegroundColor Gray
} else {
    Write-Host "Downloading portable PHP runtime (PHP 8.2.19 NTS x64)..." -ForegroundColor Green
    Invoke-WebRequest -Uri $phpUrl -OutFile $phpZipPath -UseBasicParsing
}

Write-Host "Extracting PHP..." -ForegroundColor Green
Expand-Archive -Path $phpZipPath -DestinationPath $appPhpDir -Force

# 5. Download SQLSRV Extensions
$sqlSrvZipPath = Join-Path $cacheDir "sqlsrv-windows-8.2.zip"
if (Test-Path $sqlSrvZipPath) {
    Write-Host "Using cached SQLSRV extensions ZIP: $sqlSrvZipPath" -ForegroundColor Gray
} else {
    Write-Host "Downloading Microsoft SQLSRV PHP extensions..." -ForegroundColor Green
    Invoke-WebRequest -Uri $sqlSrvUrl -OutFile $sqlSrvZipPath -UseBasicParsing
}

$tempExtractDir = Join-Path $cacheDir "sqlsrv_temp"
if (Test-Path $tempExtractDir) { Remove-Item $tempExtractDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $tempExtractDir | Out-Null

Write-Host "Extracting SQLSRV DLLs..." -ForegroundColor Green
Expand-Archive -Path $sqlSrvZipPath -DestinationPath $tempExtractDir -Force

# Copy driver DLLs to php/ext
$extDestDir = Join-Path $appPhpDir "ext"
if (!(Test-Path $extDestDir)) {
    New-Item -ItemType Directory -Force -Path $extDestDir | Out-Null
}

Write-Host "Copying SQLSRV DLLs to PHP extensions directory..." -ForegroundColor Green
$sqlsrvDll = Get-ChildItem -Path $tempExtractDir -Filter "php_sqlsrv_82_nts.dll" -Recurse | Select-Object -First 1
$pdoSqlsrvDll = Get-ChildItem -Path $tempExtractDir -Filter "php_pdo_sqlsrv_82_nts.dll" -Recurse | Select-Object -First 1

if ($sqlsrvDll -eq $null -or $pdoSqlsrvDll -eq $null) {
    throw "SQLSRV drivers not found in extracted files."
}

Copy-Item $sqlsrvDll.FullName -Destination (Join-Path $extDestDir "php_sqlsrv.dll") -Force
Copy-Item $pdoSqlsrvDll.FullName -Destination (Join-Path $extDestDir "php_pdo_sqlsrv.dll") -Force

# Clean up temp extract dir
Remove-Item $tempExtractDir -Recurse -Force

# 6. Configure php.ini
Write-Host "Configuring php.ini..." -ForegroundColor Green
$phpIniContent = @'
[PHP]
extension_dir = "ext"
extension = php_sqlsrv.dll
extension = php_pdo_sqlsrv.dll

memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M

; Enable typical extensions that php-cli might require
extension = php_curl.dll
extension = php_mbstring.dll
extension = php_openssl.dll
extension = php_fileinfo.dll
'@

$phpIniPath = Join-Path $appPhpDir "php.ini"
Set-Content -Path $phpIniPath -Value $phpIniContent

# 7. Compress the distribution folder
$outputZip = Join-Path $baseDir "SQLDBCompare_Portable.zip"
if (Test-Path $outputZip) {
    Remove-Item $outputZip -Force
}

Write-Host "Waiting 5 seconds for file handles to release..." -ForegroundColor Gray
Start-Sleep -Seconds 5

Write-Host "Compressing everything into SQLDBCompare_Portable.zip..." -ForegroundColor Green
Compress-Archive -Path $appDistDir -DestinationPath $outputZip -Force

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host " Build Finished Successfully!" -ForegroundColor Green
Write-Host " Output Zip: $outputZip" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Green
