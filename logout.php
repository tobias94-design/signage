<?php
// logout.php — distrugge la sessione così il ruolo aggiornato nel DB
// viene ricaricato al prossimo login. Da eliminare dopo l'uso se non ti serve stabilmente.
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
echo '<div style="font-family:sans-serif;padding:60px;text-align:center">
    <h2>Sessione terminata</h2>
    <p>Ora puoi fare login di nuovo.</p>
    <a href="/login.php" style="color:#2578D1">Vai al login →</a>
</div>';
