# PixelBridge Installer - One Click Setup
# Richiede PowerShell come Admin

Write-Host "=== PixelBridge Installer v1.0 ===" -ForegroundColor Cyan
Write-Host ""

# 1. Controlla Python
Write-Host "Controllo Python..." -ForegroundColor Yellow
$pythonInstalled = $false
try {
    $pythonVersion = python --version 2>&1
    if ($pythonVersion -match "Python 3") {
        Write-Host "✓ Python trovato: $pythonVersion" -ForegroundColor Green
        $pythonInstalled = $true
    }
} catch {}

if (-not $pythonInstalled) {
    Write-Host "✗ Python non trovato" -ForegroundColor Red
    Write-Host "Installazione Python in corso..." -ForegroundColor Yellow
    
    $pythonUrl = "https://www.python.org/ftp/python/3.12.0/python-3.12.0-amd64.exe"
    $pythonInstaller = "$env:TEMP\python-installer.exe"
    
    Invoke-WebRequest -Uri $pythonUrl -OutFile $pythonInstaller
    Start-Process -FilePath $pythonInstaller -ArgumentList "/quiet InstallAllUsers=0 PrependPath=1" -Wait
    Remove-Item $pythonInstaller
    
    Write-Host "✓ Python installato" -ForegroundColor Green
}

# 2. Crea cartella PixelBridge
$installPath = "C:\PixelBridge"
if (-not (Test-Path $installPath)) {
    New-Item -Path $installPath -ItemType Directory -Force | Out-Null
}
Write-Host "✓ Cartella creata: $installPath" -ForegroundColor Green

# 3. Scarica agent.py
Write-Host "Download agent.py..." -ForegroundColor Yellow
$agentUrl = "https://raw.githubusercontent.com/tobias94-design/signage/main/agent.py"
Invoke-WebRequest -Uri $agentUrl -OutFile "$installPath\agent.py"
Write-Host "✓ Agent scaricato" -ForegroundColor Green

# 4. Crea launcher.bat
$batContent = @"
@echo off
cd /d "%~dp0"
start /B pythonw agent.py
exit
"@
Set-Content -Path "$installPath\avvia-pixelbridge.bat" -Value $batContent
Write-Host "✓ Launcher creato" -ForegroundColor Green

# 5. Registra autostart
Write-Host "Registro autostart..." -ForegroundColor Yellow
$batPath = "$installPath\avvia-pixelbridge.bat"
Set-ItemProperty -Path "HKCU:\Software\Microsoft\Windows\CurrentVersion\Run" -Name "PixelBridge" -Value "`"$batPath`""
Write-Host "✓ Autostart registrato" -ForegroundColor Green

# 6. Avvia PixelBridge
Write-Host ""
Write-Host "=== Installazione Completata! ===" -ForegroundColor Green
Write-Host ""
Write-Host "Avvio PixelBridge..." -ForegroundColor Cyan
Start-Process -FilePath "$installPath\avvia-pixelbridge.bat"

Write-Host ""
Write-Host "FATTO!" -ForegroundColor Green
Write-Host "Inserisci il codice pairing nel pannello admin:" -ForegroundColor Yellow
Write-Host "http://204.168.161.116/dispositivi.php" -ForegroundColor Cyan
Write-Host ""
pause
