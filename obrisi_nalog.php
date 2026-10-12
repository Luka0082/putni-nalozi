<?php
session_start();
require 'db.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

$nalog_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$korisnik_id = $_SESSION['korisnik_id'];
$uloga_id = $_SESSION['uloga_id'];

// Dohvat naloga
$stmt = $pdo->prepare("SELECT * FROM putni_nalozi WHERE id = ?");
$stmt->execute([$nalog_id]);
$nalog = $stmt->fetch();

if (!$nalog) {
    die("Nalog ne postoji.");
}

// Sigurnosna provjera: Administrator može brisati BILO KOJI nalog, zaposlenik može samo svoj i to samo ako je 'Na čekanju'
$moze_obrisati = false;
if ($uloga_id == 1) {
    $moze_obrisati = true;
} elseif ($nalog['zaposlenik_id'] == $korisnik_id && $nalog['status'] === 'Na čekanju') {
    $moze_obrisati = true;
}

if (!$moze_obrisati) {
    die("Nemate ovlasti za brisanje ovog naloga ili je nalog već odobren/obrađen.");
}

// 1. Pronađi sve priložene račune (slike/PDF) i obriši ih s tvrdog diska
$stmt_troskovi = $pdo->prepare("SELECT putanja_racuna FROM troskovi_naloga WHERE nalog_id = ?");
$stmt_troskovi->execute([$nalog_id]);
$troskovi = $stmt_troskovi->fetchAll();

foreach ($troskovi as $t) {
    if (!empty($t['putanja_racuna']) && file_exists($t['putanja_racuna'])) {
        unlink($t['putanja_racuna']);
    }
}

// 2. Obriši pojedinačne troškove iz baze
$stmt_del_troskovi = $pdo->prepare("DELETE FROM troskovi_naloga WHERE nalog_id = ?");
$stmt_del_troskovi->execute([$nalog_id]);

// 3. Obriši glavni nalog iz baze
$stmt_del_nalog = $pdo->prepare("DELETE FROM putni_nalozi WHERE id = ?");
$stmt_del_nalog->execute([$nalog_id]);

// Povratak na nadzornu ploču
header("Location: dashboard.php");
exit;