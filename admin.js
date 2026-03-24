const AUTH='auth_pengurus.php';
const API={berita:'berita_api.php',agenda:'agenda_api.php',kompetisi:'kompetisi_api.php',klasemen:'klasemen_api.php',sponsor:'sponsor_api.php',atlit:'atlit_api.php',pelatih:'pelatih_api.php',pengurus:'pengurus_api.php',klub:'klub_api.php'};
const SCHEMA={
  berita:[['title','text',1],['excerpt','textarea'],['source_name','text'],['source_url','url'],['published_at','datetime-local'],['pinned','checkbox'],['banner','file']],
  agenda:[['nama_kegiatan','text',1],['periode','text',1],['lokasi','text'],['status','select',0,['Terjadwal','Berlangsung','Selesai']],['deskripsi','textarea']],
  kompetisi:[['nama_kegiatan','text',1],['periode','text',1],['lokasi','text'],['status','select',0,['Terjadwal','Berlangsung','Selesai']],['deskripsi','textarea']],
  atlit:[['nama','text',1],['jk','text'],['tgl_lahir','date'],['tempat_lahir','text'],['sejarah_singkat','textarea'],['medali','textarea'],['jumlah','textarea'],['tahun','textarea'],['event','textarea'],['foto','file']],
  pelatih:[['nama','text',1],['jk','text'],['ttl','text'],['usia','number'],['tinggi','number'],['berat','number'],['alamat','text'],['pendidikan','text'],['program_studi','text'],['sekolah','text'],['cabor','text'],['telepon','text'],['lisensi','text'],['sertifikat','textarea'],['tahun_sertifikat','textarea'],['pengalaman','textarea'],['tahun_pengalaman','textarea'],['prestasi','textarea'],['tahun_prestasi','textarea'],['foto','file']],
  pengurus:[['nama','text',1],['jabatan','text',1],['kategori','text'],['kat_order','number'],['urut','number'],['alamat','text'],['telepon','text'],['pendidikan','text'],['sertifikat','text'],['biografi','textarea'],['foto','file']],
  klub:[['nama','text',1],['kabupaten','text',1],['pemilik','text'],['telepon','text'],['alamat','textarea'],['instagram','text'],['foto','file']]
};
const $=(s,p=document)=>p.querySelector(s);const $$=(s,p=document)=>Array.from(p.querySelectorAll(s));
function esc(v=''){return String(v).replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]));}
function toast(m,ok=true){const e=$('#flash');e.className='alert show '+(ok?'ok':'err');e.textContent=m;setTimeout(()=>e.classList.remove('show'),3000)}
async function auth(){const r=await fetch(AUTH+'?action=me');const j=await r.json();if(!j.admin)location.href='login_pengurus.html?next=dashboard.html';$('#who').textContent=j.user||'-';}

function bindTabs(){ $$('.side button').forEach(b=>b.onclick=()=>{ $$('.side button').forEach(x=>x.classList.remove('active'));b.classList.add('active'); $$('.panel').forEach(p=>p.classList.remove('active'));$('#p-'+b.dataset.tab).classList.add('active'); loadPanel(b.dataset.tab); }); }

async function list(mod){const r=await fetch(API[mod]+'?action=list&limit=999',{cache:'no-store'});return r.json();}
async function remove(mod,id){const fd=new FormData();fd.append('action','delete');fd.append('id',id);const r=await fetch(API[mod],{method:'POST',body:fd});return r.json();}

function renderTable(mod,items){const w=$('#table-'+mod); if(!w) return;
  if(!items.length){w.innerHTML=`<div class='empty-state card'><i class='fa-regular fa-folder-open'></i><p>Belum ada data ${mod}.</p></div>`;return;}
  const cols=[...new Set(items.flatMap(o=>Object.keys(o)))].filter(k=>!['updated_at','created_at'].includes(k)).slice(0,8);
  w.innerHTML=`<div style='overflow:auto'><table class='table'><thead><tr>${cols.map(c=>`<th>${esc(c)}</th>`).join('')}<th>Aksi</th></tr></thead><tbody>${items.map(it=>`<tr>${cols.map(c=>`<td>${Array.isArray(it[c])?esc(it[c].join(', ')):esc(it[c]??'')}</td>`).join('')}<td><button class='btn secondary' data-edit='${esc(it.id)}'>Edit</button> <button class='btn danger' data-del='${esc(it.id)}'>Hapus</button></td></tr>`).join('')}</tbody></table></div>`;
  $$('[data-del]',w).forEach(b=>b.onclick=async()=>{if(!confirm('Hapus data ini?'))return;const j=await remove(mod,b.dataset.del);if(!j.ok)return toast(j.error||'Gagal hapus',0);toast('Data dihapus');loadPanel(mod)});
  $$('[data-edit]',w).forEach(b=>b.onclick=()=>openForm(mod,items.find(x=>String(x.id)===String(b.dataset.edit))));
}

function makeInput(name,type,val='',opts=[]){
  if(type==='textarea')return `<label>${name}<textarea class='input' name='${name}' placeholder='Masukkan ${name}'>${esc(Array.isArray(val)?val.join('\n'):val??'')}</textarea></label>`;
  if(type==='checkbox')return `<label><input type='checkbox' name='${name}' value='1' ${Number(val)?'checked':''}> ${name}</label>`;
  if(type==='select')return `<label>${name}<select class='input' name='${name}'>${opts.map(o=>`<option value='${esc(o)}' ${String(val)===String(o)?'selected':''}>${esc(o)}</option>`).join('')}</select></label>`;
  return `<label>${name}<input class='input' type='${type==='file'?'file':type||'text'}' name='${name}' ${type==='file'?'accept="image/*"':''} ${type!=='file'?`value='${esc(val??'')}'`:''} placeholder='Masukkan ${name}'></label>`;
}

function validateRequired(mod,fd){
  const req=(SCHEMA[mod]||[]).filter(([, ,r])=>r).map(([n])=>n);
  for(const key of req){ if(!String(fd.get(key)||'').trim()) return `Field wajib: ${key}`; }
  return '';
}

function openForm(mod,data={}){
  const m=$('#modal');const body=$('#modalBody');const targetMod=mod;
  $('#formTitle').textContent=(data.id?'Edit ':'Tambah ')+targetMod;
  body.innerHTML=`<form id='f' class='form-grid'><input type='hidden' name='id' value='${esc(data.id||'')}'>${SCHEMA[targetMod].map(([n,t,,opts])=>`<div class='${t==='textarea'?'full':''}'>${makeInput(n,t,data[n],opts||[])}</div>`).join('')}<div class='full'><button class='btn' type='submit'>Simpan</button></div></form>`;
  m.showModal();
  $('#f').onsubmit=async(e)=>{e.preventDefault();const fd=new FormData(e.target);fd.append('action',data.id?'update':'create');
    const vErr=validateRequired(targetMod,fd);if(vErr)return toast(vErr,0);
    if(targetMod==='berita'&&!fd.get('published_at'))fd.set('published_at',new Date().toISOString().slice(0,19));
    if(targetMod==='kompetisi') fd.set('kategori','kompetisi');
    if(targetMod==='agenda') fd.set('kategori','agenda');
    for(const [n,t] of SCHEMA[targetMod]){if(t==='textarea'&&['medali','jumlah','tahun','event','sertifikat','tahun_sertifikat','pengalaman','tahun_pengalaman','prestasi','tahun_prestasi'].includes(n)){fd.set(n,(fd.get(n)||'').toString());}}
    const r=await fetch(API[targetMod],{method:'POST',body:fd});const j=await r.json();if(!j.ok)return toast(j.error||'Gagal simpan',0);m.close();toast('Berhasil disimpan');loadPanel(mod);
  };
}

function renderKlasemen(items){const w=$('#table-klasemen'); if(!w)return;
  w.innerHTML=`<div id='rows'>${items.map(it=>`<div class='form-grid'>${['klub','main','menang','poin'].map(k=>`<input class='input' data-k='${k}' value='${esc(it[k]??'')}' placeholder='${k}'>`).join('')}</div>`).join('')}</div><button id='addRow' class='btn secondary' style='margin-top:.6rem'>Tambah Baris</button><button id='saveRows' class='btn' style='margin-top:.6rem'>Simpan</button>`;
  $('#addRow',w).onclick=()=>$('#rows',w).insertAdjacentHTML('beforeend',`<div class='form-grid'>${['klub','main','menang','poin'].map(k=>`<input class='input' data-k='${k}' placeholder='${k}'>`).join('')}</div>`);
  $('#saveRows',w).onclick=async()=>{const rows=$$('#rows > div',w).map(d=>Object.fromEntries(['klub','main','menang','poin'].map(k=>[k,$(`[data-k="${k}"]`,d).value]))); const r=await fetch(API.klasemen+'?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items:rows})}); const j=await r.json(); if(!j.ok)return toast(j.error||'Gagal simpan',0); toast('Tersimpan');};
}

function renderSponsor(items){const w=$('#table-sponsor');w.innerHTML=`<form id='sp'>${Array.from({length:6},(_,i)=>{const x=items[i]||{};return `<div class='form-grid card'><input class='input' name='name_${i}' placeholder='Nama sponsor' value='${esc(x.name||'')}'><input class='input' name='url_${i}' placeholder='URL' value='${esc(x.url||'')}'><input class='input' type='hidden' name='old_image_${i}' value='${esc(x.image||'')}'><input class='input full' type='file' name='image_${i}' accept='image/*'></div>`}).join('')}<button class='btn'>Simpan Sponsor</button></form>`;
  $('#sp').onsubmit=async(e)=>{e.preventDefault();const fd=new FormData(e.target);fd.append('action','save');const r=await fetch(API.sponsor,{method:'POST',body:fd});const j=await r.json();if(!j.ok)return toast(j.error||'Gagal',0);toast('Sponsor tersimpan')};
}

async function loadPanel(mod){const j=await list(mod);if(!j.ok)return toast(j.error||'Gagal load',0); if(mod==='klasemen')return renderKlasemen(j.items||[]); if(mod==='sponsor') return renderSponsor(j.items||[]); renderTable(mod,j.items||[]);}

document.addEventListener('DOMContentLoaded',async()=>{await auth();bindTabs();$('#logout').onclick=async()=>{await fetch(AUTH+'?action=logout');location.href='login_pengurus.html';};$$('.addBtn').forEach(b=>b.onclick=()=>openForm(b.dataset.mod));loadPanel('berita');});