const $ = (s,p=document)=>p.querySelector(s);
const $$ = (s,p=document)=>Array.from(p.querySelectorAll(s));
const API={berita:'berita_api.php',agenda:'agenda_api.php',klasemen:'klasemen_api.php',sponsor:'sponsor_api.php',atlit:'atlit_api.php',pelatih:'pelatih_api.php',pengurus:'pengurus_api.php',klub:'klub_api.php',kompetisi:'kompetisi_api.php'};

function esc(v=''){return String(v).replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]));}
function fmtDate(v){if(!v)return'-';const d=new Date(String(v).replace(' ','T'));return Number.isNaN(d)?v:d.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});}
function img(v,fallback='assets/logo.png'){return v&&String(v).trim()?String(v):fallback;}

function navbar(){
  const path=location.pathname.split('/').pop()||'index.html';
  const links=[['Beranda','index.html'],['Berita','berita.html'],['Agenda','agenda.html'],['Kompetisi','kompetisi.html'],['Klasemen','klasemen.html'],['Sponsor','sponsor.html'],['Pengurus','pengurus.html'],['Atlet','atlit.html'],['Pelatih','pelatih.html'],['Klub','klub.html'],['Dashboard','dashboard.html']];
  return `<div class="topbar"><div class="container"><span id="hariTanggal"></span><span>Pengprov PSTI Jawa Timur</span></div></div>
  <header class="header"><div class="container nav-wrap"><a class="brand" href="index.html"><img src="assets/logo.png" alt="logo"><div><div>PSTI JATIM</div><small class="muted">Persatuan Sepak Takraw Indonesia</small></div></a>
  <button class="menu-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button><nav class="menu" id="menu">${links.map(([t,h])=>`<a class="${path===h?'active':''}" href="${h}">${t}</a>`).join('')}</nav></div></header>`;
}
function footer(){return `<footer class="footer"><div class="container">© <span id="year"></span> Pengprov PSTI Jawa Timur · Semua hak dilindungi.</div></footer>`}

async function apiList(name,qs=''){const r=await fetch(`${API[name]}?action=list${qs?`&${qs}`:''}`,{cache:'no-store'});return r.json();}
async function apiGet(name,id){const r=await fetch(`${API[name]}?action=get&id=${encodeURIComponent(id)}`,{cache:'no-store'});return r.json();}

function initLayout(){const n=$('#navbar');if(n)n.innerHTML=navbar();const f=$('#footer');if(f)f.innerHTML=footer();const d=$('#hariTanggal');if(d)d.textContent=new Date().toLocaleDateString('id-ID',{weekday:'long',day:'2-digit',month:'long',year:'numeric'});const y=$('#year');if(y)y.textContent=new Date().getFullYear();const b=$('#menuBtn'),m=$('#menu');if(b)b.onclick=()=>m.classList.toggle('show');}

function cardEmpty(text='Data belum tersedia.'){return `<div class="card empty-state"><i class="fa-regular fa-folder-open"></i><p>${esc(text)}</p></div>`;}

async function renderIndex(){
  const [berita,agenda,klasemen,sponsor]=await Promise.all([apiList('berita','limit=6'),apiList('agenda','limit=5'),apiList('klasemen'),apiList('sponsor')]);
  const news=$('#homeNews');if(news){const items=berita.items||[];news.innerHTML=items.length?items.map((n)=>`<article class="card news-card"><img src="${img(n.banner,'assets/hero-musprov.jpg')}" alt="${esc(n.title)}"><span class="badge">${n.pinned?'Unggulan':'Berita'}</span><h3><a href="berita_detail.html?id=${encodeURIComponent(n.id)}">${esc(n.title||'-')}</a></h3><p class="muted">${esc((n.excerpt||'').slice(0,130) || 'Belum ada ringkasan berita.')}</p><small class="muted">${esc(n.source_name||'PSTI Jatim')} · ${fmtDate(n.published_at)}</small></article>`).join(''):cardEmpty('Belum ada berita.')}
  const ag=$('#agendaList');if(ag){ag.innerHTML=(agenda.items||[]).map(a=>`<div class="card"><strong>${esc(a.nama_kegiatan||a.periode||'-')}</strong><div class="muted">${esc([a.periode,a.lokasi].filter(Boolean).join(' • ') || (a.kegiatan||'-'))}</div><small>${esc(a.status||'Terjadwal')}</small></div>`).join('')||cardEmpty('Belum ada agenda.')}
  const kl=$('#klasemenBody');if(kl){const rows=(klasemen.items||[]);kl.innerHTML=rows.length?rows.map((k,i)=>`<tr><td>${i+1}</td><td>${esc(k.klub||'-')}</td><td>${k.main||0}</td><td>${k.menang||0}</td><td><strong>${k.poin||0}</strong></td></tr>`).join(''):`<tr><td colspan='5' class='muted'>Belum ada klasemen.</td></tr>`}
  const sp=$('#sponsorList');if(sp){sp.innerHTML=(sponsor.items||[]).map(s=>`<div class="card"><img style="height:84px;object-fit:contain" src="${img(s.image)}" alt="${esc(s.name)}"><div class="muted">${esc(s.name||'Sponsor')}</div>${s.url?`<a href="${esc(s.url)}" target="_blank" rel="noopener">Kunjungi</a>`:''}</div>`).join('')||cardEmpty('Sponsor belum tersedia.')}
}

async function renderListPage(){
  const wrap=$('#listWrap'); if(!wrap) return;
  const mod=wrap.dataset.module; const detail=wrap.dataset.detail;
  const j=await apiList(mod); const items=j.items||[];

  if(mod==='klasemen'){
    wrap.innerHTML=`<div class='card'><table class='table'><thead><tr><th>#</th><th>Klub</th><th>Main</th><th>Menang</th><th>Poin</th></tr></thead><tbody>${items.length?items.map((k,i)=>`<tr><td>${i+1}</td><td>${esc(k.klub||'-')}</td><td>${k.main||0}</td><td>${k.menang||0}</td><td>${k.poin||0}</td></tr>`).join(''):`<tr><td colspan='5' class='muted'>Belum ada data klasemen.</td></tr>`}</tbody></table></div>`;
    return;
  }

  if(['agenda','kompetisi'].includes(mod)){
    const label = mod==='kompetisi' ? 'kompetisi' : 'agenda';
    wrap.innerHTML=items.length?items.map(it=>`<article class='card'><h3 style='margin:.1rem 0'>${esc(it.nama_kegiatan||it.kegiatan||'Kegiatan')}</h3><p class='muted'>${esc(it.periode||'-')} ${it.lokasi?`• ${esc(it.lokasi)}`:''}</p><p>${esc(it.deskripsi||'')}</p><div class='badge'>${esc(it.status||'Terjadwal')}</div></article>`).join(''):cardEmpty(`Belum ada ${label}.`);
    return;
  }

  if(mod==='sponsor'){
    wrap.innerHTML=items.length?items.map(s=>`<article class='card'><img style='height:100px;object-fit:contain' src='${img(s.image)}' alt='${esc(s.name||'Sponsor')}'><h3>${esc(s.name||'Sponsor')}</h3>${s.url?`<a href='${esc(s.url)}' target='_blank' rel='noopener'>Kunjungi</a>`:'<span class="muted">-</span>'}</article>`).join(''):cardEmpty('Belum ada sponsor.');
    return;
  }

  wrap.innerHTML=items.map(it=>`<article class="card"><div style="display:flex;gap:.8rem"><img class="list-avatar" src="${img(it.foto||it.banner)}" alt="${esc(it.nama||it.title||it.nama_klub||'item')}"><div><h3 style="margin:.1rem 0">${esc(it.nama||it.title||it.nama_klub||it.klub||'-')}</h3><div class="muted">${esc(it.jabatan||it.kabupaten||it.source_name||it.pemilik||'')}</div>${detail?`<a href="${detail}?id=${encodeURIComponent(it.id)}">Lihat detail</a>`:''}</div></div></article>`).join('')||cardEmpty();
}

async function renderDetailPage(){
  const root=$('#detailWrap'); if(!root) return;
  const mod=root.dataset.module; const id=new URLSearchParams(location.search).get('id'); if(!id){root.innerHTML=cardEmpty('ID tidak ditemukan.');return;}
  const j=await apiGet(mod,id); if(!j.ok||!j.item){root.innerHTML=cardEmpty('Data tidak ditemukan.');return;}
  const it=j.item;
  const rows=Object.entries(it).filter(([k,v])=>!['id','foto','banner','created_at','updated_at'].includes(k)&&v!==null&&v!=='' ).map(([k,v])=>`<div>${esc(k.replaceAll('_',' '))}</div><div>${Array.isArray(v)?esc(v.join(', ')):esc(v)}</div>`).join('');
  root.innerHTML=`<article class="card"><div class="detail-head"><img src="${img(it.foto||it.banner,'assets/hero-musprov.jpg')}" alt="${esc(it.nama||it.title||'detail')}"><div><h2>${esc(it.nama||it.title||'-')}</h2><p class="muted">${esc(it.jabatan||it.source_name||it.kategori||'')}</p><div class="kv">${rows}</div></div></div></article>`;
}

document.addEventListener('DOMContentLoaded', async ()=>{initLayout();
  if($('#homePage')) await renderIndex();
  await renderListPage();
  await renderDetailPage();
});