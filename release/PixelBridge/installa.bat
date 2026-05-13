@echo off
echo PixelBridge - Setup
echo.
python --version >nul 2>&1
if errorlevel 1 (
    echo ERRORE: Python non installato!
    pause
    exit /b 1
)
echo [OK] Python trovato
if not exist "C:\PixelBridge" mkdir "C:\PixelBridge"
copy /Y "%~dp0agent.py" "C:\PixelBridge\agent.py"
copy /Y "%~dp0avvia.bat" "C:\PixelBridge\avvia.bat"
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "PixelBridge" /t REG_SZ /d "C:\PixelBridge\avvia.bat" /f
powershell -Command "$ts='HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StuckRects3';$v=(Get-ItemProperty -Path Registry::$ts -Name Settings).Settings;$v[8]=3;Set-ItemProperty -Path Registry::$ts -Name Settings -Value $v;Stop-Process -Name explorer -Force"
echo.
echo FATTO!
cd C:\PixelBridge
start "" avvia.bat
pause
