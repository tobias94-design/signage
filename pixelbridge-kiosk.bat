@echo off
setlocal enabledelayedexpansion

:: ============================================================
::  PixelBridge Kiosk - Watchdog
::  Avvia agent.py e lo riavvia se crasha
:: ============================================================

cd /d "%~dp0"

echo  PixelBridge Watchdog avviato > "%~dp0kiosk.log"

if not exist "%~dp0agent.py" (
    echo  ERRORE: agent.py non trovato >> "%~dp0kiosk.log"
    exit /b 1
)

:: Trova Chrome
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

:: Scrivi il percorso Chrome nel log
echo  Chrome: !CHROME! >> "%~dp0kiosk.log"

:: Aggiungi il flag HTTP sicuro al profilo Chrome (una tantum)
:: Serve per autorizzare getUserMedia su HTTP
if not "!CHROME!"=="" (
    reg add "HKLM\SOFTWARE\Policies\Google\Chrome\InsecureOriginPolicyBypassList" /v "1" /t REG_SZ /d "http://204.168.161.116" /f > nul 2>&1
)

:: Attendi rete
timeout /t 8 /nobreak > nul

set "CICLO=0"

:LOOP
set /a CICLO+=1
echo  [Ciclo !CICLO!] Avvio agent.py >> "%~dp0kiosk.log"

taskkill /f /im pythonw.exe > nul 2>&1
taskkill /f /im chrome.exe > nul 2>&1
timeout /t 2 /nobreak > nul

python "%~dp0agent.py"

echo  [Ciclo !CICLO!] agent.py terminato. Riavvio tra 5s... >> "%~dp0kiosk.log"
timeout /t 5 /nobreak > nul
goto LOOP
