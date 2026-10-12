<?php
session_start();
require_once 'db.php';
require_once 'obracun.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$korisnik_id = $_SESSION['korisnik_id'];
$uloga_id = $_SESSION['uloga_id'];

// Filteri
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'svi';
$zaposlenik_filter = isset($_GET['zaposlenik_filter']) ? (int)$_GET['zaposlenik_filter'] : 0;
$datum_od = isset($_GET['datum_od']) ? $_GET['datum_od'] : '';
$datum_do = isset($_GET['datum_do']) ? $_GET['datum_do'] : '';

// Paginacija
$po_stranici = 10;
$trenutna_stranica = isset($_GET['p']) && (int)$_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($trenutna_stranica - 1) * $po_stranici;

// Dohvat vlastitih naloga zaposlenika
$sql_moji = "SELECT * FROM putni_nalozi WHERE zaposlenik_id = ?";
$params_moji = [$korisnik_id];

if ($filter === 'cekanje') {
    $sql_moji .= " AND status = 'Na čekanju'";
} elseif ($filter === 'odobreno') {
    $sql_moji .= " AND status = 'Odobreno'";
} elseif ($filter === 'odbijeno') {
    $sql_moji .= " AND status = 'Odbijeno'";
}

if (!empty($datum_od)) {
    $sql_moji .= " AND datum_polaska >= ?";
    $params_moji[] = $datum_od;
}
if (!empty($datum_do)) {
    $sql_moji .= " AND datum_polaska <= ?";
    $params_moji[] = $datum_do;
}

$sql_moji .= " ORDER BY datum_kreiranja DESC";

$stmt_moji = $pdo->prepare($sql_moji);
$stmt_moji->execute($params_moji);
$moji_nalozi = $stmt_moji->fetchAll();

// Statistika za Admina / Voditelja & Popis org naloga
$nalozi_org = [];
$svi_zaposlenici = [];
$ukupno_stranica = 1;

if ($uloga_id == 2 || $uloga_id == 1) {
    $svi_zaposlenici = $pdo->query("SELECT id, ime, prezime FROM korisnici ORDER BY prezime, ime")->fetchAll();

    $sql_count = "SELECT COUNT(*) FROM putni_nalozi pn WHERE 1=1";
    $params_count = [];
    
    if ($filter === 'cekanje') {
        $sql_count .= " AND pn.status = 'Na čekanju'";
    } elseif ($filter === 'odobreno') {
        $sql_count .= " AND pn.status = 'Odobreno'";
    } elseif ($filter === 'odbijeno') {
        $sql_count .= " AND pn.status = 'Odbijeno'";
    }

    if ($zaposlenik_filter > 0) {
        $sql_count .= " AND pn.zaposlenik_id = ?";
        $params_count[] = $zaposlenik_filter;
    }

    if (!empty($datum_od)) {
        $sql_count .= " AND pn.datum_polaska >= ?";
        $params_count[] = $datum_od;
    }
    if (!empty($datum_do)) {
        $sql_count .= " AND pn.datum_polaska <= ?";
        $params_count[] = $datum_do;
    }

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params_count);
    $ukupno_naloga_org = $stmt_count->fetchColumn();
    $ukupno_stranica = ceil($ukupno_naloga_org / $po_stranici);
    if ($ukupno_stranica < 1) {
        $ukupno_stranica = 1;
    }

    $sql_org = "SELECT pn.*, k.ime, k.prezime, k.nadredeni_id 
                FROM putni_nalozi pn 
                JOIN korisnici k ON pn.zaposlenik_id = k.id 
                WHERE 1=1";
    $params_org = [];
    
    if ($filter === 'cekanje') {
        $sql_org .= " AND pn.status = 'Na čekanju'";
    } elseif ($filter === 'odobreno') {
        $sql_org .= " AND pn.status = 'Odobreno'";
    } elseif ($filter === 'odbijeno') {
        $sql_org .= " AND pn.status = 'Odbijeno'";
    }

    if ($zaposlenik_filter > 0) {
        $sql_org .= " AND pn.zaposlenik_id = ?";
        $params_org[] = $zaposlenik_filter;
    }

    if (!empty($datum_od)) {
        $sql_org .= " AND pn.datum_polaska >= ?";
        $params_org[] = $datum_od;
    }
    if (!empty($datum_do)) {
        $sql_org .= " AND pn.datum_polaska <= ?";
        $params_org[] = $datum_do;
    }

    $sql_org .= " ORDER BY pn.datum_kreiranja DESC LIMIT $po_stranici OFFSET $offset";

    $stmt_org = $pdo->prepare($sql_org);
    $stmt_org->execute($params_org);
    $nalozi_org = $stmt_org->fetchAll();
}

// Osobna operativna statistika zaposlenika (samo 3 polja)
$stat_ukupno = $pdo->prepare("SELECT COUNT(*) FROM putni_nalozi WHERE zaposlenik_id = ?");
$stat_ukupno->execute([$korisnik_id]);
$ukupno_mojih = $stat_ukupno->fetchColumn();

$stat_cekanje = $pdo->prepare("SELECT COUNT(*) FROM putni_nalozi WHERE zaposlenik_id = ? AND status = 'Na čekanju'");
$stat_cekanje->execute([$korisnik_id]);
$cekanje_mojih = $stat_cekanje->fetchColumn();

$stat_odobreno = $pdo->prepare("SELECT COUNT(*) FROM putni_nalozi WHERE zaposlenik_id = ? AND status = 'Odobreno'");
$stat_odobreno->execute([$korisnik_id]);
$odobreno_mojih = $stat_odobreno->fetchColumn();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nadzorna ploča - Evidencija putnih naloga</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-box {
            background: #ffffff;
            border: 1px solid var(--color-border);
            padding: 1rem;
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--color-primary);
        }
        
        .filter-container {
            background-color: #ffffff;
            border: 1px solid #000000;
            border-top: 4px solid #0d1e2d;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #444444;
            margin-bottom: 0.4rem;
        }
        .modern-input, .modern-date {
            padding: 0.6rem 0.8rem;
            border: 2px solid #a0a0a0;
            background-color: #fcfcfc;
            font-size: 0.95rem;
            font-weight: bold;
            color: #111111;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .modern-input {
            min-width: 150px;
        }
        .modern-date {
            appearance: none;
            -webkit-appearance: none;
            width: 170px;
        }
        .modern-input:focus, .modern-input:hover,
        .modern-date:focus, .modern-date:hover {
            outline: none;
            border-color: #0055a5;
            background-color: #ffffff;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>Evidencija putnih naloga</h1>
    <div class="navbar-user">
        <a href="dashboard.php" class="nav-btn">Nadzorna ploča</a>
        <?php if ($_SESSION['uloga_id'] == 1 || $_SESSION['uloga_id'] == 2): ?>
            <a href="izvjestaji.php" class="nav-btn">Izvještaji</a>
        <?php endif; ?>
        <?php if ($_SESSION['uloga_id'] == 1): ?>
            <a href="admin_korisnici.php" class="nav-btn">Upravljaj profilima</a>
        <?php endif; ?>
        <span class="user-badge"><?= htmlspecialchars($_SESSION['ime'] . ' ' . $_SESSION['prezime']) ?> (<?= htmlspecialchars($_SESSION['uloga_naziv']) ?>)</span>
        <a href="logout.php" class="nav-btn nav-btn-logout">Odjava</a>
    </div>
</div>

<div class="container">

    <!-- Samo 3 osobne operativne kartice na nadzornoj ploči -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?= $ukupno_mojih ?></div>
            <div style="font-size: 0.85rem; color: #444444; font-weight: bold;">Moji predani nalozi</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: #8a6200;"><?= $cekanje_mojih ?></div>
            <div style="font-size: 0.85rem; color: #444444; font-weight: bold;">Moji nalozi na čekanju</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: #1e6b2d;"><?= $odobreno_mojih ?></div>
            <div style="font-size: 0.85rem; color: #444444; font-weight: bold;">Moji odobreni nalozi</div>
        </div>
    </div>

    <!-- MOJI NALOZI (Prebačeni na vrh) -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Moji putni nalozi</h2>
            <a href="kreiraj_nalog.php" class="btn">Stvori novi nalog</a>
        </div>

        <?php if (count($moji_nalozi) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Relacija</th>
                        <th>Svrha</th>
                        <th>Datum polaska</th>
                        <th>Datum povratka</th>
                        <th>Status</th>
                        <th style="width: 1%;">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($moji_nalozi as $nalog): ?>
                        <tr>
                            <td><?= htmlspecialchars(!empty($nalog['polaziste']) ? $nalog['polaziste'] . ' - ' . $nalog['odrediste'] : $nalog['odrediste']) ?></td>
                            <td><?= htmlspecialchars($nalog['svrha']) ?></td>
                            <td style="white-space: nowrap;"><?= date('d.m.Y.', strtotime($nalog['datum_polaska'])) ?></td>
                            <td style="white-space: nowrap;"><?= date('d.m.Y.', strtotime($nalog['datum_povratka'])) ?></td>
                            <td>
                                <?php 
                                    $class = 'badge-cekanje';
                                    if ($nalog['status'] === 'Odobreno') $class = 'badge-odobreno';
                                    if ($nalog['status'] === 'Odbijeno') $class = 'badge-odbijeno';
                                ?>
                                <span class="badge <?= $class ?>"><?= htmlspecialchars($nalog['status']) ?></span>
                            </td>
                            <td>
                                <div class="action-flex">
                                    <a href="detalji_naloga.php?id=<?= $nalog['id'] ?>" class="btn" style="background:#0055a5; border-color:#004080;">Detalji</a>
                                    <?php if ($nalog['status'] === 'Na čekanju'): ?>
                                        <a href="uredi_nalog.php?id=<?= $nalog['id'] ?>" class="btn" style="background:#666666; border-color:#444444;">Uredi</a>
                                        <button type="button" class="btn btn-danger" onclick="otvoriModalZaBrisanjeNaloga('obrisi_nalog.php?id=<?= $nalog['id'] ?>')">Obriši</button>
                                    <?php endif; ?>
                                    <a href="print_nalog.php?id=<?= $nalog['id'] ?>" class="btn" style="background:#444444; border-color:#222222;">Ispis</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="border: 1px solid #b0b0b0; padding: 1rem; background-color: #f9f9f9;">Trenutno nemate predanih putnih naloga.</p>
        <?php endif; ?>
    </div>

    <!-- PREGLED SVIH PUTNIH NALOGA I FILTER (Spojeni) -->
    <?php if ($uloga_id == 2 || $uloga_id == 1): ?>
    <div class="card">
        <h2>Pregled svih putnih naloga (Organizacija)</h2>
        
        <div class="filter-container">
            <form method="GET" action="dashboard.php" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label class="filter-label">Status:</label>
                    <select name="filter" class="modern-input">
                        <option value="svi" <?= $filter === 'svi' ? 'selected' : '' ?>>Svi nalozi</option>
                        <option value="cekanje" <?= $filter === 'cekanje' ? 'selected' : '' ?>>Na čekanju</option>
                        <option value="odobreno" <?= $filter === 'odobreno' ? 'selected' : '' ?>>Odobreni</option>
                        <option value="odbijeno" <?= $filter === 'odbijeno' ? 'selected' : '' ?>>Odbijeni</option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Zaposlenik:</label>
                    <select name="zaposlenik_filter" class="modern-input">
                        <option value="0">Svi zaposlenici</option>
                        <?php foreach ($svi_zaposlenici as $z): ?>
                            <option value="<?= $z['id'] ?>" <?= $zaposlenik_filter == $z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['ime'] . ' ' . $z['prezime']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Od datuma:</label>
                    <input type="date" name="datum_od" value="<?= htmlspecialchars($datum_od) ?>" class="modern-date">
                </div>
                <div>
                    <label class="filter-label">Do datuma:</label>
                    <input type="date" name="datum_do" value="<?= htmlspecialchars($datum_do) ?>" class="modern-date">
                </div>
                
                <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn" style="height: 38px; padding: 0 1.2rem;">Filtriraj</button>
                    <a href="dashboard.php" class="btn" style="height: 38px; background:#64748b; border-color:#475569; padding: 0 1.2rem; line-height: 36px;">Poništi</a>
                    <a href="export_csv.php?filter=<?= htmlspecialchars($filter) ?>&zaposlenik_filter=<?= $zaposlenik_filter ?>&datum_od=<?= htmlspecialchars($datum_od) ?>&datum_do=<?= htmlspecialchars($datum_do) ?>" class="btn btn-success" style="height: 38px; padding: 0 1.2rem; line-height: 36px;">Izvezi CSV</a>
                </div>
            </form>
        </div>

        <?php if (count($nalozi_org) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Zaposlenik</th>
                        <th>Relacija (Polazište - Odredište)</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th style="width: 1%;">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nalozi_org as $nalog): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($nalog['ime'] . ' ' . $nalog['prezime']) ?></strong></td>
                            <td><?= htmlspecialchars(!empty($nalog['polaziste']) ? $nalog['polaziste'] . ' - ' . $nalog['odrediste'] : $nalog['odrediste']) ?></td>
                            <td style="white-space: nowrap;"><?= date('d.m.Y.', strtotime($nalog['datum_polaska'])) ?> - <?= date('d.m.Y.', strtotime($nalog['datum_povratka'])) ?></td>
                            <td>
                                <?php 
                                    $class = 'badge-cekanje';
                                    if ($nalog['status'] === 'Odobreno') $class = 'badge-odobreno';
                                    if ($nalog['status'] === 'Odbijeno') $class = 'badge-odbijeno';
                                ?>
                                <span class="badge <?= $class ?>"><?= htmlspecialchars($nalog['status']) ?></span>
                            </td>
                            <td>
                                <div class="action-flex">
                                    <a href="detalji_naloga.php?id=<?= $nalog['id'] ?>" class="btn" style="background:#0055a5; border-color:#004080;">Detalji</a>
                                    
                                    <?php if ($nalog['status'] === 'Na čekanju' && ($uloga_id == 1 || $nalog['nadredeni_id'] == $korisnik_id)): ?>
                                        <a href="obradi_status.php?id=<?= $nalog['id'] ?>&akcija=odobri" class="btn btn-success">Odobri</a>
                                        <a href="obradi_status.php?id=<?= $nalog['id'] ?>&akcija=odbij" class="btn btn-danger">Odbij</a>
                                    <?php endif; ?>
                                    
                                    <a href="print_nalog.php?id=<?= $nalog['id'] ?>" class="btn" style="background:#444444; border-color:#222222;">Ispis</a>
                                    
                                    <?php if ($_SESSION['uloga_id'] == 1): ?>
                                        <button type="button" class="btn btn-danger" onclick="otvoriModalZaBrisanjeNaloga('obrisi_nalog.php?id=<?= $nalog['id'] ?>')">Obriši</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($ukupno_stranica > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $ukupno_stranica; $i++): ?>
                        <a href="dashboard.php?p=<?= $i ?>&filter=<?= htmlspecialchars($filter) ?>&zaposlenik_filter=<?= $zaposlenik_filter ?>&datum_od=<?= htmlspecialchars($datum_od) ?>&datum_do=<?= htmlspecialchars($datum_do) ?>" class="<?= $i === $trenutna_stranica ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p style="border: 1px solid #b0b0b0; padding: 1rem; background-color: #f9f9f9;">Nema putnih naloga za odabrane kriterije.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<footer class="footer">
    <div class="footer-links">
        <a href="privatnost.php">Politika privatnosti</a> | 
        <a href="uvjeti.php">Uvjeti korištenja</a>
    </div>
    <div>Evidencija putnih naloga &copy; 2026</div>
</footer>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top: 0; color: var(--color-danger); border-bottom: 2px solid var(--color-danger); padding-bottom: 0.5rem; text-transform: uppercase;">Potvrda brisanja naloga</h3>
        <p style="font-weight: bold; font-size: 1rem;">Jeste li sigurni da želite trajno obrisati ovaj putni nalog?</p>
        <p style="font-size: 0.85rem; color: #444444;">Svi uneseni troškovi i podaci vezani uz ovaj nalog bit će nepovratno izbrisani iz baze.</p>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Obriši nalog</a>
            <button type="button" class="btn" style="background-color: #666666; border-color: #444444;" onclick="zatvoriModal()">Odustani</button>
        </div>
    </div>
</div>

<script>
    function otvoriModalZaBrisanjeNaloga(url) {
        document.getElementById('confirmDeleteBtn').href = url;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function zatvoriModal() {
        document.getElementById('deleteModal').style.display = 'none';
        document.getElementById('confirmDeleteBtn').href = '#';
    }
</script>

</body>
</html>