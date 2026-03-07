<!DOCTYPE html>
<html>
<head>
    <style>
        body { background:#000; margin:0; color:#fff; font-family:Arial; }
        video { width:100vw; height:100vh; object-fit:cover; position:absolute; top:0; }
        #info { position:absolute; top:10px; left:10px; z-index:9; font-size:14px; }
    </style>
</head>
<body>
<div id="info">Connessione...</div>
<video id="tv" autoplay playsinline muted></video>
<script>
async function avvia() {
    // Prima chiedi permesso generico, poi enumera
    await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    
    const devices = await navigator.mediaDevices.enumerateDevices();
    const video   = devices.filter(d => d.kind === 'videoinput');
    
    document.getElementById('info').textContent = 
        'Dispositivi: ' + video.map(d => d.label).join(' | ');

    // Prende il primo videoinput (su PC senza webcam = capture card)
    const capture = video[0];
    
    const stream = await navigator.mediaDevices.getUserMedia({
        video: { deviceId: { exact: capture.deviceId } },
        audio: true
    });
    
    document.getElementById('tv').srcObject = stream;
    document.getElementById('info').textContent = '✅ ' + capture.label;
}
avvia().catch(e => document.getElementById('info').textContent = '❌ ' + e.message);
</script>
</body>
</html>