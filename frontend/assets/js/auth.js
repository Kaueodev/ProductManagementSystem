/**
 * auth.js — Gerenciamento de autenticação (client-side)
 *
 * NOTA: Após integração com backend, este arquivo receberá
 * o token JWT retornado pelo POST /api/login e fará validações reais.
 *
 * Por ora, simula o estado de autenticação via localStorage.
 */

const Auth = {
  // ── Constantes ────────────────────────────────────
  STORAGE_KEY: 'sgp_session',

  // ── Verifica se o usuário está logado ─────────────
  isLoggedIn() {
    try {
      const session = JSON.parse(localStorage.getItem(this.STORAGE_KEY));
      return !!(session && session.token && session.user);
    } catch {
      return false;
    }
  },

  // ── Retorna dados do usuário logado ───────────────
  getUser() {
    try {
      const session = JSON.parse(localStorage.getItem(this.STORAGE_KEY));
      return session?.user || null;
    } catch {
      return null;
    }
  },

  // ── Simula login (substituir por chamada real) ─────
  // TODO: POST /api/login → { token, user }
  login(userData) {
    const session = {
      token: 'mock_token_' + Date.now(), // substituir pelo JWT do backend
      user: userData,
      loginAt: new Date().toISOString(),
    };
    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(session));
  },

  // ── Logout ────────────────────────────────────────
  logout() {
    localStorage.removeItem(this.STORAGE_KEY);
    // TODO: POST /api/logout para invalidar token no backend
    window.location.href = 'index.html';
  },

  // ── Protege páginas privadas ───────────────────────
  // Chame requireAuth() no <head> de páginas privadas.
  requireAuth() {
    if (!this.isLoggedIn()) {
      window.location.href = 'index.html?redirect=' + encodeURIComponent(window.location.pathname);
    }
  },

  // ── Redireciona usuário logado da tela de login ────
  redirectIfLoggedIn(dest = 'menu.html') {
    if (this.isLoggedIn()) window.location.href = dest;
  },
};

// ── Sidebar Builder ────────────────────────────────────
// Injeta a sidebar dinamicamente em todas as páginas
const SidebarBuilder = {
  init() {
    const placeholder = document.getElementById('sidebar-placeholder');
    if (!placeholder) return;

    const isLogged   = Auth.isLoggedIn();
    const user       = Auth.getUser();
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    const publicLinks = [
      { href: 'index.html',           icon: '🔐', label: 'Login',           id: 'index.html' },
      { href: 'cadastro_usuario.html', icon: '👤', label: 'Cadastro',        id: 'cadastro_usuario.html' },
    ];

    const privateLinks = [
      { href: 'menu.html',             icon: '🏠', label: 'Dashboard',       id: 'menu.html' },
      { href: 'fornecedores.html',     icon: '🏭', label: 'Fornecedores',    id: 'fornecedores.html' },
      { href: 'produtos.html',         icon: '📦', label: 'Produtos',        id: 'produtos.html' },
      { href: 'listagem_produtos.html',icon: '📋', label: 'Listagem',        id: 'listagem_produtos.html' },
      { href: 'cesta.html',            icon: '🛒', label: 'Cesta',           id: 'cesta.html', badge: true },
    ];

    const makeLink = (l) => {
      const active  = currentPage === l.id ? 'active' : '';
      const badge   = l.badge ? `<span class="nav-badge" id="sidebar-cesta-count">0</span>` : '';
      return `
        <a href="${l.href}" class="nav-link-custom ${active}">
          <span class="nav-icon">${l.icon}</span>
          <span>${l.label}</span>
          ${badge}
        </a>`;
    };

    const userName   = user?.nome || 'Usuário';
    const userEmail  = user?.email || '';
    const userInitial = userName.charAt(0).toUpperCase();

    placeholder.innerHTML = `
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
          <div class="brand-icon">📦</div>
          <div class="brand-name">GestProd</div>
          <div class="brand-sub">Sistema de Gestão</div>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-label">Acesso Público</div>
          ${publicLinks.map(makeLink).join('')}
        </div>

        ${isLogged ? `
        <div class="nav-divider"></div>
        <div class="sidebar-section">
          <div class="sidebar-section-label">Módulos</div>
          ${privateLinks.map(makeLink).join('')}
        </div>` : `
        <div class="nav-divider"></div>
        <div class="sidebar-section">
          <div class="sidebar-section-label" style="display:flex;align-items:center;gap:6px">
            <span>🔒</span> Faça login para acessar
          </div>
          ${privateLinks.map(l => `
            <a href="index.html" class="nav-link-custom" style="opacity:.4;pointer-events:none">
              <span class="nav-icon">${l.icon}</span>
              <span>${l.label}</span>
            </a>`).join('')}
        </div>`}

        <div class="nav-divider"></div>

        ${isLogged ? `
        <div class="sidebar-footer">
          <div class="sidebar-user" onclick="Auth.logout()">
            <div class="user-avatar">${userInitial}</div>
            <div class="user-info">
              <div class="user-name">${userName}</div>
              <div class="user-role">Sair da conta</div>
            </div>
            <span style="color:#64748b;font-size:14px;margin-left:auto">↩</span>
          </div>
        </div>` : ''}
      </aside>
      <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
    `;

    // Atualiza badge da cesta
    this.updateCestaBadge();
  },

  updateCestaBadge() {
    const cesta = Cesta?.getAll?.() || [];
    const badge = document.getElementById('sidebar-cesta-count');
    if (badge) {
      badge.textContent = cesta.length;
      badge.style.display = cesta.length > 0 ? 'inline-block' : 'none';
    }
  },
};

// ── Toggle sidebar mobile ──────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar')?.classList.toggle('open');
  document.getElementById('sidebar-overlay')?.classList.toggle('open');
}

// ── Toast utility ──────────────────────────────────────
function showToast(message, type = 'success') {
  let container = document.querySelector('.toast-container-custom');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container-custom';
    document.body.appendChild(container);
  }

  const icon  = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
  const toast = document.createElement('div');
  toast.className = `toast-custom ${type === 'error' ? 'error' : ''}`;
  toast.innerHTML = `<span>${icon}</span> ${message}`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'toastIn .3s ease reverse forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// Inicializa sidebar ao carregar o DOM
document.addEventListener('DOMContentLoaded', () => SidebarBuilder.init());
