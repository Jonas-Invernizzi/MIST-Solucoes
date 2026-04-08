<?php
/**
 * Arquivo de Entrada da Aplicação
 * Ponto de acesso principal - redireciona para public/ ou API
 */

require_once __DIR__ . '/vendor/autoload.php';

// Configurações
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', BASE_PATH . '/public');

// Obter rota solicitada
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$baseName = basename(__DIR__);
$requestPath = str_replace('/' . $baseName, '', $requestUri);
$requestPath = $requestPath ?: '/';

// Redirecionar para arquivo público se existir
if ($requestPath === '/' || $requestPath === '') {
    include PUBLIC_PATH . '/index.html';
} else {
    $file = PUBLIC_PATH . $requestPath;
    if (file_exists($file) && is_file($file)) {
        $mimeType = mime_content_type($file) ?: 'text/plain';
        header('Content-Type: ' . $mimeType);
        readfile($file);
    } else {
        // Aqui você pode adicionar rotas de API no futuro
        http_response_code(404);
        echo json_encode(['error' => '404 Not Found']);
    }
}
