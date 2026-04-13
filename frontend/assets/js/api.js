/**
 * api.js — Camada de serviços HTTP
 *
 * Abstrai todas as chamadas ao backend.
 * Substitua BASE_URL pela URL real do seu servidor.
 *
 * Endpoints esperados:
 *   POST   /api/login
 *   POST   /api/usuarios
 *   GET    /api/fornecedores
 *   POST   /api/fornecedores
 *   PUT    /api/fornecedores/:id
 *   DELETE /api/fornecedores/:id
 *   GET    /api/produtos
 *   POST   /api/produtos
 *   PUT    /api/produtos/:id
 *   DELETE /api/produtos/:id
 */

const API_BASE = 'http://localhost/gestprod/backend/api'; // TODO: alterar para URL do backend em produção

// ── Utilitário de request ──────────────────────────────
async function apiFetch(endpoint, options = {}) {
  // Recupera token de autenticação
  let token = null;
  try {
    const session = JSON.parse(localStorage.getItem('sgp_session'));
    token = session?.token;
  } catch {}

  const defaultHeaders = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };

  const config = {
    ...options,
    headers: { ...defaultHeaders, ...(options.headers || {}) },
  };

  try {
    const res = await fetch(`${API_BASE}${endpoint}`, config);

    // Resposta sem corpo (204 No Content)
    if (res.status === 204) return { ok: true };

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data?.message || `Erro ${res.status}`);
    }

    return data;
  } catch (err) {
    console.error(`[API] ${options.method || 'GET'} ${endpoint}`, err.message);
    throw err;
  }
}

// ── Auth ───────────────────────────────────────────────
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
};

// ── Fornecedores ───────────────────────────────────────
const ApiFornecedores = {
  async listar() {
    return apiFetch('/fornecedores.php');
  },

  async criar(dados) {
    return apiFetch('/fornecedores.php', {
      method: 'POST',
      body: JSON.stringify(dados),
    });
  },

  async atualizar(id, dados) {
    return apiFetch(`/fornecedores.php/${id}`, {
      method: 'PUT',
      body: JSON.stringify(dados),
    });
  },

  async deletar(id) {
    return apiFetch(`/fornecedores.php/${id}`, { method: 'DELETE' });
  },
};

// ── Produtos ───────────────────────────────────────────
const ApiProdutos = {
  async listar() {
    return apiFetch('/produtos.php');
  },

  async criar(dados) {
    return apiFetch('/produtos.php', {
      method: 'POST',
      body: JSON.stringify(dados),
    });
  },

  async atualizar(id, dados) {
    return apiFetch(`/produtos.php/${id}`, {
      method: 'PUT',
      body: JSON.stringify(dados),
    });
  },

  async deletar(id) {
    return apiFetch(`/produtos.php/${id}`, { method: 'DELETE' });
  },
};

// ── Mock de dados (usar enquanto backend não está pronto) ──
// Remova este bloco após integração real
const MockData = {
  fornecedores: [
    { id: 1, nome: 'TechParts Ltda',    cnpj: '12.345.678/0001-90', contato: 'tech@techparts.com',    telefone: '(11) 9 9876-5432', status: 'ativo' },
    { id: 2, nome: 'Distribuidora Sul', cnpj: '98.765.432/0001-10', contato: 'vendas@distsul.com',    telefone: '(51) 9 8765-4321', status: 'ativo' },
    { id: 3, nome: 'Global Supplies',   cnpj: '11.222.333/0001-44', contato: 'global@supplies.com',   telefone: '(21) 9 7654-3210', status: 'inativo' },
  ],

  produtos: [
    { id: 1, nome: 'Monitor 27" 4K',   categoria: 'Eletrônicos', fornecedor_id: 1, fornecedor: 'TechParts Ltda',    preco: 2499.90, estoque: 15, status: 'ativo' },
    { id: 2, nome: 'Teclado Mecânico', categoria: 'Periféricos', fornecedor_id: 1, fornecedor: 'TechParts Ltda',    preco: 389.00,  estoque: 42, status: 'ativo' },
    { id: 3, nome: 'Cadeira Gamer',    categoria: 'Móveis',      fornecedor_id: 2, fornecedor: 'Distribuidora Sul', preco: 1199.00, estoque: 8,  status: 'ativo' },
    { id: 4, nome: 'Mouse Wireless',   categoria: 'Periféricos', fornecedor_id: 2, fornecedor: 'Distribuidora Sul', preco: 159.90,  estoque: 0,  status: 'inativo' },
    { id: 5, nome: 'Headset 7.1',      categoria: 'Áudio',       fornecedor_id: 3, fornecedor: 'Global Supplies',   preco: 299.00,  estoque: 27, status: 'ativo' },
  ],
};
