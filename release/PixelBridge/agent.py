#!/usr/bin/env python3
"""
PixelBridge Agent - Windows
Pairing automatico + lancia Chrome kiosk + heartbeat + auto-update
"""

import os, sys, json, time, random, socket, subprocess, threading
import urllib.request, urllib.parse, shutil, tempfile

# ── CONFIGURAZIONE ────────────────────────────────────────────
VERSION     = '1.0.4'
# Determina directory exe/script
if getattr(sys, 'frozen', False):
    BASE_DIR = os.path.dirname(os.path.abspath(sys.executable))
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))

CONFIG_FILE = os.path.join(BASE_DIR, 'config.json')
SERVER_URL  = 'http://204.168.161.116'
PING_INTERVAL   = 60
PAIRING_TIMEOUT = 600
CHROME_PATHS = [
    r'C:\Program Files\Google\Chrome\Application\chrome.exe',
    r'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
]
APP_NAME = 'PixelBridge'

# ── UTILITY ───────────────────────────────────────────────────
def load_config():
    if os.path.exists(CONFIG_FILE):
        with open(CONFIG_FILE, 'r') as f:
            return json.load(f)
    return {}

def save_config(cfg):
    with open(CONFIG_FILE, 'w') as f:
        json.dump(cfg, f, indent=2)

def api_get(path):
    try:
        url = SERVER_URL + path
        req = urllib.request.Request(url, headers={'User-Agent': f'PixelBridge-Agent/{VERSION}'})
        with urllib.request.urlopen(req, timeout=10) as r:
            return json.loads(r.read().decode())
    except Exception as e:
        return {'ok': False, 'error': str(e)}

def get_machine_name():
    try:    return socket.gethostname()
    except: return 'PC-SCONOSCIUTO'

def find_chrome():
    for path in CHROME_PATHS:
        if os.path.exists(path):
            return path
    return None

def log(msg):
    ts = time.strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{ts}] {msg}"
    print(line)
    try:
        log_file = os.path.join(os.path.dirname(CONFIG_FILE), 'agent.log')
        with open(log_file, 'a', encoding='utf-8') as f:
            f.write(line + '\n')
    except: pass

# ── AUTO-UPDATE ───────────────────────────────────────────────
def controlla_aggiornamento():
    """Controlla se esiste una versione più recente e si aggiorna da solo"""
    try:
        res = api_get('/api/version.php')
        if not res.get('version'):
            return False

        server_ver = res['version'].strip()
        log(f"Versione locale: {VERSION} | Server: {server_ver}")

        if server_ver == VERSION:
            return False  # già aggiornato

        download_url = res.get('download', '')
        if not download_url:
            log("Nessun URL di download disponibile")
            return False

        log(f"Aggiornamento disponibile: {server_ver} — scarico da {download_url}")

        # Scarica il nuovo exe in una cartella temp
        exe_path = os.path.abspath(sys.executable if getattr(sys, 'frozen', False) else __file__)
        tmp_path = exe_path + '.new'

        req = urllib.request.Request(download_url, headers={'User-Agent': f'PixelBridge-Agent/{VERSION}'})
        with urllib.request.urlopen(req, timeout=120) as r, open(tmp_path, 'wb') as f:
            shutil.copyfileobj(r, f)

        log("Download completato — preparo sostituzione")

        # Script batch per sostituire l'exe e rilanciare
        bat = os.path.join(tempfile.gettempdir(), 'pb_update.bat')
        with open(bat, 'w') as f:
            f.write(f'''@echo off
timeout /t 3 /nobreak >nul
move /y "{tmp_path}" "{exe_path}"
start "" "{exe_path}"
del "%~f0"
''')
        subprocess.Popen(['cmd', '/c', bat], creationflags=subprocess.CREATE_NO_WINDOW)
        log("Riavvio per aggiornamento...")
        sys.exit(0)

    except Exception as e:
        log(f"Errore auto-update: {e}")
        return False

# ── AUTOSTART AL BOOT ─────────────────────────────────────────
def installa_autostart():
    try:
        import winreg
        exe_path = os.path.abspath(sys.executable if getattr(sys, 'frozen', False) else __file__)
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER,
                             r'Software\Microsoft\Windows\CurrentVersion\Run',
                             0, winreg.KEY_SET_VALUE)
        winreg.SetValueEx(key, APP_NAME, 0, winreg.REG_SZ, f'"{exe_path}"')
        winreg.CloseKey(key)
        log("Autostart registrato")
    except Exception as e:
        log(f"Autostart: {e}")

# ── CHROME ────────────────────────────────────────────────────
def launch_chrome(token):
    chrome = find_chrome()
    if not chrome:
        log("Chrome non trovato!")
        return None
    player_url = f"{SERVER_URL}/player/display.php?token={token}"
    args = [
        chrome,
        '--kiosk',
        '--no-first-run',
        '--disable-infobars',
        '--disable-session-crashed-bubble',
        '--autoplay-policy=no-user-gesture-required',
        '--disable-features=TranslateUI',
        '--disable-notifications',
        '--disable-popup-blocking',
        '--hide-crash-restore-bubble',
        f'--user-data-dir={os.path.join(os.path.dirname(CONFIG_FILE), "chrome_profile")}',
        player_url
    ]
    proc = subprocess.Popen(args)
    # Chiudi finestre Esplora Risorse aperte
    try:
        import time
        time.sleep(2)
        subprocess.Popen(
            ['powershell', '-WindowStyle', 'Hidden', '-Command',
             '$shell = New-Object -ComObject Shell.Application; $shell.Windows() | ForEach-Object { $_.Quit() }'],
            creationflags=0x08000000  # CREATE_NO_WINDOW
        )
    except Exception:
        pass
    return proc

def ping_server(token):
    api_get(f'/api/stato.php?token={urllib.parse.quote(token)}&t={int(time.time())}')

# ── SCHERMATA PAIRING ─────────────────────────────────────────
_pairing_window = None

def show_screen(message='', code=None):
    try:
        import tkinter as tk
        global _pairing_window
        if _pairing_window:
            try: _pairing_window.destroy()
            except: pass
        root = tk.Tk()
        _pairing_window = root
        root.title('PixelBridge')
        root.configure(bg='#0a0a0a')
        root.attributes('-fullscreen', True)
        root.attributes('-topmost', True)
        frame = tk.Frame(root, bg='#0a0a0a')
        frame.place(relx=0.5, rely=0.5, anchor='center')
        tk.Label(frame, text='⬡ PIXELBRIDGE', font=('Segoe UI', 18, 'bold'),
                 fg='#e85002', bg='#0a0a0a').pack(pady=(0, 40))
        if code:
            tk.Label(frame, text='Codice di pairing', font=('Segoe UI', 20),
                     fg='#888888', bg='#0a0a0a').pack()
            tk.Label(frame, text=code, font=('Courier New', 80, 'bold'),
                     fg='#ffffff', bg='#0a0a0a').pack(pady=20)
            tk.Label(frame, text='Inserisci questo codice nel pannello admin',
                     font=('Segoe UI', 16), fg='#555555', bg='#0a0a0a').pack()
            tk.Label(frame, text=f'Scade tra 10 minuti  ·  {get_machine_name()}  ·  v{VERSION}',
                     font=('Segoe UI', 12), fg='#333333', bg='#0a0a0a').pack(pady=(10,0))
        else:
            tk.Label(frame, text=message, font=('Segoe UI', 22),
                     fg='#ffffff', bg='#0a0a0a', justify='center').pack()
        root.mainloop()
    except ImportError:
        log(f"SCHERMO: {message} | CODICE: {code}")

def close_screen():
    global _pairing_window
    if _pairing_window:
        try: _pairing_window.quit()
        except: pass
        _pairing_window = None

# ── PAIRING ───────────────────────────────────────────────────
def do_pairing():
    code    = str(random.randint(100000, 999999))
    machine = get_machine_name()
    log(f"Pairing: codice {code} per {machine}")

    res = api_get(f'/api/claim.php?action=register&code={code}&machine={urllib.parse.quote(machine)}')
    if not res.get('ok'):
        log(f"Errore registrazione: {res.get('error','')} — riprovo tra 30s")
        time.sleep(30)
        return do_pairing()

    t = threading.Thread(target=show_screen, kwargs={'code': code}, daemon=True)
    t.start()

    deadline = time.time() + PAIRING_TIMEOUT
    while time.time() < deadline:
        time.sleep(5)
        res = api_get(f'/api/claim.php?action=check&code={code}')
        if res.get('status') == 'claimed' and res.get('token'):
            token = res['token']
            log(f"Pairing OK! Token: {token}")
            close_screen()
            cfg = load_config()
            cfg['token']     = token
            cfg['paired_at'] = time.strftime('%Y-%m-%d %H:%M:%S')
            cfg['machine']   = machine
            save_config(cfg)
            return token
        elif res.get('status') == 'expired':
            close_screen()
            return do_pairing()

    close_screen()
    return do_pairing()

# ── WATCHDOG CHROME + PING ────────────────────────────────────
def run_player(token):
    log(f"Avvio player — token: {token}")

    # Ping ogni 60s in background
    def ping_loop():
        while True:
            time.sleep(PING_INTERVAL)
            try: ping_server(token)
            except: pass
    threading.Thread(target=ping_loop, daemon=True).start()

    # Controllo aggiornamenti ogni ora
    def update_loop():
        while True:
            time.sleep(3600)
            try: controlla_aggiornamento()
            except: pass
    threading.Thread(target=update_loop, daemon=True).start()

    # Watchdog Chrome — rilancia se crasha
    while True:
        log("Lancio Chrome kiosk...")
        proc = launch_chrome(token)
        if proc:
            proc.wait()
            log("Chrome terminato — rilancio tra 5s")
            time.sleep(5)
        else:
            time.sleep(30)

# ── SETUP KIOSK (primo avvio) ─────────────────────────────────
def setup_kiosk_se_necessario():
    """Esegue il setup kiosk una sola volta al primo avvio."""
    cfg = load_config()
    if cfg.get('kiosk_setup_done'):
        return

    log("Primo avvio — eseguo setup kiosk...")

    # Script PowerShell inline (stesse operazioni di setup_kiosk.ps1)
    ps_script = r"""
# Aggiornamenti automatici
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" /v NoAutoUpdate /t REG_DWORD /d 1 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" /v AUOptions /t REG_DWORD /d 1 /f | Out-Null

# Notifiche
reg add "HKCU\SOFTWARE\Policies\Microsoft\Windows\Explorer" /v DisableNotificationCenter /t REG_DWORD /d 1 /f | Out-Null
reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\PushNotifications" /v ToastEnabled /t REG_DWORD /d 0 /f | Out-Null

# Schermata di blocco
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\Personalization" /v NoLockScreen /t REG_DWORD /d 1 /f | Out-Null

# Sleep e screensaver
powercfg /change monitor-timeout-ac 0
powercfg /change monitor-timeout-dc 0
powercfg /change standby-timeout-ac 0
powercfg /change standby-timeout-dc 0
powercfg /change hibernate-timeout-ac 0
reg add "HKCU\Control Panel\Desktop" /v ScreenSaveActive /t REG_SZ /d 0 /f | Out-Null

# Cursore invisibile
$cp = "HKCU\Control Panel\Cursors"
foreach ($cur in @("","Arrow","Hand","Wait","IBeam","SizeAll","SizeNESW","SizeNS","SizeNWSE","SizeWE","UpArrow","Crosshair","No","AppStarting")) {
    reg add $cp /v $cur /t REG_SZ /d "" /f | Out-Null
}
$code = @"
[DllImport("user32.dll")] public static extern bool SystemParametersInfo(int a, int b, string c, int d);
"@
Add-Type -MemberDefinition $code -Name CF -Namespace Win32
[Win32.CF]::SystemParametersInfo(0x0057, 0, $null, 3) | Out-Null

# Taskbar auto-hide
$ts = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StuckRects3"
$v = (Get-ItemProperty -Path "Registry::$ts" -Name Settings -ErrorAction SilentlyContinue).Settings
if ($v) { $v[8] = 3; Set-ItemProperty -Path "Registry::$ts" -Name Settings -Value $v }

# Errori e crash
reg add "HKLM\SOFTWARE\Microsoft\Windows\Windows Error Reporting" /v Disabled /t REG_DWORD /d 1 /f | Out-Null
reg add "HKCU\SOFTWARE\Microsoft\Windows\Windows Error Reporting" /v DontShowUI /t REG_DWORD /d 1 /f | Out-Null

# Defender notifiche
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender Security Center\Notifications" /v DisableNotifications /t REG_DWORD /d 1 /f | Out-Null

# OneDrive
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\OneDrive" /v DisableFileSyncNGSC /t REG_DWORD /d 1 /f | Out-Null

# UAC
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" /v ConsentPromptBehaviorAdmin /t REG_DWORD /d 0 /f | Out-Null

Write-Host "Setup kiosk completato"
"""

    try:
        import tempfile
        ps_file = os.path.join(tempfile.gettempdir(), 'pb_kiosk_setup.ps1')
        with open(ps_file, 'w', encoding='utf-8') as f:
            f.write(ps_script)

        result = subprocess.run(
            ['powershell', '-ExecutionPolicy', 'Bypass', '-WindowStyle', 'Hidden',
             '-File', ps_file],
            capture_output=True, text=True, timeout=60
        )
        if result.returncode == 0:
            log("Setup kiosk completato con successo")
            cfg['kiosk_setup_done'] = True
            save_config(cfg)
        else:
            log(f"Setup kiosk: {result.stderr[:200]}")
    except Exception as e:
        log(f"Setup kiosk errore: {e}")


# ── MAIN ──────────────────────────────────────────────────────
def main():
    log(f"=== PixelBridge Agent v{VERSION} ===")
    log(f"Server: {SERVER_URL} | Macchina: {get_machine_name()}")
    log(f"Config file: {CONFIG_FILE}")

    installa_autostart()
    setup_kiosk_se_necessario()  # solo al primo avvio
    controlla_aggiornamento()  # controlla subito all'avvio

    cfg   = load_config()
    token = cfg.get('token')
    
    log(f"Config caricato: {cfg}")
    log(f"Token trovato: {token if token else 'NESSUNO'}")

    if not token:
        log("Nessun token — avvio pairing")
        token = do_pairing()
        
        # Verifica che il token sia stato salvato
        cfg_check = load_config()
        if cfg_check.get('token') == token:
            log(f"✅ Token salvato correttamente: {token}")
        else:
            log(f"❌ ERRORE: Token NON salvato! File: {CONFIG_FILE}")

    if token:
        run_player(token)

if __name__ == '__main__':
    while True:
        try:
            main()
        except Exception as e:
            log(f"CRASH: {e} — riavvio tra 10s")
            time.sleep(10)
