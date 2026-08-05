<?php
/**
 * MediStock IA Engine - Automated Stock Prediction and Alerting
 */

if (!function_exists('regression_lineaire')) {
    function regression_lineaire(array $x, array $y): array {
        $n = count($x);
        if ($n < 2) {
            return ['pente' => 0, 'origine' => $y[0] ?? 0, 'r2' => null];
        }

        $sommeX  = array_sum($x);
        $sommeY  = array_sum($y);
        $sommeXY = 0;
        $sommeX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sommeXY += $x[$i] * $y[$i];
            $sommeX2 += $x[$i] * $x[$i];
        }

        $denominateur = ($n * $sommeX2 - $sommeX * $sommeX);
        if ($denominateur == 0) {
            return ['pente' => 0, 'origine' => $sommeY / $n, 'r2' => null];
        }

        $pente   = ($n * $sommeXY - $sommeX * $sommeY) / $denominateur;
        $origine = ($sommeY - $pente * $sommeX) / $n;

        $moyenneY = $sommeY / $n;
        $ssTot = 0;
        $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $prediction = $pente * $x[$i] + $origine;
            $ssTot += ($y[$i] - $moyenneY) ** 2;
            $ssRes += ($y[$i] - $prediction) ** 2;
        }
        $r2 = $ssTot > 0 ? max(0, 1 - ($ssRes / $ssTot)) : null;

        return ['pente' => $pente, 'origine' => $origine, 'r2' => $r2];
    }
}

function verifierPrevisionsIA($pdo) {
    $nb_mois = 6;
    $jours_par_mois = 30;
    $seuil_alerte_jours = 30;
    
    // Récupération des médicaments actifs
    $medicaments = $pdo->query("
        SELECT m.id, m.nom, COALESCE(v.quantite_totale,0) AS stock_actuel
        FROM medicaments m
        LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
        WHERE m.actif = 1
    ")->fetchAll();

    $debutMoisCourant = (new DateTime('first day of this month'))->format('Y-m-d');
    $debutFenetre = (new DateTime("first day of -$nb_mois month"))->format('Y-m-d');

    // Ventes agrégées par mois
    $stmt = $pdo->prepare("
        SELECT dv.medicament_id, DATE_FORMAT(v.date_vente, '%Y-%m') AS mois, SUM(dv.quantite) AS quantite
        FROM details_ventes dv
        JOIN ventes v ON v.id = dv.vente_id
        WHERE v.date_vente >= ? AND v.date_vente < ?
        GROUP BY dv.medicament_id, mois
    ");
    $stmt->execute([$debutFenetre, $debutMoisCourant]);
    $ventesParMois = [];
    foreach ($stmt->fetchAll() as $row) {
        $ventesParMois[$row['medicament_id']][$row['mois']] = (int) $row['quantite'];
    }

    $moisCles = [];
    for ($i = $nb_mois; $i >= 1; $i--) {
        $moisCles[] = (new DateTime("first day of -$i month"))->format('Y-m-d');
    }

    foreach ($medicaments as $m) {
        $serie = [];
        for ($i = $nb_mois; $i >= 1; $i--) {
            $cle = (new DateTime("first day of -$i month"))->format('Y-m');
            $serie[] = $ventesParMois[$m['id']][$cle] ?? 0;
        }

        $x = range(0, count($serie) - 1);
        $reg = regression_lineaire($x, $serie);
        
        $indexMoisProchain = count($serie);
        $previsionMoisProchain = max(0, round($reg['pente'] * $indexMoisProchain + $reg['origine']));
        
        $consommationJournaliere = $previsionMoisProchain / $jours_par_mois;
        $joursAvantRupture = $consommationJournaliere > 0 
            ? (int) floor($m['stock_actuel'] / $consommationJournaliere) 
            : null;

        if ($joursAvantRupture !== null && $joursAvantRupture <= $seuil_alerte_jours) {
            $dejaActive = $pdo->prepare("SELECT COUNT(*) AS n FROM alertes WHERE medicament_id = ? AND type_alerte = 'rupture_prevue_ia' AND statut = 'active'");
            $dejaActive->execute([$m['id']]);
            if ($dejaActive->fetch()['n'] == 0) {
                $pdo->prepare("INSERT INTO alertes (type_alerte, medicament_id, message) VALUES ('rupture_prevue_ia', ?, ?)")
                    ->execute([$m['id'], "{$m['nom']} risque une rupture dans {$joursAvantRupture} jours (prévision IA)."]);
            }
        } else {
            // Auto-traiter si le risque est écarté
            $pdo->prepare("UPDATE alertes SET statut = 'traitee', date_traitement = NOW() 
                           WHERE medicament_id = ? AND type_alerte = 'rupture_prevue_ia' AND statut = 'active'")
                ->execute([$m['id']]);
        }
    }
}
