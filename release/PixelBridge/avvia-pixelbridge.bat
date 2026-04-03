@echo off
cd /d "%~dp0"
python --version >nul 2>&1
if errorlevel 1 (
    start https://www.python.org/downloads/
    msg * "Installa Python e riprova!"
    exit /b 1
)
start "" /B pythonw agent.py
exit
