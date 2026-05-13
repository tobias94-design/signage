@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
echo [AVVIO] PixelBridge Kiosk >> "%~dp0kiosk.log"
:: ── TROVA CHROME ────────────────────────────────────────────
set "CHROME="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
)
if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
    set "CHROME=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
)
if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" (
    set "CHROME=%LocalAppData%\Google\Chrome\Application\chrome.exe"
)
if "!CHROME!"=="" (
    echo [ERRORE] Chrome non trovato >> "%~dp0kiosk.log"
    exit /b 1
)
:: ── AVVIA AGENT.PY IN BACKGROUND (solo pairing + heartbeat) ─
if exist "%~dp0agent.py" (
    start "" /B python "%~dp0agent.py"
)
:: ── ATTENDI RETE ────────────────────────────────────────────
timeout /t 10 /nobreak > nul
:: ── ATTENDI TOKEN ───────────────────────────────────────────
:WAIT_TOKEN
if not exist "%~dp0token.txt" (
    echo [ATTESA] Token non ancora disponibile... >> "%~dp0kiosk.log"
    timeout /t 3 /nobreak > nul
    goto WAIT_TOKEN
)
:: Leggi token dal file
set /p TOKEN=<"%~dp0token.txt"
set "DISPLAY_URL=https://pixelbridge.it/player/display.php?token=!TOKEN!"
echo [OK] Token: !TOKEN! >> "%~dp0kiosk.log"
echo [OK] URL: !DISPLAY_URL! >> "%~dp0kiosk.log"
:: ── NASCONDI TASKBAR ────────────────────────────────────────
powershell -WindowStyle Hidden -Command "$ts = 'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StuckRects3'; $v = (Get-ItemProperty -Path $ts).Settings; $v[8] = 3; Set-ItemProperty -Path $ts -Name Settings -Value $v; Stop-Process -Name explorer -Force" > nul 2>&1
::  ── LOOP WATCHDOG CHROME ────────────────────────────────────
:CHROME_LOOP
echo [AVVIO] Chrome kiosk... >> "%~dp0kiosk.log"
taskkill /f /im chrome.exe > nul 2>&1
timeout /t 2 /nobreak > nul
:: Pulizia lock Chrome per evitare crash profilo
del /f /q "C:\PixelBridge\chrome_profile\SingletonLock" 2>nul
del /f /q "C:\PixelBridge\chrome_profile\SingletonSocket" 2>nul
del /f /q "C:\PixelBridge\chrome_profile\SingletonCookie" 2>nul
start "" /wait "!CHROME!" --kiosk "!DISPLAY_URL!" ^
    --no-first-run ^
    --disable-infobars ^
    --autoplay-policy=no-user-gesture-required ^
    --user-data-dir="C:\PixelBridge\chrome_profile"
echo [CRASH] Chrome terminato, riavvio tra 5s >> "%~dp0kiosk.log"
timeout /t 5 /nobreak > nul
goto CHROME_LOOP
