@echo off
echo ==========================================
echo    SIGNAGE MANAGER - Avvio in corso...
echo ==========================================

:: Avvia MAMP
start "" "C:\MAMP\MAMP.exe"
echo MAMP avviato, attendo 5 secondi...
timeout /t 5 /nobreak > nul

:: Apri il player in Chrome a schermo intero
echo Apertura player...
start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" ^
  --kiosk ^
  --fullscreen ^
  --noerrdialogs ^
  --disable-infobars ^
  --disable-session-crashed-bubble ^
  --no-first-run ^
  "http://localhost:8888/player/?token=soave-85faa7"

echo Player avviato!