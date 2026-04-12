<?php
/**
 * api/usuarios.php
 *
 * Rotas:
 *   POST   /api/usuarios.php          { "action": "cadastrar", nome, email, senha }
 *   POST   /api/usuarios.php          { "action": "login",     email, senha       }
 *   GET    /api/usuarios.php          → dados do usuário logado (/me)
 *   POST   /api/usuarios.php          { "action": "logout"                        }
 *
 * Tabela: usuario (id_usuario, nome, email, senha_hash)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

$method = getMethod();

// ────────────────────────────────────────────────────────────
//  GET /api/usuarios.php  →  /me  (retorna usuário logado)
// ────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $userId = requireAuth();

    try {
        $pdo  = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nome, email FROM usuario WHERE id_usuario = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            // Sessão aponta para usuário inexistente — limpa e rejeita
            session_destroy();
            respondError('Usuário não encontrado.', 404);
        }

        respondOk($user, 'Usuário autenticado.');

    } catch (PDOException $e) {
        respondError('Erro ao buscar usuário: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  POST /api/usuarios.php
//  Decide pela "action" enviada no corpo JSON
// ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = getBody();
    $action = $body['action'] ?? '';

    // ── POST /cadastrar ──────────────────────────────────────
    if ($action === 'cadastrar') {
        $nome  = sanitizeString($body['nome']  ?? '');
        $email = sanitizeString($body['email'] ?? '');
        $senha = $body['senha'] ?? '';

        // Validações
        if (empty($nome) || strlen($nome) < 3) {
            respondError('O nome deve ter pelo menos 3 caracteres.');
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respondError('E-mail inválido.');
        }
        if (empty($senha) || strlen($senha) < 6) {
            respondError('A senha deve ter pelo menos 6 caracteres.');
        }

        try {
            $pdo = getConnection();

            // Verifica duplicidade de e-mail
            $check = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch()) {
                respondError('Este e-mail já está cadastrado.', 409);
            }

            // Hash SHA-256 conforme especificado
            $senhaHash = hash('sha256', $senha);

            $stmt = $pdo->prepare(
                'INSERT INTO usuario (nome, email, senha_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([$nome, $email, $senhaHash]);

            $novoId = (int) $pdo->lastInsertId();

            respondCreated(
                ['id_usuario' => $novoId, 'nome' => $nome, 'email' => $email],
                'Conta criada com sucesso.'
            );

        } catch (PDOException $e) {
            respondError('Erro ao cadastrar: ' . $e->getMessage(), 500);
        }
    }

    // ── POST /login ──────────────────────────────────────────
    if ($action === 'login') {
        $email = sanitizeString($body['email'] ?? '');
        $senha = $body['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            respondError('E-mail e senha são obrigatórios.');
        }

        try {
            $pdo  = getConnection();
            $stmt = $pdo->prepare(
                'SELECT id_usuario, nome, email, senha_hash FROM usuario WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Compara hash SHA-256
            if (!$user || $user['senha_hash'] !== hash('sha256', $senha)) {
                // Mensagem genérica para não revelar qual campo está errado
                respondError('E-mail ou senha incorretos.', 401);
            }

            // Regenera ID de sessão para evitar session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id_usuario'];
            $_SESSION['user_nome'] = $user['nome'];

            // Não retorna senha_hash ao cliente
            unset($user['senha_hash']);

            respondOk($user, 'Login realizado com sucesso.');

        } catch (PDOException $e) {
            respondError('Erro ao autenticar: ' . $e->getMessage(), 500);
        }
    }

    // ── POST /logout ─────────────────────────────────────────
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        respondOk(null, 'Logout realizado com sucesso.');
    }

    // Action desconhecida
    if (!in_array($action, ['cadastrar', 'login', 'logout'])) {
        respondError("Action inválida: '{$action}'. Use: cadastrar, login ou logout.", 400);
    }
}

// Método não permitido
respondError('Método não permitido.', 405);
