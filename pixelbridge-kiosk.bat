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
set "DISPLAY_URL=http://204.168.161.116/player/display.php?token=!TOKEN!"
echo [OK] Token: !TOKEN! >> "%~dp0kiosk.log"
echo [OK] URL: !DISPLAY_URL! >> "%~dp0kiosk.log"

:: ── LOOP WATCHDOG CHROME ────────────────────────────────────
:CHROME_LOOP
echo [AVVIO] Chrome kiosk... >> "%~dp0kiosk.log"

taskkill /f /im chrome.exe > nul 2>&1
timeout /t 2 /nobreak > nul

start "" /wait "!CHROME!" --kiosk "!DISPLAY_URL!" --no-first-run --disable-infobars --autoplay-policy=no-user-gesture-required --user-data-dir="%~dp0chrome_profile"

echo [CRASH] Chrome terminato, riavvio tra 5s >> "%~dp0kiosk.log"
timeout /t 5 /nobreak > nul
goto CHROME_LOOP
