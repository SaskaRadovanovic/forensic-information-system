<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$result = $conn->query("SELECT id, putanja FROM dokument WHERE sadrzaj_tekst IS NULL AND putanja != 'nema-fajla'");

while ($row = $result->fetch_assoc()) {
    $fizickaPutanja = UPLOAD_DIR . $row['putanja'];
    $tekst = ekstrahujTekstIzPdf($fizickaPutanja);

    if ($tekst !== '') {
        $stmt = $conn->prepare("UPDATE dokument SET sadrzaj_tekst = ? WHERE id = ?");
        $stmt->bind_param('si', $tekst, $row['id']);
        $stmt->execute();
        $stmt->close();
        echo "Dokument #{$row['id']}: ekstrahovano " . strlen($tekst) . " karaktera\n";
    } else {
        echo "Dokument #{$row['id']}: nema teksta ili nije PDF\n";
    }
}

echo "Gotovo.\n";
