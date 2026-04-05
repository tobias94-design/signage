@echo off
setlocal enabledelayedexpansion
echo.
echo  Avvio installazione...
echo.

:: Verifica admin
net session > nul 2>&1
if %errorlevel% neq 0 (
    echo  ERRORE: devi eseguire come Amministratore!
    echo  Tasto destro - "Esegui come amministratore"
    echo.
    pause
    exit /b 1
)

cd /d "%~dp0"

echo  PixelBridge Kiosk - Installazione
echo  ====================================
echo.

:: ── TROVA AGENT.PY ──────────────────────────────────────────
if not exist "%~dp0agent.py" (
    echo  ERRORE: agent.py non trovato in %~dp0
    echo  Assicurati che installa-kiosk.bat sia nella cartella PixelBridge.
    echo.
    pause
    exit /b 1
)
echo  [OK] agent.py trovato

:: ── TROVA PYTHON ────────────────────────────────────────────
python --version > nul 2>&1
if %errorlevel% neq 0 (
    echo  ERRORE: Python non installato!
    echo  Scaricalo da https://python.org
    echo.
    pause
    exit /b 1
)
echo  [OK] Python trovato

:: ── TASK SCHEDULER ──────────────────────────────────────────
echo.
echo  Configurazione avvio automatico...

set "WATCHDOG_BAT=%~dp0pixelbridge-kiosk.bat"
set "XML_PATH=%TEMP%\pb_task.xml"

(
echo ^<?xml version="1.0" encoding="UTF-16"?^>
echo ^<Task version="1.4" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task"^>
echo   ^<RegistrationInfo^>
echo     ^<Description^>PixelBridge Digital Signage^</Description^>
echo   ^</RegistrationInfo^>
echo   ^<Triggers^>
echo     ^<LogonTrigger^>
echo       ^<Enabled^>true^</Enabled^>
echo       ^<UserId^>%COMPUTERNAME%\%USERNAME%^</UserId^>
echo       ^<Delay^>PT8S^</Delay^>
echo     ^</LogonTrigger^>
echo   ^</Triggers^>
echo   ^<Principals^>
echo     ^<Principal id="Author"^>
echo       ^<LogonType^>InteractiveToken^</LogonType^>
echo       ^<RunLevel^>HighestAvailable^</RunLevel^>
echo     ^</Principal^>
echo   ^</Principals^>
echo   ^<Settings^>
echo     ^<MultipleInstancesPolicy^>IgnoreNew^</MultipleInstancesPolicy^>
echo     ^<DisallowStartIfOnBatteries^>false^</DisallowStartIfOnBatteries^>
echo     ^<StopIfGoingOnBatteries^>false^</StopIfGoingOnBatteries^>
echo     ^<AllowHardTerminate^>false^</AllowHardTerminate^>
echo     ^<StartWhenAvailable^>true^</StartWhenAvailable^>
echo     ^<RunOnlyIfNetworkAvailable^>false^</RunOnlyIfNetworkAvailable^>
echo     ^<ExecutionTimeLimit^>PT0S^</ExecutionTimeLimit^>
echo     ^<Enabled^>true^</Enabled^>
echo     ^<RestartOnFailure^>
echo       ^<Interval^>PT1M^</Interval^>
echo       ^<Count^>999^</Count^>
echo     ^</RestartOnFailure^>
echo   ^</Settings^>
echo   ^<Actions Context="Author"^>
echo     ^<Exec^>
echo       ^<Command^>cmd.exe^</Command^>
echo       ^<Arguments^>/c "%WATCHDOG_BAT%"^</Arguments^>
echo       ^<WorkingDirectory^>%~dp0^</WorkingDirectory^>
echo     ^</Exec^>
echo   ^</Actions^>
echo ^</Task^>
) > "%XML_PATH%"

schtasks /delete /tn "PixelBridge" /f > nul 2>&1
schtasks /create /tn "PixelBridge" /xml "%XML_PATH%"
if %errorlevel% equ 0 (
    echo  [OK] Task Scheduler configurato
) else (
    echo  [FALLBACK] Uso registro...
    reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Run" /v "PixelBridge" /t REG_SZ /d "cmd.exe /c \"%WATCHDOG_BAT%\"" /f > nul
    echo  [OK] Autoavvio configurato via registro
)
del "%XML_PATH%" > nul 2>&1

:: ── AUTO-LOGIN ──────────────────────────────────────────────
echo.
echo  Per far partire il display dopo un blackout il PC
echo  deve loggarsi in automatico senza cliccare nulla.
echo.
set /p AUTOLOGIN= Configurare auto-login per "%USERNAME%"? (s/n): 

if /i "!AUTOLOGIN!"=="s" (
    set /p PWD= Password di "%USERNAME%" (invio se senza password): 
    reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" /v AutoAdminLogon /t REG_SZ /d "1" /f > nul
    reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" /v DefaultUserName /t REG_SZ /d "%USERNAME%" /f > nul
    reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" /v DefaultDomainName /t REG_SZ /d "%COMPUTERNAME%" /f > nul
    reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" /v DefaultPassword /t REG_SZ /d "!PWD!" /f > nul
    echo  [OK] Auto-login configurato per: %USERNAME%
)

:: ── IMPOSTAZIONI KIOSK ──────────────────────────────────────
echo.
echo  Impostazioni kiosk Windows...

powercfg /change monitor-timeout-ac 0 > nul 2>&1
powercfg /change monitor-timeout-dc 0 > nul 2>&1
powercfg /change standby-timeout-ac 0 > nul 2>&1
powercfg /change standby-timeout-dc 0 > nul 2>&1
powercfg /change hibernate-timeout-ac 0 > nul 2>&1
reg add "HKCU\Control Panel\Desktop" /v ScreenSaveActive /t REG_SZ /d "0" /f > nul 2>&1
echo  [OK] Sleep disabilitato

reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\PushNotifications" /v ToastEnabled /t REG_DWORD /d 0 /f > nul 2>&1
reg add "HKCU\SOFTWARE\Policies\Microsoft\Windows\Explorer" /v DisableNotificationCenter /t REG_DWORD /d 1 /f > nul 2>&1
echo  [OK] Notifiche disabilitate

reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" /v NoAutoUpdate /t REG_DWORD /d 1 /f > nul 2>&1
echo  [OK] Aggiornamenti automatici disabilitati

reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\Personalization" /v NoLockScreen /t REG_DWORD /d 1 /f > nul 2>&1
echo  [OK] Schermata di blocco disabilitata

reg add "HKLM\SOFTWARE\Microsoft\Windows\Windows Error Reporting" /v Disabled /t REG_DWORD /d 1 /f > nul 2>&1
reg add "HKCU\SOFTWARE\Microsoft\Windows\Windows Error Reporting" /v DontShowUI /t REG_DWORD /d 1 /f > nul 2>&1
echo  [OK] Dialog di errore disabilitati

reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\OneDrive" /v DisableFileSyncNGSC /t REG_DWORD /d 1 /f > nul 2>&1
echo  [OK] OneDrive disabilitato

:: ── FINE ────────────────────────────────────────────────────
echo.
echo  ====================================
echo   INSTALLAZIONE COMPLETATA!
echo  ====================================
echo.
echo  Prossimi passi:
echo  1. Riavvia il PC
echo  2. Al riavvio si apre automaticamente la schermata
echo     di pairing di PixelBridge
echo  3. Inserisci il codice nel pannello web
echo  4. Da quel momento funziona per sempre in automatico
echo.
echo  BIOS: imposta "Power On After Power Loss" = Power On
echo  per il riavvio automatico dopo blackout.
echo.
pause
