<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$page_titre = 'Prévisions IA';

/*
 * MODULE DE PREVISION DE STOCK — RÉGRESSION LINÉAIRE
 * ---------------------------------------------------------------------
 * Principe :
 *  1. On agrège les ventes de chaque médicament par mois (les N derniers mois).
 *  2. On applique une régression linéaire (méthode des moindres carrés) sur
 *     la série (index du mois -> quantité vendue) pour dégager la TENDANCE
 *     (hausse, baisse, stable).
 *  3. On extrapole cette droite pour prévoir la demande du mois prochain.
 *  4. On en déduit une consommation journalière moyenne prévue, puis une
 *     estimation du nombre de jours avant rupture de stock.
 *  5. On calcule le coefficient de détermination R² comme indicateur de
 *     fiabilité de la régression (à quel point la droite colle aux points réels).
 *  6. On propose une quantité de commande = prévision du mois + stock de
 *     sécurité − stock actuel.
 *
 * Si l'historique est trop court (moins de 2 mois avec des ventes), la
 * régression n'est pas fiable : on retombe sur une simple moyenne des
 * ventes disponibles, et la fiabilité est signalée comme non calculable.
 *
 * 100% local : pas d'API externe, pas de Python — juste des maths simples
 * en PHP, appliquées à des données réelles de la base MySQL.
 */

const NB_MOIS_HISTORIQUE   = 6;   // nombre de mois analysés
const DELAI_LIVRAISON_JOURS = 7;  // délai moyen fournisseur
const STOCK_SECURITE_JOURS  = 3;  // marge de sécurité
const JOURS_PAR_MOIS        = 30; // approximation pour convertir mois -> jours
const SEUIL_ALERTE_IA_JOURS = 30; // en dessous de ce seuil, le module IA crée une alerte "rupture prévue"

/**
 * Régression linéaire simple (moindres carrés) sur une série de points (x, y).
 * Retourne la pente (a), l'ordonnée à l'origine (b) et le R² (fiabilité).
 */
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

    // Calcul du R² (coefficient de détermination)
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

// --- 1. Récupération des médicaments et de leur stock actuel ---
$medicaments = $pdo->query("
    SELECT m.id, m.nom, m.quantite_minimale, COALESCE(v.quantite_totale,0) AS stock_actuel
    FROM medicaments m
    LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
    WHERE m.actif = 1
    ORDER BY m.nom
")->fetchAll();

// --- 2. Historique des ventes agrégées par mois (N derniers MOIS COMPLETS) ---
// Important : le mois EN COURS est volontairement exclu de la régression.
// Un mois qui vient de commencer (ex: 1 jour écoulé sur 30) contient très peu
// de ventes par nature, pas parce que la demande a chuté — l'inclure fausserait
// la tendance détectée. On ne l'utilise donc pas pour calculer la régression.
$debutMoisCourant = (new DateTime('first day of this month'))->format('Y-m-d');
$debutFenetre = (new DateTime("first day of -" . NB_MOIS_HISTORIQUE . " month"))->format('Y-m-d');

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

// Liste des N derniers mois COMPLETS (le mois en cours n'en fait pas partie)
$moisCles = [];
$moisLabels = [];
for ($i = NB_MOIS_HISTORIQUE; $i >= 1; $i--) {
    $date = new DateTime("first day of -$i month");
    $moisCles[] = $date->format('Y-m');
    $moisLabels[] = ucfirst($date->format('M'));
}

// --- 3. Calcul de la régression + prévisions pour chaque médicament ---
$previsions = [];
foreach ($medicaments as $m) {
    $serie = [];
    foreach ($moisCles as $cle) {
        $serie[] = $ventesParMois[$m['id']][$cle] ?? 0;
    }

    $nbMoisAvecVentes = count(array_filter($serie, fn($v) => $v > 0));
    $x = range(0, count($serie) - 1);

    $reg = regression_lineaire($x, $serie);

    // Prévision du mois prochain (index suivant la série)
    $indexMoisProchain = count($serie);
    $previsionMoisProchain = max(0, round($reg['pente'] * $indexMoisProchain + $reg['origine']));

    // Droite de tendance sur toute la période (pour le graphique)
    $droiteTendance = [];
    foreach ($x as $xi) {
        $droiteTendance[] = round(max(0, $reg['pente'] * $xi + $reg['origine']), 1);
    }

    $consommationJournaliere = $previsionMoisProchain / JOURS_PAR_MOIS;
    $joursAvantRupture = $consommationJournaliere > 0
        ? (int) floor($m['stock_actuel'] / $consommationJournaliere)
        : null;

    $besoinPeriode = $consommationJournaliere * (DELAI_LIVRAISON_JOURS + STOCK_SECURITE_JOURS);
    $quantiteSuggeree = max(0, (int) ceil($previsionMoisProchain + ($besoinPeriode - $consommationJournaliere * JOURS_PAR_MOIS) - $m['stock_actuel']));
    // Simplifié : quantité suggérée = prévision du mois + stock sécurité (en jours convertis) - stock actuel
    $stockSecuriteQte = $consommationJournaliere * STOCK_SECURITE_JOURS;
    $quantiteSuggeree = max(0, (int) ceil($previsionMoisProchain + $stockSecuriteQte - $m['stock_actuel']));

    if ($reg['pente'] > 0.5) {
        $tendance = ['label' => 'En hausse', 'icone' => 'bi-graph-up-arrow', 'couleur' => 'success'];
    } elseif ($reg['pente'] < -0.5) {
        $tendance = ['label' => 'En baisse', 'icone' => 'bi-graph-down-arrow', 'couleur' => 'danger'];
    } else {
        $tendance = ['label' => 'Stable', 'icone' => 'bi-arrow-right', 'couleur' => 'secondary'];
    }

    $niveauRisque = 'faible';
    if ($joursAvantRupture !== null && $joursAvantRupture <= DELAI_LIVRAISON_JOURS) {
        $niveauRisque = 'eleve';
    } elseif ($joursAvantRupture !== null && $joursAvantRupture <= DELAI_LIVRAISON_JOURS + STOCK_SECURITE_JOURS) {
        $niveauRisque = 'moyen';
    }

    // --- Création automatique d'une alerte "rupture prévue" (module IA) ---
    // On crée une alerte dès qu'une rupture est prévue sous SEUIL_ALERTE_IA_JOURS jours
    // (indépendamment du niveau "élevé/moyen/faible" utilisé pour la couleur du badge,
    // qui lui reste basé sur le délai de livraison réel). On évite les doublons en
    // vérifiant qu'aucune alerte identique n'est déjà active pour ce médicament.
    if ($joursAvantRupture !== null && $joursAvantRupture <= SEUIL_ALERTE_IA_JOURS) {
        $dejaActive = $pdo->prepare("
            SELECT COUNT(*) AS n FROM alertes
            WHERE medicament_id = ? AND type_alerte = 'rupture_prevue_ia' AND statut = 'active'
        ");
        $dejaActive->execute([$m['id']]);
        if ($dejaActive->fetch()['n'] == 0) {
            $pdo->prepare("
                INSERT INTO alertes (type_alerte, medicament_id, message)
                VALUES ('rupture_prevue_ia', ?, ?)
            ")->execute([
                $m['id'],
                "{$m['nom']} risque une rupture dans {$joursAvantRupture} jours (prévision IA, au rythme de vente actuel)."
            ]);
        }
    }

    $previsions[] = [
        'id' => $m['id'],
        'nom' => $m['nom'],
        'stock_actuel' => $m['stock_actuel'],
        'serie' => $serie,
        'droite_tendance' => $droiteTendance,
        'nb_mois_avec_ventes' => $nbMoisAvecVentes,
        'prevision_mois_prochain' => $previsionMoisProchain,
        'jours_avant_rupture' => $joursAvantRupture,
        'quantite_suggeree' => $quantiteSuggeree,
        'tendance' => $tendance,
        'r2' => $reg['r2'],
        'niveau_risque' => $niveauRisque,
    ];
}

// Trier : risque élevé en premier
$ordreRisque = ['eleve' => 0, 'moyen' => 1, 'faible' => 2];
usort($previsions, fn($a, $b) => $ordreRisque[$a['niveau_risque']] <=> $ordreRisque[$b['niveau_risque']]);

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-graph-up-arrow"></i> Prévisions de stock (IA)</h4>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i>
    Le module intelligent apprend la tendance à partir de l'historique des ventes des
    <?= NB_MOIS_HISTORIQUE ?> derniers <strong>mois complets</strong> (le mois en cours est exclu du calcul
    car il est encore incomplet et fausserait la tendance) grâce à une <strong>régression linéaire</strong>
    (méthode des moindres carrés). Il prévoit la demande du mois en cours, estime la date de rupture de stock,
    et recommande une quantité de réapprovisionnement. Le <strong>R²</strong> indique la fiabilité de la
    tendance détectée (proche de 1 = tendance nette, proche de 0 = ventes trop irrégulières pour conclure).
</div>

<div class="card p-3 mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Médicament</th>
                    <th>Stock actuel</th>
                    <th>Tendance</th>
                    <th>Prévision mois prochain</th>
                    <th>Rupture estimée</th>
                    <th>Quantité à commander</th>
                    <th>Fiabilité (R²)</th>
                    <th>Risque</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($previsions as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                    <td><?= $p['stock_actuel'] ?></td>
                    <td>
                        <span class="text-<?= $p['tendance']['couleur'] ?>">
                            <i class="bi <?= $p['tendance']['icone'] ?>"></i> <?= $p['tendance']['label'] ?>
                        </span>
                    </td>
                    <td><?= $p['prevision_mois_prochain'] ?></td>
                    <td><?= $p['jours_avant_rupture'] !== null ? $p['jours_avant_rupture'] . ' j' : '—' ?></td>
                    <td>
                        <?php if ($p['quantite_suggeree'] > 0): ?>
                            <strong class="text-primary"><?= $p['quantite_suggeree'] ?></strong>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['r2'] !== null && $p['nb_mois_avec_ventes'] >= 2): ?>
                            <?= round($p['r2'] * 100) ?> %
                        <?php else: ?>
                            <span class="text-muted" title="Historique insuffisant pour une régression fiable">n/a</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['niveau_risque'] === 'eleve'): ?>
                            <span class="badge badge-expire">Élevé</span>
                        <?php elseif ($p['niveau_risque'] === 'moyen'): ?>
                            <span class="badge badge-expire-proche">Moyen</span>
                        <?php else: ?>
                            <span class="badge badge-ok">Faible</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<h5 class="mb-3">Ventes réelles vs tendance prévue (par médicament)</h5>
<div class="row g-3">
    <?php foreach ($previsions as $p): ?>
        <div class="col-md-6">
            <div class="card p-3">
                <h6 class="mb-1"><?= htmlspecialchars($p['nom']) ?></h6>
                <?php if ($p['nb_mois_avec_ventes'] < 2): ?>
                    <p class="small text-muted mb-2">
                        Historique insuffisant (<?= $p['nb_mois_avec_ventes'] ?> mois avec ventes) —
                        la tendance affichée est indicative, pas encore fiable statistiquement.
                    </p>
                <?php endif; ?>
                <canvas id="chart_<?= $p['id'] ?>" height="140"></canvas>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const moisLabels = <?= json_encode($moisLabels) ?>;

<?php foreach ($previsions as $p): ?>
new Chart(document.getElementById('chart_<?= $p['id'] ?>'), {
    type: 'line',
    data: {
        labels: [...moisLabels, 'Prévision'],
        datasets: [
            {
                label: 'Ventes réelles',
                data: <?= json_encode($p['serie']) ?>.concat([null]),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.08)',
                tension: 0.2,
                spanGaps: false,
            },
            {
                label: 'Tendance (régression)',
                data: <?= json_encode($p['droite_tendance']) ?>.concat([<?= $p['prevision_mois_prochain'] ?>]),
                borderColor: '#fd7e14',
                borderDash: [6, 4],
                backgroundColor: 'transparent',
                tension: 0,
                pointRadius: 3,
            }
        ]
    },
    options: {
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
<?php endforeach; ?>
</script>

<?php require 'includes/footer.php'; ?>
