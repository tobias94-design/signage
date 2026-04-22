#!/usr/bin/env python3
"""
PixelBridge Agent - Windows v1.1.0
Pairing + heartbeat + cache locale + server HTTP locale.
Chrome viene lanciato dal bat.
"""

import os, sys, json, time, random, socket, subprocess, threading
import urllib.request, urllib.parse, shutil, tempfile
import ssl, http.server, socketserver
ssl._create_default_https_context = ssl._create_unverified_context

VERSION        = '1.1.0'
if getattr(sys, 'frozen', False):
    BASE_DIR = os.path.dirname(os.path.abspath(sys.executable))
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))

CONFIG_FILE    = os.path.join(BASE_DIR, 'config.json')
CACHE_DIR      = os.path.join(BASE_DIR, 'cache')
SERVER_URL     = 'https://pixelbridge.it'
PING_INTERVAL  = 60
CACHE_INTERVAL = 300
LOCAL_PORT     = 8765
PAIRING_TIMEOUT = 600
APP_NAME       = 'PixelBridge'

os.makedirs(CACHE_DIR, exist_ok=True)

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
        with urllib.request.urlopen(req, timeout=15) as r:
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

# ── SERVER HTTP LOCALE PER CACHE ─────────────────────────────
class CacheHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=CACHE_DIR, **kwargs)
    def log_message(self, format, *args):
        pass  # silenzioso

def avvia_server_locale():
    try:
        socketserver.TCPServer.allow_reuse_address = True
        httpd = socketserver.TCPServer(('127.0.0.1', LOCAL_PORT), CacheHandler)
        log(f"Server cache locale avviato su http://127.0.0.1:{LOCAL_PORT}")
        httpd.serve_forever()
    except Exception as e:
        log(f"Server locale errore: {e}")

# ── CACHE LOCALE ──────────────────────────────────────────────
def aggiorna_cache(token):
    log("Cache: controllo contenuti da scaricare...")
    res = api_get(f'/api/playlist_cache.php?token={urllib.parse.quote(token)}')

    # FIX: se API fallisce NON cancellare la cache esistente
    if not res.get('ok'):
        log(f"Cache: API non raggiungibile — cache locale mantenuta")
        return

    files_server = res.get('files', [])
    nomi_server  = {f['file'] for f in files_server}

    # Cancella file non più in playlist (solo se API OK)
    for fname in os.listdir(CACHE_DIR):
        if fname not in nomi_server:
            try:
                os.remove(os.path.join(CACHE_DIR, fname))
                log(f"Cache: rimosso {fname}")
            except: pass

    # Scarica file mancanti con verifica dimensione
    for f in files_server:
        dest = os.path.join(CACHE_DIR, f['file'])
        if os.path.exists(dest) and os.path.getsize(dest) > 0:
            continue
        url = SERVER_URL + f['url']
        log(f"Cache: scarico {f['nome']}...")
        tmp_dest = dest + '.tmp'
        try:
            req = urllib.request.Request(url, headers={'User-Agent': f'PixelBridge-Agent/{VERSION}'})
            with urllib.request.urlopen(req, timeout=120) as r:
                content_length = r.headers.get('Content-Length')
                with open(tmp_dest, 'wb') as out:
                    shutil.copyfileobj(r, out)

            downloaded_size = os.path.getsize(tmp_dest)

            # FIX: verifica dimensione
            if content_length and downloaded_size != int(content_length):
                log(f"Cache: {f['nome']} dimensione errata — riprovo dopo")
                os.remove(tmp_dest)
                continue

            if downloaded_size == 0:
                log(f"Cache: {f['nome']} file vuoto — scarto")
                os.remove(tmp_dest)
                continue

            shutil.move(tmp_dest, dest)
            log(f"Cache: {f['nome']} OK ({downloaded_size} bytes)")

        except Exception as e:
            log(f"Cache: errore download {f['nome']} — {e}")
            try:
                if os.path.exists(tmp_dest): os.remove(tmp_dest)
            except: pass

def cache_loop(token):
    try: aggiorna_cache(token)
    except Exception as e: log(f"Cache errore: {e}")
    while True:
        time.sleep(CACHE_INTERVAL)
        try: aggiorna_cache(token)
        except Exception as e: log(f"Cache errore: {e}")

# ── AUTO-UPDATE ───────────────────────────────────────────────
def controlla_aggiornamento():
    try:
        res = api_get('/api/version.php')
        if not res.get('version'): return False
        server_ver = res['version'].strip()
        log(f"Versione locale: {VERSION} | Server: {server_ver}")
        if server_ver == VERSION: return False
        # FIX: non scaricare versioni più vecchie
        try:
            local_parts  = [int(x) for x in VERSION.split('.')]
            server_parts = [int(x) for x in server_ver.split('.')]
            if server_parts <= local_parts:
                log("Versione server non più recente — skip")
                return False
        except: pass
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

# FIX: do_pairing iterativo invece di ricorsivo (evita stack overflow)
def do_pairing():
    while True:
        code    = str(random.randint(100000, 999999))
        machine = get_machine_name()
        log(f"Pairing: codice {code} per {machine}")
        res = api_get(f'/api/claim.php?action=register&code={code}&machine={urllib.parse.quote(machine)}')
        if not res.get('ok'):
            log(f"Errore registrazione — riprovo tra 30s")
            time.sleep(30)
            continue

        threading.Thread(target=show_screen, kwargs={'code': code}, daemon=True).start()

        deadline = time.time() + PAIRING_TIMEOUT
        paired_token = None
        while time.time() < deadline:
            time.sleep(5)
            res = api_get(f'/api/claim.php?action=check&code={code}')
            if res.get('status') == 'claimed' and res.get('token'):
                paired_token = res['token']
                log(f"Pairing OK! Token: {paired_token}")
                close_screen()
                cfg = load_config()
                cfg.update({'token': paired_token, 'paired_at': time.strftime('%Y-%m-%d %H:%M:%S'), 'machine': machine})
                save_config(cfg)
                break
            elif res.get('status') == 'expired':
                break

        close_screen()
        if paired_token:
            return paired_token
        log("Pairing scaduto — riprovo")

def ping_loop(token):
    while True:
        time.sleep(PING_INTERVAL)
        try: ping_server(token)
        except: pass

def main():
    log(f"=== PixelBridge Agent v{VERSION} ===")
    log(f"Server: {SERVER_URL} | Macchina: {get_machine_name()}")
    log(f"Cache dir: {CACHE_DIR}")

    controlla_aggiornamento()

    cfg   = load_config()
    token = cfg.get('token')
    if not token:
        log("Nessun token — avvio pairing")
        token = do_pairing()

    log(f"Token: {token} — avvio servizi")

    token_file = os.path.join(BASE_DIR, 'token.txt')
    with open(token_file, 'w') as f:
        f.write(token)
    log("Token salvato in token.txt")

    # Server HTTP locale per servire cache a Chrome
    threading.Thread(target=avvia_server_locale, daemon=True).start()

    # Cache in background
    threading.Thread(target=cache_loop, args=(token,), daemon=True).start()

    # Heartbeat
    ping_loop(token)

if __name__ == '__main__':
    while True:
        try:
            main()
        except Exception as e:
            log(f"CRASH: {e} — riavvio tra 10s")
            time.sleep(10)
