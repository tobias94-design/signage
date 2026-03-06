#!/bin/bash
# Chiudi Chrome se aperto
pkill -x "Google Chrome" 2>/dev/null
sleep 1

# Avvia Chrome in kiosk
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --kiosk \
  --fullscreen \
  --noerrdialogs \
  --disable-infobars \
  --disable-session-crashed-bubble \
  --no-first-run \
  "http://localhost:8888/player/corsi.php?token=soave-97cbe7"