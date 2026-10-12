<?php
session_start();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politika privatnosti - Evidencija putnih naloga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231e3a8a'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="navbar">
    <h1>Evidencija putnih naloga</h1>
    <div class="navbar-user">
        <?php if (isset($_SESSION['korisnik_id'])): ?>
            <a href="dashboard.php" class="nav-btn">Nadzorna ploča</a>
            <?php if ($_SESSION['uloga_id'] == 1): ?>
                <a href="admin_korisnici.php" class="nav-btn">Upravljaj profilima</a>
            <?php endif; ?>
            <span class="user-badge"><?= htmlspecialchars($_SESSION['ime'] . ' ' . $_SESSION['prezime']) ?> (<?= htmlspecialchars($_SESSION['uloga_naziv']) ?>)</span>
            <a href="logout.php" class="nav-btn nav-btn-logout">Odjava</a>
        <?php else: ?>
            <a href="login.php" class="nav-btn">Prijava</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>Politika privatnosti</h2>
        
        <p><strong>1. Opće odredbe</strong><br>
        Ova Politika privatnosti definira način na koji aplikacija "Evidencija putnih naloga" prikuplja, koristi, održava i štiti podatke prikupljene od korisnika unutar poslovnog sustava.</p>

        <p><strong>2. Prikupljanje osobnih podataka</strong><br>
        U svrhu izdavanja i obračuna službenih putnih naloga sukladno važećim poreznim propisima Republike Hrvatske, sustav obrađuje sljedeće podatke: ime i prezime zaposlenika, email adresu, strukturu nadređenih osoba, podatke o relacijama putovanja, kilometražu, troškove i priložene račune.</p>

        <p><strong>3. Svrha obrade podataka</strong><br>
        Prikupljeni podaci koriste se isključivo u svrhu:
        * Izrade zakonski pravovaljanih putnih naloga.
        * Odobravanja i obračuna dnevnica i putnih troškova.
        * Internog računovodstvenog izvještavanja i arhiviranja.</p>

        <p><strong>4. Sigurnost podataka</strong><br>
        Sustav primjenjuje tehničke i organizacijske mjere zaštite podataka, uključujući enkripciju korisničkih lozinki putem sigurne hash funkcije, zaštitu od SQL injection napada te restrikciju pristupa podacima isključivo ovlaštenim osobama (zaposlenik, nadređeni voditelj i administrator).</p>

        <p><strong>5. Razdoblje čuvanja podataka</strong><br>
        Podaci o putnim nalozima i priloženim računima čuvaju se unutar baze podataka u skladu sa zakonskim rokovima čuvanja računovodstvene dokumentacije u Republici Hrvatskoj.</p>
    </div>
</div>

<footer class="footer">
    <div class="footer-links">
        <a href="privatnost.php">Politika privatnosti</a> | 
        <a href="uvjeti.php">Uvjeti korištenja</a>
    </div>
    <div>Evidencija putnih naloga &copy; 2026</div>
</footer>

</body>
</html>