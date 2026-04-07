#!/usr/bin/env python3
"""
PixelBridge Agent - Windows
Solo pairing + heartbeat. Chrome viene lanciato dal bat.
"""

import os, sys, json, time, random, socket, subprocess, threading
import urllib.request, urllib.parse, shutil, tempfile

VERSION     = '1.0.4'
if getattr(sys, 'frozen', False):
    BASE_DIR = os.path.dirname(os.path.abspath(sys.executable))
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))

CONFIG_FILE     = os.path.join(BASE_DIR, 'config.json')
SERVER_URL      = 'http://204.168.161.116'
PING_INTERVAL   = 60
PAIRING_TIMEOUT = 600
APP_NAME        = 'PixelBridge'

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

def log(msg):
    ts   = time.strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{ts}] {msg}"
    print(line)
    try:
        with open(os.path.join(BASE_DIR, 'agent.log'), 'a', encoding='utf-8') as f:
            f.write(line + '\n')
    except: pass

def controlla_aggiornamento():
    try:
        res = api_get('/api/version.php')
        if not res.get('version'): return False
        server_ver = res['version'].strip()
        log(f"Versione locale: {VERSION} | Server: {server_ver}")
        if server_ver == VERSION: return False
        download_url = res.get('download', '')
        if not download_url: return False
        log(f"Aggiornamento disponibile: {server_ver}")
        exe_path = os.path.abspath(sys.executable if getattr(sys, 'frozen', False) else __file__)
        tmp_path = exe_path + '.new'
        req = urllib.request.Request(download_url, headers={'User-Agent': f'PixelBridge-Agent/{VERSION}'})
        with urllib.request.urlopen(req, timeout=120) as r, open(tmp_path, 'wb') as f:
            shutil.copyfileobj(r, f)
        bat = os.path.join(tempfile.gettempdir(), 'pb_update.bat')
        with open(bat, 'w') as f:
            f.write(f'@echo off\ntimeout /t 3 /nobreak >nul\nmove /y "{tmp_path}" "{exe_path}"\nstart "" "{exe_path}"\ndel "%~f0"\n')
        subprocess.Popen(['cmd', '/c', bat], creationflags=subprocess.CREATE_NO_WINDOW)
        sys.exit(0)
    except Exception as e:
        log(f"Errore auto-update: {e}")
        return False

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

def ping_server(token):
    api_get(f'/api/stato.php?token={urllib.parse.quote(token)}&t={int(time.time())}')

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

def do_pairing():
    code    = str(random.randint(100000, 999999))
    machine = get_machine_name()
    log(f"Pairing: codice {code} per {machine}")
    res = api_get(f'/api/claim.php?action=register&code={code}&machine={urllib.parse.quote(machine)}')
    if not res.get('ok'):
        log(f"Errore registrazione — riprovo tra 30s")
        time.sleep(30)
        return do_pairing()
    threading.Thread(target=show_screen, kwargs={'code': code}, daemon=True).start()
    deadline = time.time() + PAIRING_TIMEOUT
    while time.time() < deadline:
        time.sleep(5)
        res = api_get(f'/api/claim.php?action=check&code={code}')
        if res.get('status') == 'claimed' and res.get('token'):
            token = res['token']
            log(f"Pairing OK! Token: {token}")
            close_screen()
            cfg = load_config()
            cfg.update({'token': token, 'paired_at': time.strftime('%Y-%m-%d %H:%M:%S'), 'machine': machine})
            save_config(cfg)
            return token
        elif res.get('status') == 'expired':
            close_screen()
            return do_pairing()
    close_screen()
    return do_pairing()

def ping_loop(token):
    while True:
        time.sleep(PING_INTERVAL)
        try: ping_server(token)
        except: pass

def main():
    log(f"=== PixelBridge Agent v{VERSION} ===")
    log(f"Server: {SERVER_URL} | Macchina: {get_machine_name()}")
    installa_autostart()
    controlla_aggiornamento()
    cfg   = load_config()
    token = cfg.get('token')
    if not token:
        log("Nessun token — avvio pairing")
        token = do_pairing()
    log(f"Token: {token} — avvio heartbeat")
    # Salva token su file txt per il bat
    token_file = os.path.join(BASE_DIR, 'token.txt')
    with open(token_file, 'w') as f:
        f.write(token)
    log(f"Token salvato in token.txt — Chrome viene lanciato dal bat")
    # Heartbeat loop
    ping_loop(token)

if __name__ == '__main__':
    while True:
        try:
            main()
        except Exception as e:
            log(f"CRASH: {e} — riavvio tra 10s")
            time.sleep(10)
