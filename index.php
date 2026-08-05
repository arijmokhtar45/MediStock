<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();
require 'includes/ia_engine.php';

$page_titre = 'Dashboard';

// --- Exécution des automatisations IA (Background check) ---
verifierPrevisionsIA($pdo);

// --- Statistiques principales ---
$totalMedicaments = $pdo->query("SELECT COUNT(*) n FROM medicaments WHERE actif = 1")->fetch()['n'];

$stockFaible = $pdo->query("SELECT COUNT(*) n FROM v_stock_medicaments WHERE stock_faible = 1")->fetch()['n'];

$expirationProche = $pdo->query("
    SELECT COUNT(*) n FROM lots
    WHERE statut = 'actif' AND date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetch()['n'];

$ventesJour = $pdo->query("
    SELECT COALESCE(SUM(montant_total),0) total FROM ventes WHERE DATE(date_vente) = CURDATE()
")->fetch()['total'];

$ventesMois = $pdo->query("
    SELECT COALESCE(SUM(montant_total),0) total FROM ventes
    WHERE MONTH(date_vente) = MONTH(CURDATE()) AND YEAR(date_vente) = YEAR(CURDATE())
")->fetch()['total'];

// --- Ventes des 7 derniers jours (pour le graphique) ---
$stmt = $pdo->query("
    SELECT DATE(date_vente) AS jour, SUM(montant_total) AS total
    FROM ventes
    WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(date_vente)
    ORDER BY jour
");
$ventes7j = $stmt->fetchAll();
$labelsJours = [];
$dataVentes = [];
for ($i = 6; $i >= 0; $i--) {
    $jour = date('Y-m-d', strtotime("-$i day"));
    $labelsJours[] = date('d/m', strtotime($jour));
    $trouve = 0;
    foreach ($ventes7j as $v) {
        if ($v['jour'] === $jour) { $trouve = (float) $v['total']; break; }
    }
    $dataVentes[] = $trouve;
}

// --- Alertes récentes ---
$alertesRecentes = $pdo->query("
    SELECT * FROM alertes WHERE statut = 'active' ORDER BY date_creation DESC LIMIT 5
")->fetchAll();

// --- Médicaments les plus vendus (30 derniers jours) ---
$topMedicaments = $pdo->query("
    SELECT m.nom, SUM(dv.quantite) AS total_vendu
    FROM details_ventes dv
    JOIN medicaments m ON m.id = dv.medicament_id
    JOIN ventes v ON v.id = dv.vente_id
    WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY m.id, m.nom
    ORDER BY total_vendu DESC
    LIMIT 5
")->fetchAll();

require 'includes/header.php';
?>

<h3 class="mb-4">Bienvenue, <?= htmlspecialchars($_SESSION['nom']) ?> 👋</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-medicaments">
            <div class="small">Médicaments actifs</div>
            <div class="stat-value"><?= $totalMedicaments ?></div>
            <i class="bi bi-capsule"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-faible">
            <div class="small">Stock faible</div>
            <div class="stat-value"><?= $stockFaible ?></div>
            <i class="bi bi-exclamation-triangle"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-expiration">
            <div class="small">Expirent dans 30j</div>
            <div class="stat-value"><?= $expirationProche ?></div>
            <i class="bi bi-hourglass-split"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-ventes">
            <div class="small">Ventes du jour</div>
            <div class="stat-value"><?= number_format($ventesJour, 3) ?> DT</div>
            <i class="bi bi-cash-coin"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card p-3">
            <h6 class="mb-3">Ventes des 7 derniers jours</h6>
            <canvas id="chartVentes" height="90"></canvas>
        </div>

        <div class="card p-3 mt-3">
            <h6 class="mb-3">Top 5 médicaments vendus (30 jours)</h6>
            <table class="table table-sm mb-0">
                <thead><tr><th>Médicament</th><th class="text-end">Quantité vendue</th></tr></thead>
                <tbody>
                <?php if (!$topMedicaments): ?>
                    <tr><td colspan="2" class="text-muted text-center">Aucune vente enregistrée</td></tr>
                <?php endif; ?>
                <?php foreach ($topMedicaments as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['nom']) ?></td>
                        <td class="text-end"><?= $t['total_vendu'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card p-3">
            <h6 class="mb-3">Alertes récentes</h6>
            <?php if (!$alertesRecentes): ?>
                <p class="text-muted small">Aucune alerte active 🎉</p>
            <?php endif; ?>
            <?php foreach ($alertesRecentes as $a): ?>
                <div class="alert alert-warning py-2 small mb-2">
                    <?= htmlspecialchars($a['message']) ?>
                </div>
            <?php endforeach; ?>
            <a href="alertes.php" class="btn btn-sm btn-outline-primary w-100">Voir toutes les alertes</a>
        </div>

        <div class="card p-3 mt-3">
            <h6 class="mb-2">Ce mois-ci</h6>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Total des ventes</span>
                <strong><?= number_format($ventesMois, 3) ?> DT</strong>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartVentes'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labelsJours) ?>,
        datasets: [{
            label: 'Ventes (DT)',
            data: <?= json_encode($dataVentes) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: { plugins: { legend: { display: false } } }
});
</script>

<?php require 'includes/footer.php'; ?>
