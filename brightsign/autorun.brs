' ═══════════════════════════════════════════════
' PixelBridge — autorun.brs per BrightSign XT1144
' ═══════════════════════════════════════════════

Sub Main()

    ' ── CONFIGURAZIONE ──────────────────────────
    token     = "IL_TUO_TOKEN_QUI"
    serverUrl = "https://pixelbridge.it"
    playerUrl = serverUrl + "/player/display.php?token=" + token

    ' ── ATTENDI RETE ────────────────────────────
    print "PixelBridge: attendo connessione di rete..."
    elapsed = 0
    Do While elapsed < 30
        Sleep(1000)
        elapsed = elapsed + 1
        net = CreateObject("roNetworkConfiguration", 0)
        cfg = net.GetCurrentConfig()
        If cfg.ip <> "" Then
            print "PixelBridge: IP ottenuto → " + cfg.ip
            Exit Do
        End If
    End Do

    ' ── CONFIGURA HTML5 WIDGET ──────────────────
    config = {
        js_enabled:       True,
        inspector_server: False,
        mouse_enabled:    False,
        port:             0
    }

    msgPort = CreateObject("roMessagePort")
    rect    = CreateObject("roRectangle", 0, 0, 1920, 1080)

    html = CreateObject("roHtmlWidget", rect, config)
    html.SetPort(msgPort)
    html.EnableGPUCompositing(True)
    html.SetUserAgent("PixelBridge-BrightSign/1.0 XT1144")
    html.LoadURL(playerUrl)

    print "PixelBridge: carico → " + playerUrl

    ' ── LOOP WATCHDOG ───────────────────────────
    Do While True
        msg = Wait(0, msgPort)
        If Type(msg) = "roHtmlWidgetEvent" Then
            data = msg.GetData()
            print "Evento HTML: " + data.reason
            If data.reason = "load-error" Or data.reason = "crash" Then
                print "Errore — ricarico tra 10 secondi..."
                Sleep(10000)
                html.LoadURL(playerUrl)
            End If
        End If
    End Do

End Sub