// app.js — PSTI Jatim (ambil berita eksternal via proxy.php)
document.addEventListener('DOMContentLoaded', async () => {
  // Topbar tanggal & tahun footer
  const now = new Date();
  const hariTanggal = document.getElementById('hariTanggal');
  const yearSpan = document.getElementById('year');
  if (hariTanggal) hariTanggal.textContent = now.toLocaleDateString('id-ID',
    {weekday:'long', day:'numeric', month:'long', year:'numeric'});
  if (yearSpan) yearSpan.textContent = now.getFullYear();

  // Menu mobile
  const burger = document.querySelector('.hamburger');
  const menu = document.querySelector('.menu');
  burger?.addEventListener('click', () => menu?.classList.toggle('show'));

  const grid = document.getElementById('gridBerita');
  const marquee = document.getElementById('marqueeBerita');

  // Helpers
  async function loadJSON(url) {
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) throw new Error(`Gagal load ${url} (${res.status})`);
    return res.json();
  }
  function fallbackLinks(){
    return [
      "https://lintasnetworkmedia.com/2025/08/04/aries-agung-paewai-ketua-psti-jatim-lepas-atlet-ke-pelatnas-harumkan-nama-daerah-dan-bangsa/",
      "https://artik.id/news-12448-jawa-timur-kirim-dua-pejuang-takraw-ke-pelatnas-sea-games-thailand",
      "https://hariannasionalnews.com/news-4727-aries-agung-paewai-ketua-psti-jatim-lepas-atlet-ke-pelatnas-harumkan-nama-daerah-dan-bangsa"
    ];
  }
  const safe = v => (v && String(v).trim()) ? v : 'Baca selengkapnya di sumber.';
  const BTN = (url, small=false) => `<a class="btn ${small?'btn-small':''}" href="${url}" target="_blank" rel="noopener">Baca Selengkapnya</a>`;

  const heroCard = b => `
    <article class="card-hero">
      <span class="badge">Berita</span>
      <img src="${b.gambar}" alt="${b.judul}">
      <div class="content">
        <h2 style="margin:0 0 6px 0;">${b.judul}</h2>
        <p class="excerpt">${safe(b.deskripsi)}</p>
        ${BTN(b.url)}
      </div>
    </article>`;

  const smallCard = b => `
    <article class="card">
      <img class="thumb" src="${b.gambar}" alt="${b.judul}">
      <h3>${b.judul}</h3>
      <p class="excerpt">${safe(b.deskripsi)}</p>
      ${BTN(b.url, true)}
    </article>`;

  async function fetchViaProxy(url){
    const q = `proxy.php?url=${encodeURIComponent(url)}&ts=${Date.now()}`; // bust cache
    const r = await fetch(q, { cache: 'no-store' });
    if (!r.ok) throw new Error(`Proxy gagal (${r.status})`);
    const j = await r.json();
    if (j.error) throw new Error(j.error);
    return j;
  }

  async function ambilSemuaBerita(){
    let links;
    try {
      links = await loadJSON('data/berita.json?ts='+Date.now());
      if (!Array.isArray(links) || links.length===0) throw new Error('Format/isi berita.json salah');
    } catch(e){
      links = fallbackLinks(); // tetap tampil meski berita.json belum ada
    }
    const jobs = links.map(u => fetchViaProxy(u).catch(()=>null));
    const results = await Promise.allSettled(jobs);
    return results
      .map(x => x.status==='fulfilled' ? x.value : null)
      .filter(x => x && x.judul);
  }

  // Render
  if (grid) grid.textContent = 'Memuat berita…';
  const items = await ambilSemuaBerita();

  if (items.length) {
    const [utama, ...lain] = items;
    if (grid) grid.innerHTML = `${heroCard(utama)}${lain.slice(0,2).map(smallCard).join('')}`;
    if (marquee) marquee.textContent =
      ['SELAMAT DATANG DI WEBSITE PSTI JAWA TIMUR', ...items.map(i=>i.judul)].join(' • ');
  } else {
    if (grid) grid.innerHTML =
      `<div class="card">Belum ada data dari sumber. Coba refresh atau periksa <code>proxy.php</code> dan <code>data/berita.json</code>.</div>`;
  }

  // Optional: isi tabel klasemen bila ada
  const tbody = document.querySelector('#tabelKlasemen tbody');
  if (tbody) {
    try {
      const klasemen = await loadJSON('data/klasemen.json?ts='+Date.now());
      tbody.innerHTML = klasemen.map(k =>
        `<tr><td>${k.klub}</td><td>${k.main}</td><td>${k.menang}</td><td><strong>${k.poin}</strong></td></tr>`
      ).join('');
    } catch(e){ /* biarin */ }
  }
});
