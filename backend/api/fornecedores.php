<?php
/**
 * api/fornecedores.php
 *
 * Rotas (todas requerem autenticação):
 *   GET    /api/fornecedores.php        → listar todos
 *   GET    /api/fornecedores.php/{id}   → buscar por ID
 *   POST   /api/fornecedores.php        → criar
 *   PUT    /api/fornecedores.php/{id}   → editar
 *   DELETE /api/fornecedores.php/{id}   → excluir
 *
 * Tabela: fornecedor (id_fornecedor, nome, cnpj, contato)
 *
 * Compatível com o frontend:
 *   ApiFornecedores.listar()   → GET /api/fornecedores.php
 *   ApiFornecedores.criar()    → POST
 *   ApiFornecedores.atualizar()→ PUT /{id}
 *   ApiFornecedores.deletar()  → DELETE /{id}
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

// Todas as rotas de fornecedores exigem login
requireAuth();

$method = getMethod();
$id     = getUrlId();   // null se não houver ID na URL

// ────────────────────────────────────────────────────────────
//  GET /api/fornecedores.php          → listar todos
//  GET /api/fornecedores.php/{id}     → buscar um
// ────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $pdo = getConnection();

        if ($id !== null) {
            // ── Busca por ID ──────────────────────────────
            $stmt = $pdo->prepare(
                'SELECT id_fornecedor, nome, cnpj, contato, telefone, status
                   FROM fornecedor
                  WHERE id_fornecedor = ?
                  LIMIT 1'
            );
            $stmt->execute([$id]);
            $fornecedor = $stmt->fetch();

            if (!$fornecedor) {
                respondError("Fornecedor #{$id} não encontrado.", 404);
            }

            respondOk($fornecedor);

        } else {
            // ── Lista completa ────────────────────────────
            // Suporte a filtro por status via query string: ?status=ativo
            $status       = $_GET['status'] ?? null;
            $searchTerm   = $_GET['q']      ?? null;

            $sql    = 'SELECT id_fornecedor, nome, cnpj, contato, telefone, status FROM fornecedor WHERE 1=1';
            $params = [];

            if ($status !== null) {
                $sql     .= ' AND status = ?';
                $params[] = $status;
            }

            if ($searchTerm !== null) {
                $sql     .= ' AND (nome LIKE ? OR cnpj LIKE ? OR contato LIKE ?)';
                $like     = '%' . $searchTerm . '%';
                $params   = array_merge($params, [$like, $like, $like]);
            }

            $sql .= ' ORDER BY nome ASC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $lista = $stmt->fetchAll();

            respondOk($lista, count($lista) . ' fornecedor(es) encontrado(s).');
        }

    } catch (PDOException $e) {
        respondError('Erro ao buscar fornecedores: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  POST /api/fornecedores.php  →  criar fornecedor
// ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body     = getBody();
    $nome     = sanitizeString($body['nome']     ?? '');
    $cnpj     = sanitizeString($body['cnpj']     ?? '');
    $contato  = sanitizeString($body['contato']  ?? '');
    $telefone = sanitizeString($body['telefone'] ?? '');
    $status   = in_array($body['status'] ?? 'ativo', ['ativo','inativo'])
                ? $body['status'] : 'ativo';

    // ── Validações ────────────────────────────────────────
    $errors = [];
    if (empty($nome))    $errors[] = 'Nome é obrigatório.';
    if (empty($cnpj))    $errors[] = 'CNPJ é obrigatório.';
    if (empty($contato)) $errors[] = 'Contato (e-mail) é obrigatório.';

    if (!empty($contato) && !filter_var($contato, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail de contato inválido.';
    }

    // CNPJ: remove formatação e valida comprimento
    $cnpjRaw = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpjRaw) !== 14) {
        $errors[] = 'CNPJ inválido (deve ter 14 dígitos).';
    }

    if (!empty($errors)) {
        respondError(implode(' ', $errors), 422);
    }

    try {
        $pdo = getConnection();

        // Duplicidade de CNPJ
        $check = $pdo->prepare('SELECT id_fornecedor FROM fornecedor WHERE cnpj = ? LIMIT 1');
        $check->execute([$cnpjRaw]);
        if ($check->fetch()) {
            respondError('CNPJ já cadastrado.', 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO fornecedor (nome, cnpj, contato, telefone, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nome, $cnpjRaw, $contato, $telefone, $status]);

        $novoId = (int) $pdo->lastInsertId();

        respondCreated([
            'id_fornecedor' => $novoId,
            'nome'          => $nome,
            'cnpj'          => $cnpj,    // devolve formatado
            'contato'       => $contato,
            'telefone'      => $telefone,
            'status'        => $status,
        ], 'Fornecedor criado com sucesso.');

    } catch (PDOException $e) {
        respondError('Erro ao criar fornecedor: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  PUT /api/fornecedores.php/{id}  →  atualizar
// ────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if ($id === null) {
        respondError('Informe o ID do fornecedor na URL. Ex.: /api/fornecedores.php/5', 400);
    }

    $body     = getBody();
    $nome     = sanitizeString($body['nome']     ?? '');
    $cnpj     = sanitizeString($body['cnpj']     ?? '');
    $contato  = sanitizeString($body['contato']  ?? '');
    $telefone = sanitizeString($body['telefone'] ?? '');
    $status   = in_array($body['status'] ?? '', ['ativo','inativo'])
                ? $body['status'] : null;

    $errors = [];
    if (empty($nome))    $errors[] = 'Nome é obrigatório.';
    if (empty($cnpj))    $errors[] = 'CNPJ é obrigatório.';
    if (empty($contato)) $errors[] = 'Contato (e-mail) é obrigatório.';

    if (!empty($contato) && !filter_var($contato, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail de contato inválido.';
    }

    $cnpjRaw = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpjRaw) !== 14) {
        $errors[] = 'CNPJ inválido.';
    }

    if (!empty($errors)) {
        respondError(implode(' ', $errors), 422);
    }

    try {
        $pdo = getConnection();

        // Verifica existência
        $check = $pdo->prepare('SELECT id_fornecedor FROM fornecedor WHERE id_fornecedor = ? LIMIT 1');
        $check->execute([$id]);
        if (!$check->fetch()) {
            respondError("Fornecedor #{$id} não encontrado.", 404);
        }

        // CNPJ duplicado (ignora o próprio registro)
        $dupCheck = $pdo->prepare(
            'SELECT id_fornecedor FROM fornecedor WHERE cnpj = ? AND id_fornecedor != ? LIMIT 1'
        );
        $dupCheck->execute([$cnpjRaw, $id]);
        if ($dupCheck->fetch()) {
            respondError('CNPJ já pertence a outro fornecedor.', 409);
        }

        $sql    = 'UPDATE fornecedor SET nome=?, cnpj=?, contato=?, telefone=?';
        $params = [$nome, $cnpjRaw, $contato, $telefone];

        if ($status !== null) {
            $sql     .= ', status=?';
            $params[] = $status;
        }

        $sql     .= ' WHERE id_fornecedor=?';
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        respondOk([
            'id_fornecedor' => $id,
            'nome'          => $nome,
            'cnpj'          => $cnpj,
            'contato'       => $contato,
            'telefone'      => $telefone,
            'status'        => $status,
        ], 'Fornecedor atualizado com sucesso.');

    } catch (PDOException $e) {
        respondError('Erro ao atualizar fornecedor: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  DELETE /api/fornecedores.php/{id}  →  excluir
// ────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($id === null) {
        respondError('Informe o ID do fornecedor na URL.', 400);
    }

    try {
        $pdo = getConnection();

        // Verifica existência
        $check = $pdo->prepare('SELECT id_fornecedor FROM fornecedor WHERE id_fornecedor = ? LIMIT 1');
        $check->execute([$id]);
        if (!$check->fetch()) {
            respondError("Fornecedor #{$id} não encontrado.", 404);
        }

        // Verifica se há produtos vinculados
        $prodCheck = $pdo->prepare(
            'SELECT COUNT(*) AS total FROM produto WHERE id_fornecedor = ?'
        );
        $prodCheck->execute([$id]);
        $count = (int) $prodCheck->fetchColumn();

        if ($count > 0) {
            respondError(
                "Não é possível excluir: fornecedor possui {$count} produto(s) vinculado(s). " .
                "Remova ou transfira os produtos antes.",
                409
            );
        }

        $stmt = $pdo->prepare('DELETE FROM fornecedor WHERE id_fornecedor = ?');
        $stmt->execute([$id]);

        respondOk(['id_fornecedor' => $id], 'Fornecedor excluído com sucesso.');

    } catch (PDOException $e) {
        respondError('Erro ao excluir fornecedor: ' . $e->getMessage(), 500);
    }
}

// Método não suportado
respondError('Método não permitido.', 405);
