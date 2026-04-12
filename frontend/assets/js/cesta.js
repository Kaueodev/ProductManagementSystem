/**
 * cesta.js — Gerenciamento da cesta de produtos
 *
 * A cesta é mantida em memória (sessionStorage).
 * Não requer backend — conforme especificado no enunciado.
 *
 * Estrutura de um item:
 * {
 *   id        : number,
 *   nome      : string,
 *   categoria : string,
 *   fornecedor: string,
 *   preco     : number,
 *   qty       : number,
 * }
 */

const Cesta = {
  STORAGE_KEY: 'sgp_cesta',

  // ── Persistência ──────────────────────────────────
  _load() {
    try {
      return JSON.parse(sessionStorage.getItem(this.STORAGE_KEY)) || [];
    } catch {
      return [];
    }
  },

  _save(items) {
    sessionStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
    this._onUpdate();
  },

  _onUpdate() {
    // Atualiza badge da sidebar
    SidebarBuilder?.updateCestaBadge?.();
    // Dispara evento customizado para que a página cesta.html reaja
    document.dispatchEvent(new CustomEvent('cesta:updated'));
  },

  // ── Getters ────────────────────────────────────────
  getAll() {
    return this._load();
  },

  count() {
    return this._load().reduce((n, i) => n + i.qty, 0);
  },

  total() {
    return this._load().reduce((s, i) => s + (i.preco * i.qty), 0);
  },

  // ── Mutações ───────────────────────────────────────
  adicionar(produto) {
    const items = this._load();
    const idx   = items.findIndex(i => i.id === produto.id);

    if (idx >= 0) {
      items[idx].qty += 1;
    } else {
      items.push({ ...produto, qty: 1 });
    }

    this._save(items);
    return items;
  },

  remover(id) {
    const items = this._load().filter(i => i.id !== id);
    this._save(items);
    return items;
  },

  alterarQty(id, qty) {
    if (qty < 1) return this.remover(id);
    const items = this._load();
    const idx   = items.findIndex(i => i.id === id);
    if (idx >= 0) { items[idx].qty = qty; this._save(items); }
    return items;
  },

  limpar() {
    this._save([]);
  },

  // ── Helpers ────────────────────────────────────────
  formataPreco(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  },
};
