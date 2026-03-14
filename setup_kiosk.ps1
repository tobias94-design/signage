# PixelBridge Kiosk Setup — esegui come Amministratore
# Disabilita tutto quello che potrebbe comparire sulla TV

Write-Host "=== PixelBridge Kiosk Setup ===" -ForegroundColor Cyan

# ── 1. AGGIORNAMENTI AUTOMATICI ──────────────────────────────
Write-Host "Disabilito aggiornamenti automatici..." -ForegroundColor Yellow
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" /v NoAutoUpdate /t REG_DWORD /d 1 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" /v AUOptions /t REG_DWORD /d 1 /f | Out-Null

# ── 2. NOTIFICHE SISTEMA ─────────────────────────────────────
Write-Host "Disabilito notifiche..." -ForegroundColor Yellow
reg add "HKCU\SOFTWARE\Policies\Microsoft\Windows\Explorer" /v DisableNotificationCenter /t REG_DWORD /d 1 /f | Out-Null
reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\PushNotifications" /v ToastEnabled /t REG_DWORD /d 0 /f | Out-Null

# ── 3. ACTION CENTER ─────────────────────────────────────────
reg add "HKCU\SOFTWARE\Policies\Microsoft\Windows\Explorer" /v DisableNotificationCenter /t REG_DWORD /d 1 /f | Out-Null

# ── 4. SCHERMATA BLOCCO ──────────────────────────────────────
Write-Host "Disabilito schermata di blocco..." -ForegroundColor Yellow
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\Personalization" /v NoLockScreen /t REG_DWORD /d 1 /f | Out-Null

# ── 5. SCREEN SAVER / SLEEP ──────────────────────────────────
Write-Host "Disabilito screensaver e sleep..." -ForegroundColor Yellow
powercfg /change monitor-timeout-ac 0
powercfg /change monitor-timeout-dc 0
powercfg /change standby-timeout-ac 0
powercfg /change standby-timeout-dc 0
powercfg /change hibernate-timeout-ac 0
reg add "HKCU\Control Panel\Desktop" /v ScreenSaveActive /t REG_SZ /d 0 /f | Out-Null

# ── 6. PUNTATORE MOUSE ───────────────────────────────────────
Write-Host "Nascondo cursore mouse..." -ForegroundColor Yellow
# Cursore invisibile tramite registro
$CursorPath = "HKCU\Control Panel\Cursors"
reg add $CursorPath /v "" /t REG_SZ /d "" /f | Out-Null
reg add $CursorPath /v "Arrow" /t REG_SZ /d "" /f | Out-Null
reg add $CursorPath /v "Hand" /t REG_SZ /d "" /f | Out-Null
reg add $CursorPath /v "Wait" /t REG_SZ /d "" /f | Out-Null

# ── 7. BLUETOOTH POPUP ───────────────────────────────────────
Write-Host "Disabilito popup Bluetooth..." -ForegroundColor Yellow
reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Bluetooth" /v "AllowDiscoverability" /t REG_DWORD /d 0 /f | Out-Null
# Disabilita servizio Bluetooth se non serve
$btService = Get-Service -Name "bthserv" -ErrorAction SilentlyContinue
if ($btService) {
    Stop-Service -Name "bthserv" -Force -ErrorAction SilentlyContinue
    Set-Service -Name "bthserv" -StartupType Disabled -ErrorAction SilentlyContinue
    Write-Host "  Servizio Bluetooth disabilitato" -ForegroundColor Gray
}

# ── 8. POPUP RETE / WIFI ─────────────────────────────────────
Write-Host "Disabilito notifiche rete..." -ForegroundColor Yellow
reg add "HKLM\SYSTEM\CurrentControlSet\Services\NlaSvc\Parameters\Internet" /v EnableActiveProbing /t REG_DWORD /d 0 /f | Out-Null

# ── 9. TASKBAR NASCOSTA ──────────────────────────────────────
Write-Host "Nascondo taskbar..." -ForegroundColor Yellow
$taskbarSettings = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StuckRects3"
# Auto-hide taskbar
$regValue = (Get-ItemProperty -Path "Registry::$taskbarSettings" -Name Settings -ErrorAction SilentlyContinue).Settings
if ($regValue) {
    $regValue[8] = 3  # auto-hide
    Set-ItemProperty -Path "Registry::$taskbarSettings" -Name Settings -Value $regValue
}

# ── 10. ERRORI E CRASH DIALOG ────────────────────────────────
Write-Host "Disabilito dialog di errore..." -ForegroundColor Yellow
reg add "HKLM\SOFTWARE\Microsoft\Windows\Windows Error Reporting" /v Disabled /t REG_DWORD /d 1 /f | Out-Null
reg add "HKCU\SOFTWARE\Microsoft\Windows\Windows Error Reporting" /v DontShowUI /t REG_DWORD /d 1 /f | Out-Null

# ── 11. WINDOWS DEFENDER NOTIFICHE ──────────────────────────
Write-Host "Disabilito notifiche Defender..." -ForegroundColor Yellow
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender Security Center\Notifications" /v DisableNotifications /t REG_DWORD /d 1 /f | Out-Null

# ── 12. ACCESSO RAPIDO / ONEDRIVE ────────────────────────────
# Disabilita OneDrive (popup fastidiosi)
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\OneDrive" /v DisableFileSyncNGSC /t REG_DWORD /d 1 /f | Out-Null

# ── 13. UAC ──────────────────────────────────────────────────
Write-Host "Abbasso UAC..." -ForegroundColor Yellow
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" /v ConsentPromptBehaviorAdmin /t REG_DWORD /d 0 /f | Out-Null

# ── 14. AUTOAVVIO PIXELBRIDGE ────────────────────────────────
Write-Host "Configuro autoavvio PixelBridge..." -ForegroundColor Yellow
$exePath = "$PSScriptRoot\PixelBridge.exe"
if (Test-Path $exePath) {
    $regRun = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Run"
    reg add $regRun /v "PixelBridge" /t REG_SZ /d "`"$exePath`"" /f | Out-Null
    Write-Host "  PixelBridge registrato per autoavvio" -ForegroundColor Green
} else {
    Write-Host "  PixelBridge.exe non trovato nella stessa cartella — autoavvio non configurato" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== Setup completato! ===" -ForegroundColor Green
Write-Host "Riavvia il PC per applicare tutte le modifiche." -ForegroundColor White
Write-Host ""
pause
