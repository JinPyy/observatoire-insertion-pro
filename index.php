<?php
require_once 'connexion.php';
$page = basename($_SERVER['PHP_SELF']);

// Indicateurs nationaux (dernière année, 18 mois)
$sql = "SELECT 
    ROUND(AVG(r.taux_emploi),1) as moy_emploi,
    ROUND(AVG(r.taux_cdi),1) as moy_cdi,
    ROUND(AVG(r.taux_cadre),1) as moy_cadre,
    ROUND(AVG(r.salaire_median),0) as moy_salaire,
    COUNT(DISTINCT r.id_diplome) as nb_formations,
    SUM(r.nb_repondants) as total_repondants
FROM RESULTAT_IP r
JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
WHERE a.annee = (SELECT MAX(annee) FROM ANNEE_ENQUETE WHERE delai_mois = 18)
  AND a.delai_mois = 18";
$indic = $pdo->query($sql)->fetch();

// Top 5 disciplines par taux d'emploi
$top5 = $pdo->query("
    SELECT d.nom, ROUND(AVG(r.taux_emploi),1) as te, ROUND(AVG(r.salaire_median),0) as sm
    FROM DISCIPLINE d
    JOIN DIPLOME dp ON d.id_disc = dp.id_disc
    JOIN RESULTAT_IP r ON dp.id_diplome = r.id_diplome
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.annee = (SELECT MAX(annee) FROM ANNEE_ENQUETE WHERE delai_mois = 18) AND a.delai_mois = 18
    GROUP BY d.id_disc, d.nom
    ORDER BY te DESC LIMIT 5
")->fetchAll();

// Top 5 formations (indice attractivité)
$topFormations = $pdo->query("
    SELECT dp.intitule, e.nom as etab,
        ROUND(AVG(r.taux_emploi)*0.4 + AVG(r.taux_cdi)*0.2 + AVG(r.taux_cadre)*0.2 + (AVG(r.salaire_median)/50)*0.2, 1) as indice
    FROM RESULTAT_IP r
    JOIN DIPLOME dp ON r.id_diplome = dp.id_diplome
    JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.delai_mois = 18
    GROUP BY dp.id_diplome, dp.intitule, e.nom
    ORDER BY indice DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observatoire IP — Tableau de bord</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Tableau de bord national</h1>
    <p class="subtitle">Indicateurs clés de l'insertion professionnelle — dernière enquête à 18 mois</p>

    <div class="cards">
        <div class="card">
            <div class="label">Taux d'emploi moyen</div>
            <div class="value"><?= $indic['moy_emploi'] ?><span class="unit">%</span></div>
        </div>
        <div class="card">
            <div class="label">Taux CDI moyen</div>
            <div class="value"><?= $indic['moy_cdi'] ?><span class="unit">%</span></div>
        </div>
        <div class="card">
            <div class="label">Taux cadre moyen</div>
            <div class="value"><?= $indic['moy_cadre'] ?><span class="unit">%</span></div>
        </div>
        <div class="card">
            <div class="label">Salaire médian</div>
            <div class="value"><?= fmt_sal($indic['moy_salaire']) ?></div>
        </div>
        <div class="card">
            <div class="label">Formations suivies</div>
            <div class="value"><?= $indic['nb_formations'] ?></div>
        </div>
        <div class="card">
            <div class="label">Répondants</div>
            <div class="value"><?= number_format($indic['total_repondants'], 0, ',', ' ') ?></div>
        </div>
    </div>

    <h2>Top 5 disciplines — Taux d'emploi</h2>
    <table>
        <thead><tr><th>#</th><th>Discipline</th><th>Taux emploi</th><th>Salaire médian</th></tr></thead>
        <tbody>
        <?php foreach ($top5 as $i => $row): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($row['nom']) ?></td>
            <td><strong><?= $row['te'] ?>%</strong></td>
            <td><?= fmt_sal($row['sm']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Top 5 formations — Indice d'attractivité</h2>
    <table>
        <thead><tr><th>#</th><th>Formation</th><th>Établissement</th><th>Indice</th></tr></thead>
        <tbody>
        <?php foreach ($topFormations as $i => $row): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><a class="link" href="formation.php?id=<?= $i+1 ?>"><?= htmlspecialchars($row['intitule']) ?></a></td>
            <td><?= htmlspecialchars($row['etab']) ?></td>
            <td><strong><?= $row['indice'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
