<?php
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'projet_ip';
$user = 'VOTRE_UTILISATEUR';
$password = 'VOTRE_MOT_DE_PASSE';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

function fmt_sal($v) {
    if ($v === null || $v === '' || $v == 0) return '<span style="color:#a0aec0">N/A</span>';
    return number_format($v, 0, ',', ' ') . ' €';
}
