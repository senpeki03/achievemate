@extends('student.studentsidebar')

@section('title','Application for Graduation')

@section('content')
<link rel="stylesheet" href="{{ asset('css/form.css') }}">

@if (session('ok'))
  <div class="flash">{{ session('ok') }}</div>
@endif

<div class="pdf-wrap">
  <canvas id="pdfCanvas"></canvas>

  <form class="overlay" method="POST" action="{{ route('student.graduation.store') }}" id="gradForm">
    @csrf
    {{-- Inputs injected by JS --}}
  </form>
</div>

<div class="toolbar"></div>

<button type="submit" form="gradForm" class="fab-save">Save Application</button>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
const PDF_URL   = @json($pdfUrl ?? '');
const FIELD_MAP = @json($fields ?? []);
const PREFILL   = @json($prefill ?? []);
const PRETTY_LABEL = {
  surname:'Surname', first_name:'First Name', middle_name:'Middle Name', extension_name:'Ext.',
  sr_code:'SR Code', birthdate:'Birthdate', place_of_birth:'Place of Birth',
  home_address:'Home Address', zip_code:'ZIP', contact_number:'Contact Number', email:'Email',
  secondary_school:'Secondary School Graduated', secondary_year:'Year Graduated',
  elementary_school:'Elementary School Graduated', elementary_year:'Year Graduated',
  college:'College', program:'Program', major:'Major',
};

const isBottomZip = (name) => /^(zip|zip_code|zipcode)$/i.test(name);

const pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let _pdfDoc = null, _page1 = null;
const MAX_WIDTH = 1360;
const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

async function renderPdf() {
  if (!PDF_URL) return;
  if (!_pdfDoc) _pdfDoc = await pdfjsLib.getDocument(PDF_URL).promise;
  _page1 = await _pdfDoc.getPage(1);

  const canvas = document.getElementById('pdfCanvas');
  const ctx = canvas.getContext('2d');

  const unscaled = _page1.getViewport({ scale: 1 });
  const wrapperW = Math.min(MAX_WIDTH, document.querySelector('.pdf-wrap').clientWidth - 28);
  const scale = wrapperW / unscaled.width;
  const viewport = _page1.getViewport({ scale });

  canvas.width  = viewport.width;
  canvas.height = viewport.height;

  await _page1.render({ canvasContext: ctx, viewport }).promise;

  const wrap = document.querySelector('.pdf-wrap');
  wrap.style.height = (canvas.clientHeight + 28) + 'px';
}

function makeFieldBox(name, cfg){
  const box = document.createElement('div');
  box.className = 'f';
  box.dataset.name = name;

  box.style.top  = (cfg.t ?? 0) + '%';
  box.style.left = clamp((cfg.l ?? 0), 0, 98) + '%';
  box.style.width = clamp((cfg.w ?? 28), 10, 90) + '%';

  const lbl = document.createElement('div');
  lbl.className = 'lbl';
  lbl.textContent = PRETTY_LABEL[name] || cfg.ph || name;
  box.appendChild(lbl);

  let control;
  if (cfg.type === 'textarea') {
    control = document.createElement('textarea');
  } else {
    control = document.createElement('input');
    control.type = (name === 'birthdate') ? 'date' : (cfg.type || 'text');
  }
  control.name = name;
  box.appendChild(control);

  if (cfg.hint){
    const hint = document.createElement('div');
    hint.className = 'hint';
    hint.textContent = cfg.hint;
    box.appendChild(hint);
  }
  return box;
}

function buildInputs() {
  const form = document.getElementById('gradForm');

  Object.entries(FIELD_MAP).forEach(([name, cfg]) => {
    if (isBottomZip(name)) return;
    if (['grad_dec','grad_may','grad_mid','grad_dec_year','grad_may_year','grad_mid_year'].includes(name)) return;
    const box = makeFieldBox(name, cfg || {});
    form.appendChild(box);
  });

  const rowCfg = FIELD_MAP['grad_dec'] || { t:52, l:18.8, w:56 };
  const row = document.createElement('div');
  row.className = 'check-row';
  row.dataset.name = 'grad_dates';
  row.style.top  = (rowCfg.t ?? 52) + '%';
  row.style.left = clamp((rowCfg.l ?? 18.8), 0, 98) + '%';
  row.style.width = clamp((rowCfg.w ?? 56), 22, 90) + '%';
  row.innerHTML = `
    <span class="lbl">Graduation Period</span>
    <label class="check">
      <input type="checkbox" name="grad_dec"><span>December</span>
      <input name="grad_dec_year" type="text" inputmode="numeric" placeholder="Year">
    </label>
    <label class="check">
      <input type="checkbox" name="grad_may"><span>May</span>
      <input name="grad_may_year" type="text" inputmode="numeric" placeholder="Year">
    </label>
    <label class="check">
      <input type="checkbox" name="grad_mid"><span>Midterm</span>
      <input name="grad_mid_year" type="text" inputmode="numeric" placeholder="Year">
    </label>`;
  form.appendChild(row);
}

function applyPrefill() {
  Object.entries(PREFILL).forEach(([k,v]) => {
    const el = document.querySelector(`[name="${k}"]`);
    if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA')) el.value = v ?? '';
  });
}

/* ---------------- PSGC ADDRESS ---------------- */
async function fetchPSGC(path) {
  const res = await fetch(`/psgc/${path}`);
  if (!res.ok) throw new Error('PSGC fetch failed');
  const json = await res.json();
  return Array.isArray(json) ? json : (json.data || []);
}

const getRegions = ()           => fetchPSGC('regions');
const getProvs   = (regionCode) => fetchPSGC(`regions/${regionCode}/provinces`);
const getCities  = (provCode)   => fetchPSGC(`provinces/${provCode}/cities-municipalities`);
const getBrgys   = (cityCode)   => fetchPSGC(`cities-municipalities/${cityCode}/barangays`);

function showPSGCError(msg) {
  const wrap = document.querySelector('.addr');
  if (!wrap) return;
  let box = document.getElementById('psgc_err_box');
  if (!box) {
    box = document.createElement('div');
    box.id = 'psgc_err_box';
    box.className = 'psgc-error';
    wrap.insertBefore(box, wrap.firstChild);
  }
  box.textContent = msg;
}

function mountAddressSelectors() {
  const slot = document.querySelector('.overlay [name="home_address"]')?.closest('.f');
  if (!slot) return;

  const { top, left, width } = slot.style;
  const wrap = document.createElement('div');
  wrap.className = 'addr';
  wrap.dataset.name = 'address_block';
  wrap.style.top = top; wrap.style.left = left; wrap.style.width = width;

  wrap.innerHTML = `
    <div class="col-4"><select id="regionSel"><option value="">Select Region</option></select></div>
    <div class="col-4"><select id="provSel" disabled><option value="">Select Province</option></select></div>
    <div class="col-4"><select id="citySel" disabled><option value="">Select City/Municipality</option></select></div>
    <div class="col-4"><select id="brgySel" disabled><option value="">Select Barangay (optional)</option></select></div>
    <div class="col-4 col-province-zip"><input id="zipInput" name="zip_code" type="text" placeholder="ZIP Code" readonly></div>
    <div class="col-12">
      <input id="addrLine" name="home_address" type="text" placeholder="House No./Street/Subdivision (optional)">
      <div class="muted">Auto: <b>Barangay, City/Municipality, Province</b>. You can prepend house/street.</div>
    </div>
    <input type="hidden" name="region_code"   id="region_code">
    <input type="hidden" name="region_name"   id="region_name">
    <input type="hidden" name="province_code" id="province_code">
    <input type="hidden" name="province_name" id="province_name">
    <input type="hidden" name="city_code"     id="city_code">
    <input type="hidden" name="city_name"     id="city_name">
    <input type="hidden" name="barangay_code" id="barangay_code">
    <input type="hidden" name="barangay_name" id="barangay_name">
  `;
  slot.replaceWith(wrap);

  const regionSel = document.getElementById('regionSel');
  const provSel   = document.getElementById('provSel');
  const citySel   = document.getElementById('citySel');
  const brgySel   = document.getElementById('brgySel');
  const addrLine  = document.getElementById('addrLine');
  const zipInput  = document.getElementById('zipInput');

  function reset(sel, label){ sel.innerHTML = `<option value="">${label}</option>`; }
  function fill(sel, items){ items.sort((a,b)=>a.name.localeCompare(b.name)).forEach(x=> sel.add(new Option(x.name, x.code))); }
  function textOf(sel){ return sel.selectedOptions[0]?.text ?? ''; }
  function setHidden(id,val){ document.getElementById(id).value = val||''; }
  function setZip(v){ zipInput.value = v||''; }
  function updateAddressLine(){ addrLine.value = [textOf(brgySel), textOf(citySel), textOf(provSel)].filter(Boolean).join(', '); }

  regionSel.addEventListener('change', async e=>{
    setHidden('region_code', e.target.value);
    setHidden('region_name', textOf(regionSel));
    reset(provSel,'Select Province'); reset(citySel,'Select City/Municipality'); reset(brgySel,'Select Barangay (optional)');
    provSel.disabled = !e.target.value; citySel.disabled = true; brgySel.disabled = true; setZip('');
    if(!e.target.value) { updateAddressLine(); return; }
    try{ fill(provSel, await getProvs(e.target.value)); } catch { showPSGCError('Failed to load provinces.'); }
    updateAddressLine();
  });

  provSel.addEventListener('change', async e=>{
    setHidden('province_code', e.target.value);
    setHidden('province_name', textOf(provSel));
    reset(citySel,'Select City/Municipality'); reset(brgySel,'Select Barangay (optional)');
    citySel.disabled = !e.target.value; brgySel.disabled = true; setZip('');
    if(!e.target.value) { updateAddressLine(); return; }
    try{ fill(citySel, await getCities(e.target.value)); } catch { showPSGCError('Failed to load cities/municipalities.'); }
    updateAddressLine();

    // Auto-fill ZIP when city is selected
    setTimeout(async ()=>{ if(citySel.value) await resolveZip(); },50);
  });

  citySel.addEventListener('change', async e=>{
    setHidden('city_code', e.target.value);
    setHidden('city_name', textOf(citySel));
    reset(brgySel,'Select Barangay (optional)');
    brgySel.disabled = !e.target.value; setZip('');
    if(!e.target.value) { updateAddressLine(); return; }
    try{ fill(brgySel, await getBrgys(e.target.value)); } catch {}
    updateAddressLine();

    // Auto-fill ZIP when city changes
    if(citySel.value) await resolveZip();
  });

  brgySel.addEventListener('change', async e=>{
    setHidden('barangay_code', e.target.value);
    setHidden('barangay_name', textOf(brgySel));
    updateAddressLine();
    if(citySel.value) await resolveZip();
  });

  // Postal index cache
  let postalIndex = null;
  const keyOf = (prov, muni) => (prov+'|'+muni).toLowerCase();

  async function loadPostalLocal(){
    try{
      const r = await fetch('/postal-ph/data', {cache:'no-store', credentials:'same-origin'});
      if(!r.ok) return [];
      const j = await r.json();
      return Array.isArray(j.data)? j.data : [];
    }catch{return [];}
  }

  async function ensurePostalIndex(){
    if(postalIndex) return postalIndex;
    const rows = await loadPostalLocal();
    const map = new Map();
    for(const r of rows){
      const prov = r.province||r.Province;
      const muni = r.municipality||r.city||r.City;
      const zip  = r.postalCode||r.postal_code||r.zip;
      if(!prov||!muni||!zip) continue;
      map.set(keyOf(prov, muni), zip);
    }
    postalIndex = map;
    return map;
  }

  async function resolveZip(){
    const prov = textOf(regionSel);
    const city = textOf(citySel);
    if(!prov||!city){ setZip(''); return; }
    const idx = await ensurePostalIndex();
    setZip(idx.get(keyOf(prov, city))||'');
  }

  async function hydrateFromPrefill(){
    const pf = PREFILL||{};
    const selectByValue = (sel,val)=>{ if(!val) return false; const o=Array.from(sel.options).find(o=>o.value==val); if(o){sel.value=val; return true;} return false; };

    if(pf.region_code&&selectByValue(regionSel,pf.region_code)) provSel.disabled=false;
    if(pf.province_code&&selectByValue(provSel,pf.province_code)) citySel.disabled=false;
    if(pf.city_code&&selectByValue(citySel,pf.city_code)) brgySel.disabled=false;
    if(pf.barangay_code) selectByValue(brgySel,pf.barangay_code);

    setHidden('region_code',regionSel.value); setHidden('region_name',textOf(regionSel));
    setHidden('province_code',provSel.value); setHidden('province_name',textOf(provSel));
    setHidden('city_code',citySel.value); setHidden('city_name',textOf(citySel));
    setHidden('barangay_code',brgySel.value); setHidden('barangay_name',textOf(brgySel));
    updateAddressLine();
    await resolveZip();
  }
}

/* ------------ Grid / PDF layout ------------ */
const SPAN_HINTS={surname:3,first_name:3,middle_name:3,extension_name:2,place_of_birth:3,sr_code:2,birthdate:2,contact_number:3,email:5,home_address:12,
secondary_school:8,secondary_year:4,elementary_school:8,elementary_year:4,college:12,program:12,major:12};

const ORDER_INDEX={surname:1,first_name:2,middle_name:3,extension_name:4,sr_code:5,birthdate:6,place_of_birth:7,contact_number:8,email:9,address_block:10,
secondary_school:20,secondary_year:21,elementary_school:22,elementary_year:23,college:30,program:31,grad_dates:32,major:33};

function snapshotAbsolutePositions(){
  const overlay=document.getElementById('gradForm');
  overlay.querySelectorAll('.f,.check-row,.addr').forEach(el=>{
    el.dataset.absTop=el.style.top||'';
    el.dataset.absLeft=el.style.left||'';
    el.dataset.absW=el.style.width||'';
  });
}

function applyGridItemSpan(el,span){
  el.classList.remove('span-2','span-3','span-4','span-5','span-6','span-8','span-12');
  el.classList.add(span>=12?'span-12':span>=8?'span-8':span>=6?'span-6':span>=5?'span-5':span>=4?'span-4':span>=3?'span-3':'span-2');
}

function enableGridMode(){
  const overlay=document.getElementById('gradForm');
  overlay.classList.add('grid-mode');
  overlay.querySelectorAll('.f,.check-row,.addr').forEach(el=>{
    el.style.top=''; el.style.left=''; el.style.width='';
    const name=el.dataset.name||'';
    let span=SPAN_HINTS[name]||3;
    if(name==='address_block'||name==='grad_dates') span=12;
    if(el.querySelector('textarea')) span=Math.max(span,4);
    applyGridItemSpan(el,span);
    el.style.order=ORDER_INDEX[name]??100;
  });
}

function enablePdfMode(){
  const overlay=document.getElementById('gradForm');
  overlay.classList.remove('grid-mode');
  overlay.querySelectorAll('.f,.check-row,.addr').forEach(el=>{
    el.classList.remove('span-2','span-3','span-4','span-5','span-6','span-8','span-12');
    el.style.order='';
    el.style.top=el.dataset.absTop||el.style.top;
    el.style.left=el.dataset.absLeft||el.style.left;
    el.style.width=el.dataset.absW||el.style.width;
  });
}

let gridOn=true;

(async function init(){
  try { await renderPdf(); } catch(e){console.error(e);}
  buildInputs();
  applyPrefill();
  mountAddressSelectors();
  snapshotAbsolutePositions();
  enableGridMode();
  let t;
  window.addEventListener('resize', ()=>{
    clearTimeout(t);
    t=setTimeout(async ()=>{
      _pdfDoc=null;
      await renderPdf();
      gridOn?enableGridMode():enablePdfMode();
    },100);
  });
})();
</script>
@endsection
