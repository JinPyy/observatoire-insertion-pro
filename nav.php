<?php $page = basename($_SERVER['PHP_SELF']); ?>
<nav>
    <span class="logo">Observatoire IP</span>
    <a href="index.php" class="<?= $page === 'index.php' ? 'active' : '' ?>">Tableau de bord</a>
    <a href="formation.php" class="<?= $page === 'formation.php' ? 'active' : '' ?>">Formations</a>
    <a href="etablissement.php" class="<?= $page === 'etablissement.php' ? 'active' : '' ?>">Établissements</a>
    <a href="comparaison.php" class="<?= $page === 'comparaison.php' ? 'active' : '' ?>">Comparaison</a>
    <a href="stats.php" class="<?= $page === 'stats.php' ? 'active' : '' ?>">Requêtes SQL</a>
</nav>
