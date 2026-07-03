<?php
/**
 * Script de vérification post-déploiement.
 * Accès : https://votre-domaine/deploy_check.php
 * Supprimez ce fichier en production après validation.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'php_version' => PHP_VERSION,
    'config_readable' => is_readable(__DIR__ . '/config.php'),
    'generator_readable' => is_readable(__DIR__ . '/generator.php'),
    'data_writable' => is_writable(__DIR__ . '/data') || @mkdir(__DIR__ . '/data', 0755, true),
    'index_ok' => false,
    'scenario_ok' => false,
];

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/generator.php';
    $scenario = generateScenario();
    $public = buildPublicScenario($scenario);
    $checks['index_ok'] = true;
    $checks['scenario_ok'] = isset($public['scenario_id'], $public['weapons_pool'])
        && !isset($public['culprit']);
} catch (Throwable $e) {
    $checks['error'] = $e->getMessage();
}

$checks['status'] = ($checks['index_ok'] && $checks['scenario_ok']) ? 'ok' : 'error';
http_response_code($checks['status'] === 'ok' ? 200 : 500);
echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);