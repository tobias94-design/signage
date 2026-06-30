Sub Main()
    print "PixelBridge: TEST GRAFICA BASE"

    screen = CreateObject("roScreen", true, 1920, 1080)
    screen.SetAlphaEnable(true)
    screen.Clear(&HFF0000FF)
    screen.SwapBuffers()

    print "PixelBridge: schermo disegnato"

    Do While True
        Sleep(5000)
        print "PixelBridge: ancora vivo"
    End Do
End Sub
