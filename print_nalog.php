<?php
session_start();
require 'db.php';
require 'obracun.php'; 

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

$nalog_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$korisnik_id = $_SESSION['korisnik_id'];
$uloga_id = $_SESSION['uloga_id'];

// Dohvat podataka
$stmt = $pdo->prepare("
    SELECT pn.*, k.ime AS zaposlenik_ime, k.prezime AS zaposlenik_prezime, k.nadredeni_id
    FROM putni_nalozi pn
    JOIN korisnici k ON pn.zaposlenik_id = k.id
    WHERE pn.id = ?
");
$stmt->execute([$nalog_id]);
$nalog = $stmt->fetch();

if (!$nalog) {
    die("Putni nalog nije pronađen.");
}

// Provjera prava pristupa
if ($nalog['zaposlenik_id'] != $korisnik_id && $nalog['nadredeni_id'] != $korisnik_id && $uloga_id != 1) {
    die("Nemate ovlasti za ispis ovog naloga.");
}

// Dohvat troškova
$stmt_troskovi = $pdo->prepare("SELECT * FROM troskovi_naloga WHERE nalog_id = ?");
$stmt_troskovi->execute([$nalog_id]);
$troskovi = $stmt_troskovi->fetchAll();

// Izračun
$obracun = izracunajObracun($nalog, $troskovi);
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Putni Nalog - <?= $nalog['id'] ?></title>
    <style>
        * {
            box-sizing: border-box;
            border-radius: 0 !important;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000000;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background-color: #d1d5db; 
        }
        
        .a4-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border: 1px solid #000000;
        }

        .no-print {
            margin-bottom: 20px;
            padding: 10px;
            background: #e5e5e5;
            border: 1px solid #000000;
            text-align: right;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn {
            display: inline-block;
            background-color: #0d1e2d;
            color: #ffffff;
            padding: 6px 12px;
            border: 1px solid #000000;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-secondary {
            background-color: #ffffff;
            color: #000000;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            font-weight: bold;
        }
        h2 {
            font-size: 13px;
            background-color: #e5e5e5;
            padding: 4px 6px;
            border: 1px solid #000000;
            margin-top: 15px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #000000;
            padding: 5px 8px;
            text-align: left;
            font-size: 12px;
            vertical-align: top;
        }
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 30%;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .signature-box {
            width: 40%;
            text-align: center;
            border-top: 1px solid #000000;
            padding-top: 5px;
            font-weight: bold;
        }
        .tekst-box {
            border: 1px solid #000000;
            padding: 10px;
            min-height: 80px;
            margin-bottom: 15px;
            white-space: pre-wrap;
        }

        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .a4-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: none;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">Ispiši dokument (Spremi kao PDF)</button>
    <a href="detalji_naloga.php?id=<?= $nalog['id'] ?>" class="btn btn-secondary">Zatvori</a>
</div>

<div class="a4-container">
    <div class="header">
        <h1>Službeni putni nalog br. <?= $nalog['id'] ?></h1>
        <p>Datum izdavanja: <?= date('d.m.Y.', strtotime($nalog['datum_kreiranja'])) ?> | Status: <?= htmlspecialchars($nalog['status']) ?></p>
    </div>

    <h2>1. Podaci o nalogu i putovanju</h2>
    <table>
        <tr>
            <th>Zaposlenik:</th>
            <td><strong><?= htmlspecialchars($nalog['zaposlenik_ime'] . ' ' . $nalog['zaposlenik_prezime']) ?></strong></td>
        </tr>
        <tr>
            <th>Relacija:</th>
            <td><?= htmlspecialchars($nalog['polaziste']) ?> - <?= htmlspecialchars($nalog['odrediste']) ?></td>
        </tr>
        <tr>
            <th>Svrha puta:</th>
            <td><?= htmlspecialchars($nalog['svrha']) ?></td>
        </tr>
        <tr>
            <th>Vrsta prijevoza:</th>
            <td><?= htmlspecialchars($nalog['vrsta_prijevoza']) ?></td>
        </tr>
        <tr>
            <th>Vrijeme putovanja:</th>
            <td>
                Polazak: <?= date('d.m.Y. H:i', strtotime($nalog['datum_polaska'] . ' ' . $nalog['vrijeme_polaska'])) ?><br>
                Povratak: <?= date('d.m.Y. H:i', strtotime($nalog['datum_povratka'] . ' ' . $nalog['vrijeme_povratka'])) ?><br>
                <strong>Ukupno trajanje: <?= $obracun['ukupno_sati'] ?> sati</strong>
            </td>
        </tr>
    </table>

    <h2>2. Izvješće s puta</h2>
    <div class="tekst-box">
<?= !empty($nalog['putno_izvjesce']) ? htmlspecialchars($nalog['putno_izvjesce']) : 'Nije uneseno putno izvješće.' ?>
    </div>

    <h2>3. Obračun troškova</h2>
    <table>
        <tr>
            <th>Pune dnevnice:</th>
            <td><?= $obracun['pune_dnevnice_broj'] ?> kom x 30.00 € = <?= number_format($obracun['pune_dnevnice_broj'] * 30, 2) ?> €</td>
        </tr>
        <tr>
            <th>Poludnevnice:</th>
            <td><?= $obracun['pola_dnevnice_broj'] ?> kom x 15.00 € = <?= number_format($obracun['pola_dnevnice_broj'] * 15, 2) ?> €</td>
        </tr>
        <tr>
            <th>Umanjenje za obroke:</th>
            <td>
                <?= $nalog['osiguran_dorucak'] ? 'Doručak (-20%) ' : '' ?>
                <?= $nalog['osiguran_rucak'] ? 'Ručak (-30%) ' : '' ?>
                <?= $nalog['osiguran_veceri'] ? 'Večera (-30%)' : '' ?>
                <?= (!$nalog['osiguran_dorucak'] && (!$nalog['osiguran_rucak']) && (!$nalog['osiguran_veceri'])) ? 'Bez umanjenja' : '' ?>
            </td>
        </tr>
        <tr>
            <th>Neto dnevnice:</th>
            <td><strong><?= number_format($obracun['neto_dnevnice'], 2) ?> €</strong></td>
        </tr>
        <tr>
            <th>Kilometraža (0.50 €/km):</th>
            <td>
                Početna: <?= $nalog['pocetna_km'] ?> km | Završna: <?= $nalog['zavrsna_km'] ?> km<br>
                Prijedeno: <?= $obracun['prijedeno_km'] ?> km = <strong><?= number_format($obracun['iznos_km'], 2) ?> €</strong>
            </td>
        </tr>
    </table>

    <h2>4. Ostali troškovi (Računi)</h2>
    <?php if (count($troskovi) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Vrsta troška</th>
                    <th>Iznos (€)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($troskovi as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['vrsta_troska']) ?></td>
                        <td><?= number_format($t['iznos'], 2) ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th>Ukupno ostali troškovi:</th>
                    <td><strong><?= number_format($obracun['iznos_ostali_troskovi'], 2) ?> €</strong></td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p style="border: 1px solid #000000; padding: 10px;">Nema priloženih dodatnih troškova.</p>
    <?php endif; ?>

    <h2>5. Rekapitulacija i isplata</h2>
    <table>
        <tr>
            <th>Ukupni trošak putovanja:</th>
            <td><strong><?= number_format($obracun['ukupni_trosak'], 2) ?> €</strong></td>
        </tr>
        <tr>
            <th>Isplaćena akontacija (Predujam):</th>
            <td>- <?= number_format($obracun['akontacija'], 2) ?> €</td>
        </tr>
        <tr style="background-color: #e5e5e5; font-size: 14px;">
            <th>SALDO ZA ISPLATU / POVRAT:</th>
            <td><strong><?= number_format($obracun['saldo'], 2) ?> €</strong></td>
        </tr>
    </table>

    <div class="signatures">
        <div class="signature-box">
            Potpis zaposlenika
        </div>
        <div class="signature-box">
            Odobrio (Voditelj / Direktor)
        </div>
    </div>
</div>

<script>
    // window.onload = function() { window.print(); }
</script>

</body>
</html>