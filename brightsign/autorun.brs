Sub Main()
    print "PixelBridge: avvio"

    msgPort = CreateObject("roMessagePort")

    ' Abilita multiProcess (richiesto per roHtmlWidget stabile)
    registrySection = CreateObject("roRegistrySection", "html")
    multiProcess = registrySection.read("mp")
    if multiProcess <> "1" then
        registrySection.write("mp", "1")
        registrySection.flush()
        RebootSystem()
    endif

    ' Imposta risoluzione
    videoModeProvider = CreateObject("roVideoMode")
    videoModeProvider.SetMode("1920x1080x60p")
    videoModeProvider.SetGraphicsZOrder("back")

    fullScreenRectangle = CreateObject("roRectangle", 0, 0, 1920, 1080)

    ' Configurazione widget HTML
    config = {
        nodejs_enabled: true
        brightsign_js_objects_enabled: true
        mouse_enabled: false
        focus_enabled: true
        javascript_enabled: true
        url: "file:///index.html"
        storage_path: "SD:"
        storage_quota: 1073741824
        hwz_default: "on"
        security_params: {
            websecurity: false
        }
    }

    htmlWidget = CreateObject("roHtmlWidget", fullScreenRectangle, config)
    htmlWidget.SetPort(msgPort)
    htmlWidget.SetProxy("")
    Sleep(5000)
    htmlWidget.Show()

    print "PixelBridge: widget avviato"

    While True
        msg = msgPort.WaitMessage(50)
        If Type(msg) = "roHtmlWidgetEvent" Then
            data = msg.GetData()
            print "Evento: " + data.reason
            If data.reason = "load-error" Or data.reason = "crash" Then
                print "Errore - ricarico tra 10s"
                Sleep(10000)
                htmlWidget.LoadURL("file:///index.html")
            End If
        End If
    End While

End Sub
