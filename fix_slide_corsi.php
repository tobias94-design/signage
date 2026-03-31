<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

echo "<h2>Fix Slide Corsi - Contenuto da Array a Oggetto</h2>";

// Trova tutte le slide corsi con contenuto "[]"
$slides = $db->query("SELECT id, tipo, contenuto FROM sidebar_slides WHERE tipo='corsi'")->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;
foreach ($slides as $slide) {
    $contenuto = $slide['contenuto'];
    
    // Se il contenuto è "[]" o array vuoto, convertilo in "{}"
    if ($contenuto === '[]' || $contenuto === 'null' || empty($contenuto)) {
        $db->prepare("UPDATE sidebar_slides SET contenuto='{}' WHERE id=?")->execute([$slide['id']]);
        echo "<p style='color:green'>✓ Slide ID {$slide['id']} - Contenuto fixato: {$contenuto} → {}</p>";
        $fixed++;
    } else {
        echo "<p style='color:gray'>- Slide ID {$slide['id']} - OK: {$contenuto}</p>";
    }
}

echo "<hr>";
echo "<p><strong>✅ Completato! {$fixed} slide fixate.</strong></p>";
echo "<p><a href='/layout.php'>← Torna a Layout</a></p>";
?>
