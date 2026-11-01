<?php
require_once 'connexion.php';

$annees = $pdo->query("SELECT DISTINCT annee FROM ANNEE_ENQUETE ORDER BY annee")->fetchAll(PDO::FETCH_COLUMN);
$selected_annee = isset($_GET['annee']) ? (int)$_GET['annee'] : max($annees);
$show = $_GET['r'] ?? null;

// ============ R1 ============
$r1 = $pdo->query("
    SELECT d.nom AS discipline, ROUND(AVG(r.taux_cadre),1) AS taux_cadre_moyen
    FROM DISCIPLINE d
    JOIN DIPLOME dp ON d.id_disc = dp.id_disc
    JOIN RESULTAT_IP r ON dp.id_diplome = r.id_diplome
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.delai_mois = 18
    GROUP BY d.nom
    ORDER BY taux_cadre_moyen DESC
")->fetchAll();

// ============ R2 ============
$stmt2 = $pdo->prepare("
    SELECT e.nom AS etablissement, d.nom AS discipline, ROUND(AVG(r.salaire_median),0) AS salaire_moyen
    FROM ETABLISSEMENT e
    JOIN DIPLOME dp ON e.id_etab = dp.id_etab
    JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
    JOIN RESULTAT_IP r ON dp.id_diplome = r.id_diplome
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.annee = ? AND a.delai_mois = 18
    AND r.salaire_median > (
        SELECT AVG(r2.salaire_median)
        FROM RESULTAT_IP r2
        JOIN DIPLOME dp2 ON r2.id_diplome = dp2.id_diplome
        JOIN ANNEE_ENQUETE a2 ON r2.id_annee = a2.id_annee
        WHERE dp2.id_disc = d.id_disc AND a2.annee = ? AND a2.delai_mois = 18
    )
    GROUP BY e.nom, d.nom
    ORDER BY salaire_moyen DESC
");
$stmt2->execute([$selected_annee, $selected_annee]);
$r2 = $stmt2->fetchAll();

// ============ R3 ============
$r3 = $pdo->query("
    SELECT d.nom AS discipline, a.annee, ROUND(AVG(r.taux_cdi),1) AS taux_cdi_moyen
    FROM DISCIPLINE d
    JOIN DIPLOME dp ON d.id_disc = dp.id_disc
    JOIN RESULTAT_IP r ON dp.id_diplome = r.id_diplome
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.delai_mois = 18
    AND a.annee >= (SELECT MAX(annee) - 4 FROM ANNEE_ENQUETE)
    GROUP BY d.nom, a.annee
    ORDER BY d.nom, a.annee
")->fetchAll();

// ============ R4 ============
$r4 = $pdo->query("
    SELECT DISTINCT d.nom AS discipline
    FROM DISCIPLINE d
    JOIN DIPLOME dp ON d.id_disc = dp.id_disc
    JOIN RESULTAT_IP r1 ON dp.id_diplome = r1.id_diplome
    JOIN ANNEE_ENQUETE a1 ON r1.id_annee = a1.id_annee
    JOIN RESULTAT_IP r2 ON dp.id_diplome = r2.id_diplome
    JOIN ANNEE_ENQUETE a2 ON r2.id_annee = a2.id_annee
    JOIN RESULTAT_IP r3 ON dp.id_diplome = r3.id_diplome
    JOIN ANNEE_ENQUETE a3 ON r3.id_annee = a3.id_annee
    WHERE a1.delai_mois = 18 AND a2.delai_mois = 18 AND a3.delai_mois = 18
    AND a2.annee = a1.annee + 1
    AND a3.annee = a2.annee + 1
    AND r2.taux_emploi < r1.taux_emploi
    AND r3.taux_emploi < r2.taux_emploi
")->fetchAll();

// ============ R5 ============
$stmt5 = $pdo->prepare("
    SELECT dp.intitule AS diplome, e.nom AS etablissement, d.nom AS discipline,
           r.taux_cadre, r.salaire_median
    FROM RESULTAT_IP r
    JOIN DIPLOME dp ON r.id_diplome = dp.id_diplome
    JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
    JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.annee = ? AND a.delai_mois = 18
    AND r.taux_cadre > 70
    AND r.salaire_median > (
        SELECT AVG(r2.salaire_median)
        FROM RESULTAT_IP r2
        JOIN ANNEE_ENQUETE a2 ON r2.id_annee = a2.id_annee
        WHERE a2.annee = ? AND a2.delai_mois = 18
    )
    ORDER BY r.salaire_median DESC
");
$stmt5->execute([$selected_annee, $selected_annee]);
$r5 = $stmt5->fetchAll();

// ============ R6 ============
$r6 = $pdo->query("
    SELECT reg.nom AS region,
           ROUND(AVG(r.taux_emploi),1) AS taux_emploi_moyen,
           COUNT(DISTINCT dp.id_diplome) AS nb_diplomes,
           ROUND(AVG(r.salaire_median),0) AS salaire_moyen
    FROM REGION reg
    JOIN ETABLISSEMENT e ON reg.id_region = e.id_region
    JOIN DIPLOME dp ON e.id_etab = dp.id_etab
    JOIN RESULTAT_IP r ON dp.id_diplome = r.id_diplome
    JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
    WHERE a.annee = (SELECT MAX(annee) FROM ANNEE_ENQUETE WHERE delai_mois = 18)
      AND a.delai_mois = 18
    GROUP BY reg.nom
    ORDER BY taux_emploi_moyen DESC
")->fetchAll();

// ============ R7 ============
$r7 = $pdo->query("
    SELECT dp.intitule AS diplome, e.nom AS etablissement, d.nom AS discipline
    FROM DIPLOME dp
    JOIN ETABLISSEMENT e ON dp.id_etab = e.id_etab
    JOIN DISCIPLINE d ON dp.id_disc = d.id_disc
    LEFT JOIN (
        SELECT r.id_diplome
        FROM RESULTAT_IP r
        JOIN ANNEE_ENQUETE a ON r.id_annee = a.id_annee
        WHERE a.annee >= (SELECT MAX(annee) - 2 FROM ANNEE_ENQUETE)
    ) recent ON dp.id_diplome = recent.id_diplome
    WHERE recent.id_diplome IS NULL
    ORDER BY d.nom, dp.intitule
")->fetchAll();

// ============ R8 ============
$r8 = $pdo->query("
    SELECT d.nom AS discipline,
           ROUND(AVG(r30.taux_emploi),1) AS taux_30,
           ROUND(AVG(r18.taux_emploi),1) AS taux_18,
           ROUND(AVG(r30.taux_emploi) - AVG(r18.taux_emploi),1) AS gain
    FROM DISCIPLINE d
    JOIN DIPLOME dp ON d.id_disc = dp.id_disc
    JOIN RESULTAT_IP r18 ON dp.id_diplome = r18.id_diplome
    JOIN ANNEE_ENQUETE a18 ON r18.id_annee = a18.id_annee
    JOIN RESULTAT_IP r30 ON dp.id_diplome = r30.id_diplome
    JOIN ANNEE_ENQUETE a30 ON r30.id_annee = a30.id_annee
    WHERE a18.delai_mois = 18 AND a30.delai_mois = 30 AND a18.annee = a30.annee
    GROUP BY d.nom
    ORDER BY gain DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requêtes SQL — Observatoire IP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Requêtes SQL interactives</h1>
    <p class="subtitle">Les 8 requêtes obligatoires du projet</p>

    <form method="get" style="margin-bottom:0.5rem">
        <div>
            <label>Année (pour R2, R5)</label>
            <select name="annee">
                <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $selected_annee == $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Actualiser</button>
    </form>

    <!-- R1 -->
    <div class="requete-section">
        <h3>R1 — Disciplines avec meilleur taux cadre</h3>
        <p class="desc">Agrégation + ORDER BY — Taux d'emploi cadre moyen à 18 mois, toutes années confondues.</p>
        <table>
            <thead><tr><th>#</th><th>Discipline</th><th>Taux cadre moyen</th></tr></thead>
            <tbody>
            <?php foreach ($r1 as $i => $row): ?>
            <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($row['discipline']) ?></td><td><strong><?= $row['taux_cadre_moyen'] ?>%</strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- R2 -->
    <div class="requete-section">
        <h3>R2 — Établissements au-dessus de la moyenne (<?= $selected_annee ?>)</h3>
        <p class="desc">Sous-requête scalaire corrélée — Salaire médian > moyenne nationale de la discipline.</p>
        <table>
            <thead><tr><th>Établissement</th><th>Discipline</th><th>Salaire moyen</th></tr></thead>
            <tbody>
            <?php foreach ($r2 as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['etablissement']) ?></td>
                <td><?= htmlspecialchars($row['discipline']) ?></td>
                <td><strong><?= fmt_sal($row['salaire_moyen']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($r2)): ?><tr><td colspan="3" class="empty">Aucun résultat</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- R3 -->
    <div class="requete-section">
        <h3>R3 — Évolution CDI par discipline</h3>
        <p class="desc">Agrégation + GROUP BY discipline + ORDER BY année — 5 dernières années, 18 mois.</p>
        <table>
            <thead><tr><th>Discipline</th><th>Année</th><th>Taux CDI moyen</th></tr></thead>
            <tbody>
            <?php $prev = ''; foreach ($r3 as $row):
                $bold = ($row['discipline'] !== $prev);
                $prev = $row['discipline'];
            ?>
            <tr>
                <td><?= $bold ? '<strong>'.htmlspecialchars($row['discipline']).'</strong>' : '' ?></td>
                <td><?= $row['annee'] ?></td>
                <td><?= $row['taux_cdi_moyen'] ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- R4 -->
    <div class="requete-section">
        <h3>R4 — Disciplines en déclin continu</h3>
        <p class="desc">Sous-requête corrélée + comparaison temporelle — Baisse 2 années consécutives.</p>
        <table>
            <thead><tr><th>Discipline en déclin</th></tr></thead>
            <tbody>
            <?php foreach ($r4 as $row): ?>
            <tr><td class="down"><?= htmlspecialchars($row['discipline']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($r4)): ?><tr><td class="empty">Aucune discipline en déclin continu détectée</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- R5 -->
    <div class="requete-section">
        <h3>R5 — Double critère cadre et salaire (<?= $selected_annee ?>)</h3>
        <p class="desc">Jointure + condition multi-critères — Taux cadre > 70% ET salaire > médiane nationale.</p>
        <table>
            <thead><tr><th>Diplôme</th><th>Établissement</th><th>Discipline</th><th>Taux cadre</th><th>Salaire</th></tr></thead>
            <tbody>
            <?php foreach ($r5 as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['diplome']) ?></td>
                <td><?= htmlspecialchars($row['etablissement']) ?></td>
                <td><?= htmlspecialchars($row['discipline']) ?></td>
                <td><strong><?= $row['taux_cadre'] ?>%</strong></td>
                <td><?= fmt_sal($row['salaire_median']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($r5)): ?><tr><td colspan="5" class="empty">Aucun résultat</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- R6 -->
    <div class="requete-section">
        <h3>R6 — Régions les mieux insérées</h3>
        <p class="desc">Agrégation + GROUP BY région — Dernière année, 18 mois.</p>
        <table>
            <thead><tr><th>#</th><th>Région</th><th>Taux emploi</th><th>Nb diplômes</th><th>Salaire moyen</th></tr></thead>
            <tbody>
            <?php foreach ($r6 as $i => $row): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($row['region']) ?></td>
                <td><strong><?= $row['taux_emploi_moyen'] ?>%</strong></td>
                <td><?= $row['nb_diplomes'] ?></td>
                <td><?= fmt_sal($row['salaire_moyen']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- R7 -->
    <div class="requete-section">
        <h3>R7 — Diplômes sans enquête récente</h3>
        <p class="desc">LEFT JOIN + IS NULL — Aucune enquête depuis 3 ans.</p>
        <table>
            <thead><tr><th>Diplôme</th><th>Établissement</th><th>Discipline</th></tr></thead>
            <tbody>
            <?php foreach ($r7 as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['diplome']) ?></td>
                <td><?= htmlspecialchars($row['etablissement']) ?></td>
                <td><?= htmlspecialchars($row['discipline']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($r7)): ?><tr><td colspan="3" class="empty">Tous les diplômes ont des enquêtes récentes</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- R8 -->
    <div class="requete-section">
        <h3>R8 — Gain d'insertion 18 vs 30 mois</h3>
        <p class="desc">Jointure sur delai_mois + calcul écart — Vitesse d'insertion par discipline.</p>
        <table>
            <thead><tr><th>Discipline</th><th>Emploi 30 mois</th><th>Emploi 18 mois</th><th>Gain</th></tr></thead>
            <tbody>
            <?php foreach ($r8 as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['discipline']) ?></td>
                <td><?= $row['taux_30'] ?>%</td>
                <td><?= $row['taux_18'] ?>%</td>
                <td class="up"><strong>+<?= $row['gain'] ?> pts</strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
