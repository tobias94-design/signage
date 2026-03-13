#!/usr/bin/env python3
"""
PixelBridge Agent - Windows
Pairing automatico + lancia Chrome kiosk + heartbeat
"""

import os, sys, json, time, random, socket, subprocess, threading, urllib.request, urllib.parse

# ── CONFIGURAZIONE ────────────────────────────────────────────
CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'config.json')
SERVER_URL  = 'http://192.168.1.33:8888'   # <-- cambia con il tuo dominio
PING_INTERVAL   = 60    # secondi tra un ping e l'altro
PAIRING_TIMEOUT = 600   # 10 minuti per completare il pairing
CHROME_PATHS = [
    r'C:\Program Files\Google\Chrome\Application\chrome.exe',
    r'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
]

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
        req = urllib.request.Request(url, headers={'User-Agent': 'PixelBridge-Agent/1.0'})
        with urllib.request.urlopen(req, timeout=10) as r:
            return json.loads(r.read().decode())
    except Exception as e:
        return {'ok': False, 'error': str(e)}

def api_post(path, data):
    try:
        url  = SERVER_URL + path
        body = urllib.parse.urlencode(data).encode()
        req  = urllib.request.Request(url, data=body, headers={'User-Agent': 'PixelBridge-Agent/1.0'})
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

def launch_chrome(token):
    chrome = find_chrome()
    if not chrome:
        show_screen("❌ Chrome non trovato!\nInstalla Google Chrome e riavvia.")
        return None
    player_url = f"{SERVER_URL}/player/corsi.php?token={token}"
    args = [
        chrome,
        '--kiosk',
        '--no-first-run',
        '--disable-infobars',
        '--disable-session-crashed-bubble',
        '--autoplay-policy=no-user-gesture-required',
        '--disable-features=TranslateUI',
        f'--user-data-dir={os.path.join(os.path.dirname(CONFIG_FILE), "chrome_profile")}',
        player_url
    ]
    return subprocess.Popen(args)

def ping_server(token):
    """Manda heartbeat — stato.php lo gestisce già, ma aggiungiamo ping diretto"""
    api_get(f'/api/stato.php?token={urllib.parse.quote(token)}&t={int(time.time())}')

# ── SCHERMATA PAIRING (finestra tkinter minimalista) ──────────
_pairing_window = None

def show_screen(message, code=None):
    """Mostra una finestra fullscreen con il codice o messaggio"""
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
                     fg='#ffffff', bg='#0a0a0a', letter_spacing=10).pack(pady=20)
            tk.Label(frame, text='Inserisci questo codice nel pannello admin', font=('Segoe UI', 16),
                     fg='#555555', bg='#0a0a0a').pack()
            tk.Label(frame, text=f'Scade tra 10 minuti · {get_machine_name()}', font=('Segoe UI', 12),
                     fg='#333333', bg='#0a0a0a').pack(pady=(10,0))
        else:
            tk.Label(frame, text=message, font=('Segoe UI', 22),
                     fg='#ffffff', bg='#0a0a0a', justify='center').pack()

        root.mainloop()
    except ImportError:
        # tkinter non disponibile — scrivi su file di log
        log(f"SCHERMO: {message} | CODICE: {code}")

def close_screen():
    global _pairing_window
    if _pairing_window:
        try: _pairing_window.quit()
        except: pass
        _pairing_window = None

def log(msg):
    ts = time.strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{ts}] {msg}"
    print(line)
    try:
        log_file = os.path.join(os.path.dirname(CONFIG_FILE), 'agent.log')
        with open(log_file, 'a', encoding='utf-8') as f:
            f.write(line + '\n')
    except: pass

# ── PAIRING ───────────────────────────────────────────────────
def do_pairing():
    """Genera codice, lo mostra, aspetta che l'admin lo associ"""
    code = str(random.randint(100000, 999999))
    machine = get_machine_name()
    log(f"Avvio pairing con codice {code} per macchina {machine}")

    # Registra il codice sul server
    res = api_get(f'/api/claim.php?action=register&code={code}&machine={urllib.parse.quote(machine)}')
    if not res.get('ok'):
        log(f"Errore registrazione codice: {res.get('error','')}")
        # Ritenta dopo 30s
        time.sleep(30)
        return do_pairing()

    # Mostra schermata pairing in thread separato
    t = threading.Thread(target=show_screen, kwargs={'message':'', 'code':code}, daemon=True)
    t.start()

    # Polling ogni 5 secondi per 10 minuti
    deadline = time.time() + PAIRING_TIMEOUT
    while time.time() < deadline:
        time.sleep(5)
        res = api_get(f'/api/claim.php?action=check&code={code}')
        log(f"Pairing check: {res}")
        if res.get('status') == 'claimed' and res.get('token'):
            token = res['token']
            log(f"Pairing completato! Token: {token}")
            close_screen()
            cfg = load_config()
            cfg['token'] = token
            cfg['paired_at'] = time.strftime('%Y-%m-%d %H:%M:%S')
            cfg['machine'] = machine
            save_config(cfg)
            return token
        elif res.get('status') == 'expired':
            log("Codice scaduto, genero nuovo codice")
            close_screen()
            return do_pairing()

    log("Timeout pairing, genero nuovo codice")
    close_screen()
    return do_pairing()

# ── WATCHDOG CHROME ───────────────────────────────────────────
def run_player(token):
    """Lancia Chrome e lo rilancia se crasha"""
    log(f"Avvio player con token: {token}")

    # Ping thread
    def ping_loop():
        while True:
            time.sleep(PING_INTERVAL)
            try: ping_server(token)
            except: pass
    threading.Thread(target=ping_loop, daemon=True).start()

    # Watchdog Chrome
    while True:
        log("Lancio Chrome kiosk...")
        proc = launch_chrome(token)
        if proc:
            proc.wait()
            log("Chrome terminato, rilancio tra 5 secondi...")
            time.sleep(5)
        else:
            time.sleep(30)

# ── MAIN ──────────────────────────────────────────────────────
def main():
    log("=== PixelBridge Agent avviato ===")
    log(f"Server: {SERVER_URL}")
    log(f"Macchina: {get_machine_name()}")

    cfg = load_config()
    token = cfg.get('token')

    if not token:
        log("Nessun token trovato — avvio pairing")
        token = do_pairing()

    if token:
        run_player(token)

if __name__ == '__main__':
    main()
