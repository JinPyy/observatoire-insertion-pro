<?php
require_once 'connexion.php';

// Liste établissements
$etabs = $pdo->query("
    SELECT e.id_etab, e.nom, e.type, e.ville, r.nom as region
    FROM ETABLISSEMENT e
    JOIN REGION r ON e.id_region = r.id_region
    ORDER BY e.nom
")->fetchAll();

$selected = isset($_GET['id']) ? (int)$_GET['id'] : null;
$info = null;
$diplomes = [];

if ($selected) {
    // Info établissement
    $stmt = $pdo->prepare("
        SELECT e.*, r.nom as region
        FROM ETABLISSEMENT e
        JOIN REGION r ON e.id_region = r.id_region
        WHERE e.id_etab = ?
    ");
    $stmt->execute([$selected]);
    $info = $stmt->fetch();

    if ($info) {
        // Diplômes avec derniers indicateurs + classement interne
        $stmt = $pdo->prepare("
            SELECT dp.id_diplome, dp.intitule, d.nom as disc,
                   r.taux_emploi, r.taux_cdi, r.taux_cadre, r.salaire_median, r.nb_repondants,
                   a.annee, a.delai_mois
            FROM DIPLOME dp
            JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
            LEFT JOIN RESULTAT_IP r ON dp.id_diplome = r.id_diplome
            LEFT JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
                AND a.annee = (SELECT MAX(a2.annee) FROM ANNEE_ENQUETE a2
                               JOIN RESULTAT_IP r2 ON a2.id_annee = r2.id_annee
                               WHERE r2.id_diplome = dp.id_diplome AND a2.delai_mois = 18)
                AND a.delai_mois = 18
            WHERE dp.id_etab = ?
            ORDER BY r.taux_emploi DESC
        ");
        $stmt->execute([$selected]);
        $diplomes = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche établissement — Observatoire IP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Fiche établissement</h1>
    <p class="subtitle">Toutes les formations d'un établissement avec classement interne</p>

    <form method="get">
        <div>
            <label for="id">Établissement</label>
            <select name="id" id="id">
                <option value="">-- Choisir --</option>
                <?php foreach ($etabs as $e): ?>
                <option value="<?= $e['id_etab'] ?>" <?= $selected == $e['id_etab'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['nom'] . ' — ' . $e['ville']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Afficher</button>
    </form>

    <?php if ($info): ?>
    <div class="cards">
        <div class="card">
            <div class="label">Établissement</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['nom']) ?></div>
        </div>
        <div class="card">
            <div class="label">Type</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['type']) ?></div>
        </div>
        <div class="card">
            <div class="label">Ville</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['ville']) ?></div>
        </div>
        <div class="card">
            <div class="label">Région</div>
            <div class="value" style="font-size:1.2rem"><?= htmlspecialchars($info['region']) ?></div>
        </div>
    </div>

    <h2>Formations — classement interne (dernière enquête 18 mois)</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Formation</th>
                <th>Discipline</th>
                <th>Taux emploi</th>
                <th>Taux CDI</th>
                <th>Taux cadre</th>
                <th>Salaire</th>
                <th>Répondants</th>
                <th>Année</th>
            </tr>
        </thead>
        <tbody>
        <?php $rank = 0; foreach ($diplomes as $d):
            $rank++;
            if ($d['taux_emploi'] === null) continue;
        ?>
            <tr>
                <td><?= $rank ?></td>
                <td><a class="link" href="formation.php?id=<?= $d['id_diplome'] ?>"><?= htmlspecialchars($d['intitule']) ?></a></td>
                <td><?= htmlspecialchars($d['disc']) ?></td>
                <td><strong><?= $d['taux_emploi'] ?>%</strong></td>
                <td><?= $d['taux_cdi'] ?>%</td>
                <td><?= $d['taux_cadre'] ?>%</td>
                <td><?= fmt_sal($d['salaire_median']) ?></td>
                <td><?= $d['nb_repondants'] ?></td>
                <td><?= $d['annee'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php elseif ($selected): ?>
        <p class="empty">Établissement introuvable.</p>
    <?php endif; ?>
</div>
</body>
</html>
