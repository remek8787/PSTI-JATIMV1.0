(function () {
  const ADMIN_KEY = 'psti_admin_session';
  const DB_PREFIX = 'psti_db_';
  const seedCache = {};

  const modules = {
    berita: { file: 'data/berita.json', id: 'id' },
    agenda: { file: 'data/agenda.json' },
    klasemen: { file: 'data/klasemen.json' },
    sponsor: { file: 'data/sponsor.json' },
    atlit: { file: 'data/atlit.json', id: 'id' },
    pelatih: { file: 'data/pelatih.json', id: 'id' },
    pengurus: { file: 'data/pengurus.json', id: 'id' },
    klub: { file: 'data/klub.json', id: 'id' }
  };

  const defaultUsers = [
    { u: 'admin_pengurus', p: 'pstiPengurus123' },
    { u: 'sekretariat', p: 'psti2025' }
  ];

  function nowSql() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  }

  function asJSON(body, status = 200) {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json; charset=utf-8' } });
  }

  function getSession() {
    try { return JSON.parse(localStorage.getItem(ADMIN_KEY) || 'null'); } catch { return null; }
  }

  function setSession(user) {
    localStorage.setItem(ADMIN_KEY, JSON.stringify({ user, at: Date.now() }));
  }

  function clearSession() {
    localStorage.removeItem(ADMIN_KEY);
  }

  async function seed(file) {
    if (seedCache[file]) return seedCache[file];
    try {
      const r = await fetch(file, { cache: 'no-store' });
      if (!r.ok) return [];
      const j = await r.json();
      seedCache[file] = Array.isArray(j) ? j : [];
      return seedCache[file];
    } catch {
      return [];
    }
  }

  async function dbGet(moduleName) {
    const key = DB_PREFIX + moduleName;
    const fromLs = localStorage.getItem(key);
    if (fromLs) {
      try { return JSON.parse(fromLs); } catch { /* ignore */ }
    }
    const base = await seed(modules[moduleName].file);
    localStorage.setItem(key, JSON.stringify(base));
    return JSON.parse(JSON.stringify(base));
  }

  function dbSet(moduleName, items) {
    localStorage.setItem(DB_PREFIX + moduleName, JSON.stringify(items));
  }

  function isAdmin() {
    return !!getSession();
  }

  function parseQuery(url) {
    const u = new URL(url, location.origin);
    return { pathname: u.pathname.split('/').pop() || '', q: u.searchParams };
  }

  function uid(prefix = '') {
    return (prefix + Math.random().toString(16).slice(2, 10)).toUpperCase();
  }

  async function fileToDataUrl(file) {
    if (!file || typeof FileReader === 'undefined') return '';
    return new Promise((resolve) => {
      const fr = new FileReader();
      fr.onload = () => resolve(String(fr.result || ''));
      fr.onerror = () => resolve('');
      fr.readAsDataURL(file);
    });
  }

  async function formToObj(formData) {
    const out = {};
    for (const [k, v] of formData.entries()) {
      if (v instanceof File) {
        if (!v.name) continue;
        out[k] = await fileToDataUrl(v);
      } else {
        out[k] = v;
      }
    }
    return out;
  }

  function parseBody(init) {
    const b = init && init.body;
    if (!b) return Promise.resolve({});
    if (b instanceof FormData) return formToObj(b);
    if (typeof b === 'string') {
      try { return Promise.resolve(JSON.parse(b)); } catch { return Promise.resolve({}); }
    }
    return Promise.resolve({});
  }

  async function handleAuth(pathname, query, method, init) {
    const action = query.get('action') || ((await parseBody(init)).action) || 'me';
    if (action === 'me') {
      const s = getSession();
      return asJSON({ ok: true, admin: !!s, user: s ? s.user : '' });
    }
    if (action === 'logout') {
      clearSession();
      return asJSON({ ok: true });
    }
    if (action === 'login') {
      const body = await parseBody(init);
      const user = String(body.username || '').trim();
      const pass = String(body.password || '').trim();
      const ok = defaultUsers.find((x) => x.u === user && x.p === pass);
      if (!ok) return asJSON({ ok: false, error: 'Username / password salah' }, 401);
      setSession(user);
      return asJSON({ ok: true, user });
    }
    return asJSON({ ok: false, error: 'Unknown action' }, 400);
  }

  function moduleFromPath(pathname) {
    if (!pathname.endsWith('_api.php')) return null;
    return pathname.replace('_api.php', '');
  }

  function mapSponsorFromForm(body) {
    const items = [];
    for (let i = 0; i < 6; i++) {
      const name = String(body[`name_${i}`] || '').trim();
      const url = String(body[`url_${i}`] || '').trim();
      const oldImage = String(body[`old_image_${i}`] || '').trim();
      const image = String(body[`image_${i}`] || '').trim() || oldImage;
      if (!name && !url && !image) continue;
      items.push({ name, url, image });
    }
    return items;
  }

  function normDate(v) {
    const x = String(v || '').trim();
    return x ? x.replace('T', ' ').slice(0, 19) : nowSql();
  }

  async function handleModule(pathname, query, method, init) {
    const moduleName = moduleFromPath(pathname);
    if (!modules[moduleName]) return null;

    let items = await dbGet(moduleName);
    const body = await parseBody(init);
    const action = query.get('action') || body.action || 'list';

    if (action === 'list') {
      let out = Array.isArray(items) ? [...items] : [];
      if (moduleName === 'berita') {
        out.sort((a, b) => (Number(b.pinned || 0) - Number(a.pinned || 0)) || (String(b.published_at || '').localeCompare(String(a.published_at || ''))));
        const limit = Number(query.get('limit') || 0);
        if (limit > 0) out = out.slice(0, limit);
      }
      return asJSON({ ok: true, items: out, total: out.length });
    }

    if (action === 'get') {
      const id = String(query.get('id') || body.id || '');
      const item = (items || []).find((it) => String(it.id) === id || Number(it.id) === Number(id));
      if (!item) return asJSON({ ok: false, error: 'Data tidak ditemukan' }, 404);
      return asJSON({ ok: true, item });
    }

    if (!isAdmin()) return asJSON({ ok: false, error: 'Unauthorized' }, 401);

    if (moduleName === 'agenda' && action === 'save') {
      const clean = (body.items || []).map((it) => ({ periode: String(it.periode || '').trim(), kegiatan: String(it.kegiatan || '').trim() })).filter((x) => x.periode || x.kegiatan);
      dbSet(moduleName, clean);
      return asJSON({ ok: true, count: clean.length });
    }

    if (moduleName === 'klasemen' && action === 'save') {
      const clean = (body.items || []).map((it) => ({
        klub: String(it.klub || '').trim(),
        main: Number(it.main || 0),
        menang: Number(it.menang || 0),
        poin: Number(it.poin || 0)
      })).filter((x) => x.klub);
      dbSet(moduleName, clean);
      return asJSON({ ok: true, count: clean.length });
    }

    if (moduleName === 'sponsor' && action === 'save') {
      const clean = mapSponsorFromForm(body);
      dbSet(moduleName, clean);
      return asJSON({ ok: true, count: clean.length });
    }

    if (action === 'create' || action === 'update') {
      const editable = { ...body };
      delete editable.action;
      if (!editable.id && action === 'create') {
        editable.id = moduleName === 'berita' ? (Math.max(0, ...items.map((x) => Number(x.id || 0))) + 1) : uid('');
        editable.created_at = nowSql();
      }

      if (moduleName === 'berita') {
        editable.pinned = ['1', 'true', 'on', 1, true].includes(editable.pinned) ? 1 : 0;
        editable.published_at = normDate(editable.published_at);
      }

      if (['atlit', 'pelatih'].includes(moduleName)) {
        for (const key of ['medali', 'jumlah', 'tahun', 'event', 'sertifikat', 'tahun_sertifikat', 'pengalaman', 'tahun_pengalaman', 'prestasi', 'tahun_prestasi']) {
          if (typeof editable[key] === 'string') {
            editable[key] = editable[key].split('\n').map((x) => x.trim()).filter(Boolean);
          }
        }
      }

      const fileField = moduleName === 'berita' ? 'banner' : 'foto';
      if (!editable[fileField]) delete editable[fileField];

      if (action === 'create') {
        items.push(editable);
      } else {
        const idx = items.findIndex((it) => String(it.id) === String(editable.id) || Number(it.id) === Number(editable.id));
        if (idx < 0) return asJSON({ ok: false, error: 'Data tidak ditemukan' }, 404);
        items[idx] = { ...items[idx], ...editable, updated_at: nowSql() };
      }
      dbSet(moduleName, items);
      return asJSON({ ok: true, id: editable.id });
    }

    if (action === 'delete') {
      const id = String(body.id || query.get('id') || '');
      const next = items.filter((it) => !(String(it.id) === id || Number(it.id) === Number(id)));
      if (next.length === items.length) return asJSON({ ok: false, error: 'Data tidak ditemukan' }, 404);
      dbSet(moduleName, next);
      return asJSON({ ok: true, id });
    }

    return asJSON({ ok: false, error: 'Action tidak dikenal' }, 400);
  }

  const nativeFetch = window.fetch.bind(window);
  window.fetch = async function (input, init = {}) {
    const url = typeof input === 'string' ? input : input.url;
    const { pathname, q } = parseQuery(url);
    const method = String(init.method || 'GET').toUpperCase();

    if (pathname === 'auth_pengurus.php' || pathname === 'auth_api.php') {
      return handleAuth(pathname, q, method, init);
    }

    const handled = await handleModule(pathname, q, method, init);
    if (handled) return handled;

    return nativeFetch(input, init);
  };

  window.PSTIData = {
    getSession,
    clearSession,
    dbGet,
    dbSet
  };
})();