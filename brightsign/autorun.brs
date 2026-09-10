' ═══════════════════════════════════════════════════════
' PixelBridge — autorun.brs v1
' Base stabile: multiProcess + widget HTML + reload anti-freeze
' Nessuna persistenza token in questa versione (arriva in v2)
' ═══════════════════════════════════════════════════════

Sub Main()

    print "PixelBridge v1: avvio"

    ' ── STEP 1: multiProcess obbligatorio per roHtmlWidget stabile ──
    registrySection = CreateObject("roRegistrySection", "html")
    mp = registrySection.read("mp")
    if mp <> "1" then
        print "PixelBridge v1: imposto multiProcess e riavvio"
        registrySection.write("mp", "1")
        registrySection.flush()
        RebootSystem()
        return
    endif

    print "PixelBridge v1: multiProcess OK"

    ' ── STEP 2: risoluzione video ──
    videoModeProvider = CreateObject("roVideoMode")
    videoModeProvider.SetMode("1920x1080x60p")
    videoModeProvider.SetGraphicsZOrder("back")

    ' ── STEP 3: rettangolo fullscreen ──
    fullScreenRectangle = CreateObject("roRectangle", 0, 0, 1920, 1080)

    ' ── STEP 4: config widget (niente config.port!) ──
    config = {
        nodejs_enabled: true
        brightsign_js_objects_enabled: true
        mouse_enabled: false
        focus_enabled: true
        javascript_enabled: true
        url: "SD:/index.html"
        storage_path: "SD:"
        storage_quota: 1073741824
        hwz_default: "on"
        security_params: {
            websecurity: false
        }
    }

    msgPort = CreateObject("roMessagePort")

    htmlWidget = CreateObject("roHtmlWidget", fullScreenRectangle, config)
    htmlWidget.SetPort(msgPort)
    htmlWidget.SetProxy("")

    ' Attesa prima di mostrare (evita crash da widget non pronto)
    Sleep(5000)
    htmlWidget.Show()

    print "PixelBridge v1: widget avviato"

    ' ── STEP 5: timer reload anti-freeze ogni 15 minuti ──
    reloadTimer = CreateObject("roTimer")
    reloadTimer.SetPort(msgPort)
    reloadTimer.SetDuration(900000, true) ' 15 minuti in ms, ripetuto

    ' ── LOOP EVENTI ──
    While True
        msg = msgPort.WaitMessage(0)

        If Type(msg) = "roHtmlWidgetEvent" Then
            data = msg.GetData()
            print "PixelBridge v1: evento widget - " + data.reason
            If data.reason = "load-error" Or data.reason = "crash" Then
                print "PixelBridge v1: errore widget, ricarico tra 10s"
                Sleep(10000)
                htmlWidget.LoadURL("SD:/index.html")
            End If

        Else If Type(msg) = "roTimerEvent" Then
            print "PixelBridge v1: reload anti-freeze programmato"
            htmlWidget.LoadURL("SD:/index.html")
        End If

    End While

End Sub
