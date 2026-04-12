<?php
/**
 * config/helpers.php
 * Funções utilitárias compartilhadas por todas as APIs.
 */

// ── Sessão ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,      // true em produção com HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── CORS ──────────────────────────────────────────────────
/**
 * Libera CORS para qualquer origem (adequado para dev).
 * Em produção, substitua '*' pelo domínio do front-end.
 */
function setCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');   // cache preflight por 24 h

    // Responde ao preflight OPTIONS e encerra
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Respostas JSON ────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');

/**
 * Envia resposta JSON padronizada e encerra o script.
 *
 * @param bool   $success
 * @param mixed  $data     Dados retornados ao cliente
 * @param string $message  Mensagem legível
 * @param int    $code     HTTP status code
 */
function respond(bool $success, $data = null, string $message = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respondOk($data = null, string $message = 'OK'): void
{
    respond(true, $data, $message, 200);
}

function respondCreated($data = null, string $message = 'Criado com sucesso.'): void
{
    respond(true, $data, $message, 201);
}

function respondError(string $message = 'Erro interno.', int $code = 400, $data = null): void
{
    respond(false, $data, $message, $code);
}

// ── Body JSON ─────────────────────────────────────────────
/**
 * Lê e decodifica o corpo da requisição como JSON.
 * Retorna array associativo ou [] em caso de falha.
 */
function getBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// ── Autenticação ──────────────────────────────────────────
/**
 * Garante que o usuário está autenticado via sessão.
 * Retorna o id da sessão ou encerra com 401.
 */
function requireAuth(): int
{
    if (empty($_SESSION['user_id'])) {
        respondError('Não autenticado. Faça login para continuar.', 401);
    }
    return (int) $_SESSION['user_id'];
}

// ── Método HTTP ───────────────────────────────────────────
function getMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * Extrai o segmento de ID da URL.
 * Ex.: /api/fornecedores.php/42  →  42
 *      /api/fornecedores.php     →  null
 */
function getUrlId(): ?int
{
    // PATH_INFO é preenchido quando o servidor está configurado para isso.
    // Fallback: último segmento da REQUEST_URI.
    $pathInfo = $_SERVER['PATH_INFO']
        ?? parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    // Remove o nome do arquivo e pega o resto
    $segments = explode('/', trim($pathInfo, '/'));
    $last     = end($segments);

    return (is_numeric($last) && (int)$last > 0) ? (int)$last : null;
}

// ── Sanitização básica ────────────────────────────────────
function sanitizeString(string $value): string
{
    return trim(strip_tags($value));
}
