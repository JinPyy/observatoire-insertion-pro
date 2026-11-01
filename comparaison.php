<?php
require_once 'connexion.php';

// Listes pour les sélecteurs
$formations = $pdo->query("
    SELECT dp.id_diplome, dp.intitule, e.nom as etab
    FROM DIPLOME dp JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
    ORDER BY dp.intitule
")->fetchAll();

$etablissements = $pdo->query("
    SELECT id_etab, nom, ville FROM ETABLISSEMENT ORDER BY nom
")->fetchAll();

$mode = $_GET['mode'] ?? 'formations';
$id1 = isset($_GET['id1']) ? (int)$_GET['id1'] : null;
$id2 = isset($_GET['id2']) ? (int)$_GET['id2'] : null;

$data1 = $data2 = null;
$res1 = $res2 = [];

if ($id1 && $id2) {
    if ($mode === 'formations') {
        // Comparer 2 formations
        $sql_info = "SELECT dp.id_diplome as id, dp.intitule as nom, e.nom as detail, d.nom as disc
                     FROM DIPLOME dp
                     JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
                     JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
                     WHERE dp.id_diplome = ?";
        $sql_res = "SELECT a.annee, a.delai_mois, r.taux_emploi, r.taux_cdi, r.taux_cadre, r.salaire_median
                    FROM RESULTAT_IP r
                    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
                    WHERE r.id_diplome = ? AND a.delai_mois = 18
                    ORDER BY a.annee";
    } else {
        // Comparer 2 établissements
        $sql_info = "SELECT e.id_etab as id, e.nom, e.ville as detail, r.nom as disc
                     FROM ETABLISSEMENT e JOIN REGION r ON e.id_region = r.id_region
                     WHERE e.id_etab = ?";
        $sql_res = "SELECT a.annee, a.delai_mois,
                        ROUND(AVG(r.taux_emploi),1) as taux_emploi,
                        ROUND(AVG(r.taux_cdi),1) as taux_cdi,
                        ROUND(AVG(r.taux_cadre),1) as taux_cadre,
                        ROUND(AVG(r.salaire_median),0) as salaire_median
                    FROM RESULTAT_IP r
                    JOIN DIPLOME dp ON r.id_diplome = dp.id_diplome
                    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
                    WHERE dp.id_etab = ? AND a.delai_mois = 18
                    GROUP BY a.annee, a.delai_mois
                    ORDER BY a.annee";
    }

    $stmt = $pdo->prepare($sql_info);
    $stmt->execute([$id1]);
    $data1 = $stmt->fetch();
    $stmt->execute([$id2]);
    $data2 = $stmt->fetch();

    $stmt = $pdo->prepare($sql_res);
    $stmt->execute([$id1]);
    $res1 = $stmt->fetchAll();
    $stmt->execute([$id2]);
    $res2 = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparaison — Observatoire IP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Comparaison côte à côte</h1>
    <p class="subtitle">Comparez deux formations ou deux établissements</p>

    <form method="get">
        <div>
            <label>Mode</label>
            <select name="mode" onchange="this.form.submit()">
                <option value="formations" <?= $mode === 'formations' ? 'selected' : '' ?>>Formations</option>
                <option value="etablissements" <?= $mode === 'etablissements' ? 'selected' : '' ?>>Établissements</option>
            </select>
        </div>
        <div>
            <label>Élément 1</label>
            <select name="id1">
                <option value="">-- Choisir --</option>
                <?php if ($mode === 'formations'):
                    foreach ($formations as $f): ?>
                    <option value="<?= $f['id_diplome'] ?>" <?= $id1 == $f['id_diplome'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['intitule'] . ' — ' . $f['etab']) ?>
                    </option>
                <?php endforeach; else:
                    foreach ($etablissements as $e): ?>
                    <option value="<?= $e['id_etab'] ?>" <?= $id1 == $e['id_etab'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nom'] . ' — ' . $e['ville']) ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div>
            <label>Élément 2</label>
            <select name="id2">
                <option value="">-- Choisir --</option>
                <?php if ($mode === 'formations'):
                    foreach ($formations as $f): ?>
                    <option value="<?= $f['id_diplome'] ?>" <?= $id2 == $f['id_diplome'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['intitule'] . ' — ' . $f['etab']) ?>
                    </option>
                <?php endforeach; else:
                    foreach ($etablissements as $e): ?>
                    <option value="<?= $e['id_etab'] ?>" <?= $id2 == $e['id_etab'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nom'] . ' — ' . $e['ville']) ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <button type="submit">Comparer</button>
    </form>

    <?php if ($data1 && $data2): ?>
    <div class="compare-grid">
        <!-- Colonne 1 -->
        <div>
            <div class="card" style="margin-bottom:1rem">
                <div class="label"><?= $mode === 'formations' ? 'Formation' : 'Établissement' ?> 1</div>
                <div class="value" style="font-size:1.1rem"><?= htmlspecialchars($data1['nom']) ?></div>
                <div class="unit"><?= htmlspecialchars($data1['detail']) ?></div>
            </div>
            <table>
                <thead><tr><th>Année</th><th>Emploi</th><th>CDI</th><th>Cadre</th><th>Salaire</th></tr></thead>
                <tbody>
                <?php foreach ($res1 as $r): ?>
                <tr>
                    <td><strong><?= $r['annee'] ?></strong></td>
                    <td><?= $r['taux_emploi'] ?>%</td>
                    <td><?= $r['taux_cdi'] ?>%</td>
                    <td><?= $r['taux_cadre'] ?>%</td>
                    <td><?= fmt_sal($r['salaire_median']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Colonne 2 -->
        <div>
            <div class="card" style="margin-bottom:1rem">
                <div class="label"><?= $mode === 'formations' ? 'Formation' : 'Établissement' ?> 2</div>
                <div class="value" style="font-size:1.1rem"><?= htmlspecialchars($data2['nom']) ?></div>
                <div class="unit"><?= htmlspecialchars($data2['detail']) ?></div>
            </div>
            <table>
                <thead><tr><th>Année</th><th>Emploi</th><th>CDI</th><th>Cadre</th><th>Salaire</th></tr></thead>
                <tbody>
                <?php foreach ($res2 as $r): ?>
                <tr>
                    <td><strong><?= $r['annee'] ?></strong></td>
                    <td><?= $r['taux_emploi'] ?>%</td>
                    <td><?= $r['taux_cdi'] ?>%</td>
                    <td><?= $r['taux_cadre'] ?>%</td>
                    <td><?= fmt_sal($r['salaire_median']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
