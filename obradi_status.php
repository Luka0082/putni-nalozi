<?php
session_start();
require 'db.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['akcija'])) {
    $nalog_id = (int)$_GET['id'];
    $akcija = $_GET['akcija'];
    $voditelj_id = $_SESSION['korisnik_id'];

    $novo_stanje = null;
    if ($akcija === 'odobri') {
        $novo_stanje = 'Odobreno';
    } elseif ($akcija === 'odbij') {
        $novo_stanje = 'Odbijeno';
    }

    if ($novo_stanje) {
        $stmt = $pdo->prepare("UPDATE putni_nalozi SET status = ?, odobrio_id = ? WHERE id = ?");
        $stmt->execute([$novo_stanje, $voditelj_id, $nalog_id]);
    }
}

header("Location: dashboard.php");
exit;