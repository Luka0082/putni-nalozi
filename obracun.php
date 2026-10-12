<?php
function izracunajObracun($nalog, $ostali_troskovi = []) {
    // Neoporezivi iznosi u RH
    $PUNA_DNEVNICA = 30.00; // EUR
    $POLA_DNEVNICE = 15.00; // EUR
    $NKN_PO_KM = 0.50;     // EUR/km za privatni automobil

    // 1. Izračun vremena i sati na putu
    $start = new DateTime($nalog['datum_polaska'] . ' ' . $nalog['vrijeme_polaska']);
    $end   = new DateTime($nalog['datum_povratka'] . ' ' . $nalog['vrijeme_povratka']);
    $interval = $start->diff($end);

    $ukupno_sati = ($interval->days * 24) + $interval->h + ($interval->i / 60);

    // Određivanje broja punih i poludnevnica
    $pune_dnevnice_broj = floor($ukupno_sati / 24);
    $preostali_sati = $ukupno_sati % 24;

    $pola_dnevnice_broj = 0;
    if ($preostali_sati >= 12) {
        $pune_dnevnice_broj += 1;
    } elseif ($preostali_sati >= 8) {
        $pola_dnevnice_broj += 1;
    }

    // Osnovni iznos dnevnica prije umanjenja
    $bruto_dnevnice = ($pune_dnevnice_broj * $PUNA_DNEVNICA) + ($pola_dnevnice_broj * $POLA_DNEVNICE);

    // 2. Umanjenje za besplatne obroke (Doručak -20%, Ručak -30%, Večera -30%)
    $postotak_umanjenja = 0;
    if (!empty($nalog['osiguran_dorucak'])) $postotak_umanjenja += 0.20;
    if (!empty($nalog['osiguran_rucak']))   $postotak_umanjenja += 0.30;
    if (!empty($nalog['osiguran_veceri']))  $postotak_umanjenja += 0.30;

    $neto_dnevnice = $bruto_dnevnice * (1 - $postotak_umanjenja);
    if ($neto_dnevnice < 0) $neto_dnevnice = 0;

    // 3. Obračun kilometraže
    $prijedeno_km = 0;
    if (!empty($nalog['zavrsna_km']) && !empty($nalog['pocetna_km']) && $nalog['zavrsna_km'] > $nalog['pocetna_km']) {
        $prijedeno_km = $nalog['zavrsna_km'] - $nalog['pocetna_km'];
    }
    $iznos_km = $prijedeno_km * $NKN_PO_KM;

    // 4. Zbroj ostalih troškova (cestarina, smještaj, parking...)
    $iznos_ostali_troskovi = 0;
    foreach ($ostali_troskovi as $trosak) {
        $iznos_ostali_troskovi += (float)$trosak['iznos'];
    }

    // 5. Ukupni trošak i završna razlika s akontacijom
    $ukupni_trosak = $neto_dnevnice + $iznos_km + $iznos_ostali_troskovi;
    $akontacija = (float)($nalog['akontacija'] ?? 0);
    $saldo = $ukupni_trosak - $akontacija;

    return [
        'ukupno_sati'           => round($ukupno_sati, 1),
        'pune_dnevnice_broj'    => $pune_dnevnice_broj,
        'pola_dnevnice_broj'    => $pola_dnevnice_broj,
        'neto_dnevnice'         => round($neto_dnevnice, 2),
        'prijedeno_km'          => $prijedeno_km,
        'iznos_km'              => round($iznos_km, 2),
        'iznos_ostali_troskovi' => round($iznos_ostali_troskovi, 2),
        'ukupni_trosak'         => round($ukupni_trosak, 2),
        'akontacija'            => round($akontacija, 2),
        'saldo'                 => round($saldo, 2)
    ];
}