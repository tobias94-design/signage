Sub Main()
    print "PixelBridge: avvio con NodeJS abilitato"

    r = CreateObject("roRectangle", 0, 0, 1920, 1080)

    config = {
        nodejs_enabled: true,
        brightsign_js_objects_enabled: true,
        javascript_enabled: true,
        mouse_enabled: false,
        storage_path: "SD:",
        storage_quota: 1073741824,
        url: "file:///sd:/index.html",
        port: 0
    }

    msgPort = CreateObject("roMessagePort")
    config.port = msgPort

    h = CreateObject("roHtmlWidget", r, config)
    h.SetPort(msgPort)
    h.Show()

    print "PixelBridge: widget avviato"

    Do While True
        msg = Wait(0, msgPort)
        If Type(msg) = "roHtmlWidgetEvent" Then
            data = msg.GetData()
            print "Evento: " + data.reason
            If data.reason = "load-error" Or data.reason = "crash" Then
                print "Errore - ricarico tra 10s"
                Sleep(10000)
                h.LoadURL("file:///sd:/index.html")
            End If
        End If
    End Do

End Sub
