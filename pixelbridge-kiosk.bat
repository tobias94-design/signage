@echo off
setlocal enabledelayedexpansion

:: ============================================================
::  PixelBridge Kiosk - Watchdog
::  Avvia agent.py e lo riavvia se crasha
:: ============================================================

cd /d "%~dp0"

echo  PixelBridge Watchdog avviato > "%~dp0kiosk.log"

:: Verifica agent.py
if not exist "%~dp0agent.py" (
    echo  ERRORE: agent.py non trovato >> "%~dp0kiosk.log"
    exit /b 1
)

:: Attendi rete (8 secondi al boot)
timeout /t 8 /nobreak > nul

set "CICLO=0"

:LOOP
set /a CICLO+=1
echo  [Ciclo !CICLO!] Avvio agent.py >> "%~dp0kiosk.log"

:: Chiudi eventuali istanze precedenti
taskkill /f /im pythonw.exe > nul 2>&1
taskkill /f /im chrome.exe > nul 2>&1
timeout /t 2 /nobreak > nul

:: Avvia agent.py e aspetta che finisca
python "%~dp0agent.py"

echo  [Ciclo !CICLO!] agent.py terminato. Riavvio tra 5s... >> "%~dp0kiosk.log"
timeout /t 5 /nobreak > nul
goto LOOP
