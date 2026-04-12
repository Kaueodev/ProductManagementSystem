<?php
/**
 * api/produtos.php
 *
 * Rotas (todas requerem autenticação):
 *   GET    /api/produtos.php           → listar (suporta ?categoria=&q=&status=)
 *   GET    /api/produtos.php/{id}      → buscar por ID (com JOIN fornecedor)
 *   POST   /api/produtos.php           → criar
 *   PUT    /api/produtos.php/{id}      → editar
 *   DELETE /api/produtos.php/{id}      → excluir
 *
 * Tabela: produto (id_produto, nome, preco, descricao, id_fornecedor, categoria, estoque, status)
 *
 * Compatível com o frontend:
 *   ApiProdutos.listar()    → GET /api/produtos.php
 *   ApiProdutos.criar()     → POST
 *   ApiProdutos.atualizar() → PUT /{id}
 *   ApiProdutos.deletar()   → DELETE /{id}
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

// Todas as rotas de produtos exigem login
requireAuth();

$method = getMethod();
$id     = getUrlId();

// ────────────────────────────────────────────────────────────
//  GET /api/produtos.php       → listar
//  GET /api/produtos.php/{id}  → detalhe
// ────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $pdo = getConnection();

        if ($id !== null) {
            // ── Busca por ID com dados do fornecedor ──────────
            $stmt = $pdo->prepare(
                'SELECT p.id_produto, p.nome, p.preco, p.descricao,
                        p.categoria, p.estoque, p.status,
                        p.id_fornecedor,
                        f.nome AS fornecedor
                   FROM produto p
                   LEFT JOIN fornecedor f ON f.id_fornecedor = p.id_fornecedor
                  WHERE p.id_produto = ?
                  LIMIT 1'
            );
            $stmt->execute([$id]);
            $produto = $stmt->fetch();

            if (!$produto) {
                respondError("Produto #{$id} não encontrado.", 404);
            }

            $produto['preco']   = (float)  $produto['preco'];
            $produto['estoque'] = (int)    $produto['estoque'];
            respondOk($produto);

        } else {
            // ── Lista completa com filtros opcionais ──────────
            $categoria  = $_GET['categoria']   ?? null;
            $status     = $_GET['status']      ?? null;
            $searchTerm = $_GET['q']           ?? null;
            $fornecedor = $_GET['fornecedor']  ?? null;    // por nome
            $orderBy    = $_GET['order']       ?? 'nome';

            $allowedOrders = ['nome','preco','estoque','categoria'];
            if (!in_array($orderBy, $allowedOrders)) $orderBy = 'nome';

            $sql = '
                SELECT p.id_produto, p.nome, p.preco, p.descricao,
                       p.categoria, p.estoque, p.status,
                       p.id_fornecedor,
                       f.nome AS fornecedor
                  FROM produto p
                  LEFT JOIN fornecedor f ON f.id_fornecedor = p.id_fornecedor
                 WHERE 1=1
            ';
            $params = [];

            if ($categoria !== null) {
                $sql     .= ' AND p.categoria = ?';
                $params[] = $categoria;
            }
            if ($status !== null) {
                $sql     .= ' AND p.status = ?';
                $params[] = $status;
            }
            if ($fornecedor !== null) {
                $sql     .= ' AND f.nome LIKE ?';
                $params[] = '%' . $fornecedor . '%';
            }
            if ($searchTerm !== null) {
                $sql     .= ' AND (p.nome LIKE ? OR p.categoria LIKE ? OR p.descricao LIKE ?)';
                $like     = '%' . $searchTerm . '%';
                $params   = array_merge($params, [$like, $like, $like]);
            }

            $sql .= " ORDER BY p.{$orderBy} ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $lista = $stmt->fetchAll();

            // Garante tipos corretos para o front
            $lista = array_map(function ($p) {
                $p['preco']   = (float) $p['preco'];
                $p['estoque'] = (int)   $p['estoque'];
                return $p;
            }, $lista);

            respondOk($lista, count($lista) . ' produto(s) encontrado(s).');
        }

    } catch (PDOException $e) {
        respondError('Erro ao buscar produtos: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  POST /api/produtos.php  →  criar produto
// ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body        = getBody();
    $nome        = sanitizeString($body['nome']        ?? '');
    $preco       = $body['preco']       ?? null;
    $descricao   = sanitizeString($body['descricao']   ?? '');
    $categoria   = sanitizeString($body['categoria']   ?? '');
    $estoque     = $body['estoque']     ?? 0;
    $fornecedorId= $body['id_fornecedor'] ?? ($body['fornecedor_id'] ?? null);
    $status      = in_array($body['status'] ?? 'ativo', ['ativo','inativo'])
                   ? $body['status'] : 'ativo';

    // ── Validações ────────────────────────────────────────
    $errors = [];
    if (empty($nome))           $errors[] = 'Nome do produto é obrigatório.';
    if ($preco === null || !is_numeric($preco) || (float)$preco < 0)
                                $errors[] = 'Preço inválido.';
    if (empty($categoria))      $errors[] = 'Categoria é obrigatória.';
    if (empty($fornecedorId))   $errors[] = 'Fornecedor é obrigatório.';
    if (!is_numeric($estoque) || (int)$estoque < 0)
                                $errors[] = 'Estoque inválido.';

    if (!empty($errors)) {
        respondError(implode(' ', $errors), 422);
    }

    try {
        $pdo = getConnection();

        // Verifica FK fornecedor
        $fCheck = $pdo->prepare('SELECT id_fornecedor FROM fornecedor WHERE id_fornecedor = ? LIMIT 1');
        $fCheck->execute([$fornecedorId]);
        if (!$fCheck->fetch()) {
            respondError("Fornecedor #{$fornecedorId} não encontrado.", 404);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO produto (nome, preco, descricao, categoria, estoque, status, id_fornecedor)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $nome,
            number_format((float)$preco, 2, '.', ''),
            $descricao,
            $categoria,
            (int)$estoque,
            $status,
            (int)$fornecedorId,
        ]);

        $novoId = (int) $pdo->lastInsertId();

        // Busca o registro recém-criado com JOIN para retornar nome do fornecedor
        $fetch = $pdo->prepare(
            'SELECT p.*, f.nome AS fornecedor
               FROM produto p
               LEFT JOIN fornecedor f ON f.id_fornecedor = p.id_fornecedor
              WHERE p.id_produto = ?'
        );
        $fetch->execute([$novoId]);
        $criado = $fetch->fetch();
        $criado['preco']   = (float) $criado['preco'];
        $criado['estoque'] = (int)   $criado['estoque'];

        respondCreated($criado, 'Produto criado com sucesso.');

    } catch (PDOException $e) {
        respondError('Erro ao criar produto: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  PUT /api/produtos.php/{id}  →  atualizar produto
// ────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if ($id === null) {
        respondError('Informe o ID do produto na URL. Ex.: /api/produtos.php/10', 400);
    }

    $body         = getBody();
    $nome         = sanitizeString($body['nome']        ?? '');
    $preco        = $body['preco']       ?? null;
    $descricao    = sanitizeString($body['descricao']   ?? '');
    $categoria    = sanitizeString($body['categoria']   ?? '');
    $estoque      = $body['estoque']     ?? null;
    $fornecedorId = $body['id_fornecedor'] ?? ($body['fornecedor_id'] ?? null);
    $status       = in_array($body['status'] ?? '', ['ativo','inativo'])
                    ? $body['status'] : null;

    $errors = [];
    if (empty($nome))         $errors[] = 'Nome do produto é obrigatório.';
    if ($preco === null || !is_numeric($preco) || (float)$preco < 0)
                              $errors[] = 'Preço inválido.';
    if (empty($categoria))    $errors[] = 'Categoria é obrigatória.';
    if (empty($fornecedorId)) $errors[] = 'Fornecedor é obrigatório.';

    if (!empty($errors)) {
        respondError(implode(' ', $errors), 422);
    }

    try {
        $pdo = getConnection();

        // Verifica existência do produto
        $check = $pdo->prepare('SELECT id_produto FROM produto WHERE id_produto = ? LIMIT 1');
        $check->execute([$id]);
        if (!$check->fetch()) {
            respondError("Produto #{$id} não encontrado.", 404);
        }

        // Verifica FK fornecedor
        $fCheck = $pdo->prepare('SELECT id_fornecedor FROM fornecedor WHERE id_fornecedor = ? LIMIT 1');
        $fCheck->execute([$fornecedorId]);
        if (!$fCheck->fetch()) {
            respondError("Fornecedor #{$fornecedorId} não encontrado.", 404);
        }

        $sql = '
            UPDATE produto
               SET nome=?, preco=?, descricao=?, categoria=?, id_fornecedor=?
        ';
        $params = [
            $nome,
            number_format((float)$preco, 2, '.', ''),
            $descricao,
            $categoria,
            (int)$fornecedorId,
        ];

        if ($estoque !== null && is_numeric($estoque)) {
            $sql     .= ', estoque=?';
            $params[] = (int)$estoque;
        }
        if ($status !== null) {
            $sql     .= ', status=?';
            $params[] = $status;
        }

        $sql     .= ' WHERE id_produto=?';
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Retorna o produto atualizado
        $fetch = $pdo->prepare(
            'SELECT p.*, f.nome AS fornecedor
               FROM produto p
               LEFT JOIN fornecedor f ON f.id_fornecedor = p.id_fornecedor
              WHERE p.id_produto = ?'
        );
        $fetch->execute([$id]);
        $atualizado = $fetch->fetch();
        $atualizado['preco']   = (float) $atualizado['preco'];
        $atualizado['estoque'] = (int)   $atualizado['estoque'];

        respondOk($atualizado, 'Produto atualizado com sucesso.');

    } catch (PDOException $e) {
        respondError('Erro ao atualizar produto: ' . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────
//  DELETE /api/produtos.php/{id}  →  excluir produto
// ────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($id === null) {
        respondError('Informe o ID do produto na URL.', 400);
    }

    try {
        $pdo = getConnection();

        $check = $pdo->prepare('SELECT id_produto FROM produto WHERE id_produto = ? LIMIT 1');
        $check->execute([$id]);
        if (!$check->fetch()) {
            respondError("Produto #{$id} não encontrado.", 404);
        }

        $stmt = $pdo->prepare('DELETE FROM produto WHERE id_produto = ?');
        $stmt->execute([$id]);

        respondOk(['id_produto' => $id], 'Produto excluído com sucesso.');

    } catch (PDOException $e) {
        respondError('Erro ao excluir produto: ' . $e->getMessage(), 500);
    }
}

// Método não suportado
respondError('Método não permitido.', 405);
