@echo off
REM PixelBridge Agent Launcher - Avvio Nascosto
REM Lancia l'agent Python completamente invisibile

cd /d "%~dp0"

REM Verifica Python
python --version >nul 2>&1
if errorlevel 1 (
    msg * "ERRORE: Python non installato! Scaricalo da python.org"
    exit /b 1
)

REM Lancia agent.py COMPLETAMENTE NASCOSTO con pythonw (no console)
start "" /B pythonw agent.py

REM Crea un VBS per mostrare notifica (opzionale)
echo Set objShell = CreateObject("WScript.Shell") > "%TEMP%\pb_start.vbs"
echo objShell.Popup "PixelBridge Agent avviato", 3, "PixelBridge", 64 >> "%TEMP%\pb_start.vbs"
wscript //nologo "%TEMP%\pb_start.vbs"
del "%TEMP%\pb_start.vbs"

exit