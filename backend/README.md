# GestProd — Backend PHP

Backend PHP puro (sem frameworks) para o sistema de gestão de produtos.
Utiliza PDO + prepared statements, sessões PHP e retorna JSON padronizado.

---

## 📁 Estrutura

```
backend/
├── .htaccess               ← Rewrite rules (Apache)
├── config/
│   ├── database.php        ← Conexão PDO (configure aqui)
│   └── helpers.php         ← CORS, respond(), requireAuth(), getBody()
├── api/
│   ├── usuarios.php        ← /api/usuarios   (cadastrar · login · me · logout)
│   ├── fornecedores.php    ← /api/fornecedores (CRUD completo)
│   └── produtos.php        ← /api/produtos    (CRUD completo)
└── sql/
    └── schema.sql          ← CREATE TABLE + dados de exemplo
```

---

## ⚙️ Configuração

### 1. Banco de dados

```sql
-- No terminal MySQL:
mysql -u root -p < sql/schema.sql

-- Ou copie e cole o conteúdo de schema.sql no phpMyAdmin.
```

### 2. Credenciais

Abra `config/database.php` e ajuste:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestprod');
define('DB_USER', 'root');
define('DB_PASS', 'SUA_SENHA');
```

### 3. Servidor local

**XAMPP / WAMP:**
- Coloque a pasta `backend/` em `htdocs/` ou `www/`
- Acesse: `http://localhost/backend/api/usuarios.php`

**PHP embutido (desenvolvimento):**
```bash
cd backend
php -S localhost:3000
```

**Nginx:** configure `try_files` e `fastcgi_split_path_info` para suportar PATH_INFO.

---

## 🔌 Conectar com o Front-end

No arquivo `assets/js/api.js` do front-end, altere:

```js
const API_BASE = 'http://localhost/backend/api';  // ou http://localhost:3000/api
```

---

## 📡 Referência das APIs

### Formato padrão de resposta

```json
{
  "success": true,
  "data": { ... },
  "message": "Mensagem legível"
}
```

---

### `POST /api/usuarios.php` — Cadastrar

```json
// Body:
{ "action": "cadastrar", "nome": "João", "email": "j@x.com", "senha": "123456" }

// Resposta 201:
{ "success": true, "data": { "id_usuario": 1, "nome": "João", "email": "j@x.com" }, "message": "Conta criada com sucesso." }
```

### `POST /api/usuarios.php` — Login

```json
// Body:
{ "action": "login", "email": "j@x.com", "senha": "123456" }

// Resposta 200:
{ "success": true, "data": { "id_usuario": 1, "nome": "João", "email": "j@x.com" }, "message": "Login realizado com sucesso." }
// → Cookie de sessão é criado automaticamente
```

### `GET /api/usuarios.php` — Dados do usuário logado (/me)

```
// Requer sessão ativa
// Resposta 200: { "success": true, "data": { id_usuario, nome, email } }
```

### `POST /api/usuarios.php` — Logout

```json
{ "action": "logout" }
// Destrói a sessão PHP
```

---

### Fornecedores

| Método | URL                          | Body (JSON)                        | Requer auth |
|--------|------------------------------|------------------------------------|-------------|
| GET    | /api/fornecedores.php        | —                                  | ✅           |
| GET    | /api/fornecedores.php/{id}   | —                                  | ✅           |
| POST   | /api/fornecedores.php        | nome, cnpj, contato, telefone, status | ✅        |
| PUT    | /api/fornecedores.php/{id}   | nome, cnpj, contato, telefone, status | ✅        |
| DELETE | /api/fornecedores.php/{id}   | —                                  | ✅           |

**Filtros GET (query string):**
- `?status=ativo`
- `?q=techparts` (busca em nome, cnpj, contato)

---

### Produtos

| Método | URL                      | Body (JSON)                                           | Requer auth |
|--------|--------------------------|-------------------------------------------------------|-------------|
| GET    | /api/produtos.php        | —                                                     | ✅           |
| GET    | /api/produtos.php/{id}   | —                                                     | ✅           |
| POST   | /api/produtos.php        | nome, preco, descricao, categoria, estoque, status, id_fornecedor | ✅ |
| PUT    | /api/produtos.php/{id}   | (mesmos campos)                                       | ✅           |
| DELETE | /api/produtos.php/{id}   | —                                                     | ✅           |

**Filtros GET:**
- `?categoria=Eletrônicos`
- `?status=ativo`
- `?q=monitor` (busca em nome, categoria, descrição)
- `?fornecedor=TechParts`
- `?order=preco` (nome | preco | estoque | categoria)

---

## 🔐 Autenticação

A autenticação é feita via **sessão PHP** (`$_SESSION['user_id']`).

Após o login, o navegador mantém o cookie de sessão automaticamente.
Todas as rotas de fornecedores e produtos chamam `requireAuth()` no início —
se não houver sessão, retornam `401 Não autenticado`.

**No front-end**, ao chamar `ApiAuth.login()`, armazene também o cookie de sessão.
Para SPA ou mobile (sem cookies), você precisará adaptar para tokens JWT.

---

## 🔒 Segurança implementada

- **PDO + prepared statements** — proteção contra SQL injection em 100% das queries
- **hash('sha256', $senha)** — conforme especificado no enunciado
- **session_regenerate_id(true)** — previne session fixation no login
- **Validação server-side** de todos os campos antes de qualquer query
- **CORS configurável** — ajuste `Access-Control-Allow-Origin` para produção
- **Acesso à pasta `config/` bloqueado** via `.htaccess`
- **Verificação de FK** antes de excluir fornecedor com produtos vinculados

---

## ✅ Adaptando o api.js do Front-end

```js
// assets/js/api.js — substitua o bloco de ApiAuth

const ApiAuth = {
  async login(email, senha) {
    return apiFetch('/usuarios.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'login', email, senha }),
    });
  },
  async cadastrar(nome, email, senha) {
    return apiFetch('/usuarios.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'cadastrar', nome, email, senha }),
    });
  },
  async me() {
    return apiFetch('/usuarios.php');          // GET
  },
  async logout() {
    return apiFetch('/usuarios.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'logout' }),
    });
  },
};
```
