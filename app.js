document.addEventListener('DOMContentLoaded', async () => {
  // tanggal + tahun
  const hariTanggal = document.getElementById('hariTanggal');
  if (hariTanggal) {
    hariTanggal.textContent = new Date().toLocaleDateString('id-ID', {
      weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'
    });
  }
  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  // load navbar modular
  const mount = document.getElementById('navbar');
  if (mount) {
    try {
      const html = await fetch('navbar.html', { cache: 'no-store' }).then(r => r.text());
      mount.innerHTML = html;
      initMenu();
      setActiveNav();
    } catch (e) {
      mount.innerHTML = '<header class="header"><div class="container"><a class="brand" href="index.html"><span>PSTI JATIM</span></a></div></header>';
    }
  }

  // load berita home
  await renderHomeNews();

  // load klasemen opsional
  await loadKlasemen();
});

function setActiveNav() {
  const path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('#navbar a[href]').forEach(a => {
    const href = a.getAttribute('href') || '';
    if (href === path || (path === 'index.html' && href.startsWith('index.html#'))) {
      a.classList.add('active');
    }
  });
}

function initMenu() {
  const burger = document.getElementById('btnMenu');
  const menu = document.getElementById('mainMenu');
  if (!burger || !menu) return;

  const isMobile = () => window.matchMedia('(max-width: 760px)').matches;

  const backdrop = document.createElement('div');
  backdrop.id = 'menu-backdrop';
  Object.assign(backdrop.style, {
    position: 'fixed', inset: '0', zIndex: '2147483590', display: 'none',
    background: 'rgba(0,0,0,.001)'
  });
  document.body.appendChild(backdrop);

  const closeMenu = () => {
    menu.classList.remove('show');
    burger.setAttribute('aria-expanded', 'false');
    backdrop.style.display = 'none';
    document.documentElement.style.overflow = '';
    menu.querySelectorAll('.dropdown.show').forEach(d => d.classList.remove('show'));
  };

  const openMenu = () => {
    menu.classList.add('show');
    burger.setAttribute('aria-expanded', 'true');
    backdrop.style.display = 'block';
    document.documentElement.style.overflow = 'hidden';
  };

  burger.addEventListener('click', () => {
    menu.classList.contains('show') ? closeMenu() : openMenu();
  });

  backdrop.addEventListener('click', closeMenu);
  document.addEventListener('keydown', (e) => e.key === 'Escape' && closeMenu());
  window.addEventListener('resize', () => !isMobile() && closeMenu());
  window.addEventListener('scroll', () => isMobile() && closeMenu(), { passive: true });

  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));

  menu.querySelectorAll('.has-dropdown > a').forEach(a => {
    a.addEventListener('click', (e) => {
      if (!isMobile()) return;
      e.preventDefault();
      const drop = a.parentElement.querySelector('.dropdown');
      if (drop) drop.classList.toggle('show');
    });
  });
}

async function renderHomeNews() {
  const grid = document.getElementById('gridBerita');
  const msg = document.getElementById('homeNewsMsg');
  if (!grid) return;

  try {
    const raw = await fetch('berita_api.php?action=list&limit=6', { cache: 'no-store' }).then(r => r.text());
    let j;
    try { j = JSON.parse(raw); } catch { throw new Error('Response API tidak valid'); }
    if (!j.ok) throw new Error(j.error || 'Gagal memuat berita');

    const items = Array.isArray(j.items) ? j.items : [];
    grid.innerHTML = '';

    if (!items.length) {
      grid.innerHTML = '<div class="card">Belum ada berita. Tambahkan melalui dashboard admin.</div>';
      return;
    }

    grid.appendChild(heroCard(items[0]));
    items.slice(1).forEach(it => grid.appendChild(newsCard(it)));
  } catch (e) {
    if (msg) msg.textContent = `Gagal memuat berita: ${e.message}`;
  }
}

function fmtDate(s) {
  if (!s) return '';
  const d = new Date(String(s).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return s;
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function heroCard(it) {
  const el = document.createElement('article');
  el.className = 'card-hero';
  const title = escapeHtml(it.title || '(Tanpa judul)');
  const excerpt = escapeHtml(it.excerpt || '');
  const source = escapeHtml(it.source_name || 'PSTI Jatim');
  const banner = it.banner || 'assets/contoh.jpg';
  const url = it.source_url || '#';

  el.innerHTML = `
    <img src="${banner}" alt="${title}">
    <div class="content">
      <span class="badge">${it.pinned ? 'Pinned' : 'Berita'}</span>
      <h2 style="margin:.55rem 0 .45rem;line-height:1.2">${title}</h2>
      <div class="excerpt">${excerpt}</div>
      <div class="muted" style="font-size:.86rem;margin-top:.45rem">${source}${it.published_at ? ` • ${fmtDate(it.published_at)}` : ''}</div>
      ${url && url !== '#' ? `<a class="btn btn-small" style="margin-top:.6rem" href="${url}" target="_blank" rel="noopener">Baca Selengkapnya</a>` : ''}
    </div>`;
  return el;
}

function newsCard(it) {
  const el = document.createElement('article');
  el.className = 'card news-card';
  const title = escapeHtml(it.title || '(Tanpa judul)');
  const excerpt = escapeHtml(it.excerpt || '');
  const source = escapeHtml(it.source_name || 'PSTI Jatim');
  const banner = it.banner || 'assets/contoh.jpg';
  const url = it.source_url || '#';

  el.innerHTML = `
    <img class="news-thumb" src="${banner}" alt="${title}">
    <h3 class="news-title">${url && url !== '#' ? `<a href="${url}" target="_blank" rel="noopener">${title}</a>` : title}</h3>
    <p class="excerpt">${excerpt}</p>
    <div class="news-meta">${source}${it.published_at ? ` • ${fmtDate(it.published_at)}` : ''}</div>
    ${url && url !== '#' ? `<a class="news-link" href="${url}" target="_blank" rel="noopener">Baca <i class="fa-solid fa-arrow-up-right-from-square"></i></a>` : ''}`;
  return el;
}

async function loadAgenda() {
  const ul = document.getElementById('jadwalList');
  if (!ul) return;
  try {
    const r = await fetch('agenda_api.php?action=list', { cache: 'no-store' });
    const j = await r.json();
    if (!j.ok || !Array.isArray(j.items)) return;
    ul.innerHTML = j.items.map(it => `
      <li><strong>${escapeHtml(String(it.periode ?? ''))}</strong><span>${escapeHtml(String(it.kegiatan ?? ''))}</span></li>
    `).join('');
  } catch {}
}

async function loadKlasemen() {
  const tbody = document.querySelector('#tabelKlasemen tbody');
  if (!tbody) return;
  try {
    const r = await fetch('klasemen_api.php?action=list', { cache: 'no-store' });
    const j = await r.json();
    const klasemen = (j && j.ok && Array.isArray(j.items)) ? j.items : [];
    if (!klasemen.length) return;

    tbody.innerHTML = klasemen.map(k => `
      <tr>
        <td>${escapeHtml(String(k.klub ?? ''))}</td>
        <td>${escapeHtml(String(k.main ?? '0'))}</td>
        <td>${escapeHtml(String(k.menang ?? '0'))}</td>
        <td><strong>${escapeHtml(String(k.poin ?? '0'))}</strong></td>
      </tr>`).join('');
  } catch {}
}

async function loadSponsor() {
  const box = document.getElementById('sponsorList');
  if (!box) return;
  try {
    const r = await fetch('sponsor_api.php?action=list', { cache: 'no-store' });
    const j = await r.json();
    if (!j.ok || !Array.isArray(j.items) || !j.items.length) return;
    box.innerHTML = j.items.map(s => {
      const name = escapeHtml(String(s.name ?? 'Sponsor'));
      const image = escapeHtml(String(s.image || 'assets/contoh.jpg'));
      const url = String(s.url || '').trim();
      if (url) {
        return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" title="${name}"><img src="${image}" alt="${name}"></a>`;
      }
      return `<img src="${image}" alt="${name}">`;
    }).join('');
  } catch {}
}

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
