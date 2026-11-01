<?php
require_once 'connexion.php';

// Liste des formations pour le sélecteur
$formations = $pdo->query("
    SELECT dp.id_diplome, dp.intitule, e.nom as etab, d.nom as disc
    FROM DIPLOME dp
    JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
    JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
    ORDER BY d.nom, dp.intitule
")->fetchAll();

$selected = isset($_GET['id']) ? (int)$_GET['id'] : null;
$info = null;
$resultats = [];
$moyennes_disc = [];

if ($selected) {
    // Infos formation
    $stmt = $pdo->prepare("
        SELECT dp.id_diplome, dp.intitule, dp.niveau, e.nom as etab, e.ville,
               d.nom as disc, d.id_disc, reg.nom as region
        FROM DIPLOME dp
        JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
        JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
        JOIN REGION reg ON e.id_region = reg.id_region
        WHERE dp.id_diplome = ?
    ");
    $stmt->execute([$selected]);
    $info = $stmt->fetch();

    if ($info) {
        // Résultats par année
        $stmt = $pdo->prepare("
            SELECT a.annee, a.delai_mois, r.taux_emploi, r.taux_cdi, r.taux_cadre,
                   r.salaire_median, r.delai_emploi, r.nb_repondants
            FROM RESULTAT_IP r
            JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
            WHERE r.id_diplome = ?
            ORDER BY a.annee, a.delai_mois
        ");
        $stmt->execute([$selected]);
        $resultats = $stmt->fetchAll();

        // Moyennes discipline par année (pour comparaison)
        $stmt = $pdo->prepare("
            SELECT a.annee, a.delai_mois,
                   ROUND(AVG(r.taux_emploi),1) as moy_emploi,
                   ROUND(AVG(r.taux_cdi),1) as moy_cdi,
                   ROUND(AVG(r.taux_cadre),1) as moy_cadre,
                   ROUND(AVG(r.salaire_median),0) as moy_salaire
            FROM RESULTAT_IP r
            JOIN DIPLOME dp ON r.id_diplome = dp.id_diplome
            JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
            WHERE dp.id_disc = ?
            GROUP BY a.annee, a.delai_mois
            ORDER BY a.annee, a.delai_mois
        ");
        $stmt->execute([$info['id_disc']]);
        foreach ($stmt->fetchAll() as $m) {
            $moyennes_disc[$m['annee'] . '_' . $m['delai_mois']] = $m;
        }
    }
}

function compareBadge($val, $moy) {
    if ($val === null || $moy === null) return '';
    if ($val >= $moy) return '<span class="badge badge-up">▲ au-dessus</span>';
    return '<span class="badge badge-down">▼ en dessous</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche formation — Observatoire IP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Fiche formation comparative</h1>
    <p class="subtitle">Sélectionnez une formation pour voir ses indicateurs comparés à la moyenne de la discipline</p>

    <form method="get">
        <div>
            <label for="id">Formation</label>
            <select name="id" id="id">
                <option value="">-- Choisir --</option>
                <?php foreach ($formations as $f): ?>
                <option value="<?= $f['id_diplome'] ?>" <?= $selected == $f['id_diplome'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['intitule'] . ' — ' . $f['etab']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Afficher</button>
    </form>

    <?php if ($info): ?>
    <div class="cards">
        <div class="card">
            <div class="label">Formation</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['intitule']) ?></div>
        </div>
        <div class="card">
            <div class="label">Établissement</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['etab']) ?></div>
        </div>
        <div class="card">
            <div class="label">Discipline</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['disc']) ?></div>
        </div>
        <div class="card">
            <div class="label">Région</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['region']) ?></div>
        </div>
    </div>

    <h2>Résultats par année — comparaison à la moyenne <?= htmlspecialchars($info['disc']) ?></h2>
    <?php if (empty($resultats)): ?>
        <p class="empty">Aucune enquête enregistrée pour cette formation.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Année</th>
                <th>Délai</th>
                <th>Taux emploi</th>
                <th>vs moy.</th>
                <th>Taux CDI</th>
                <th>vs moy.</th>
                <th>Taux cadre</th>
                <th>vs moy.</th>
                <th>Salaire</th>
                <th>vs moy.</th>
                <th>Répondants</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($resultats as $r):
            $key = $r['annee'] . '_' . $r['delai_mois'];
            $m = $moyennes_disc[$key] ?? null;
        ?>
            <tr>
                <td><strong><?= $r['annee'] ?></strong></td>
                <td><?= $r['delai_mois'] ?> mois</td>
                <td><?= $r['taux_emploi'] ?>%</td>
                <td><?= $m ? compareBadge($r['taux_emploi'], $m['moy_emploi']) : '' ?></td>
                <td><?= $r['taux_cdi'] ?>%</td>
                <td><?= $m ? compareBadge($r['taux_cdi'], $m['moy_cdi']) : '' ?></td>
                <td><?= $r['taux_cadre'] ?>%</td>
                <td><?= $m ? compareBadge($r['taux_cadre'], $m['moy_cadre']) : '' ?></td>
                <td><?= fmt_sal($r['salaire_median']) ?></td>
                <td><?= $m ? compareBadge($r['salaire_median'], $m['moy_salaire']) : '' ?></td>
                <td><?= $r['nb_repondants'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php elseif ($selected): ?>
        <p class="empty">Formation introuvable.</p>
    <?php endif; ?>
</div>
</body>
</html>
