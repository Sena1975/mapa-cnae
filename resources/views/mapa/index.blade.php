<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mapa de Clientes por CNAE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    #map { width: 100%; height: 65vh; }
    .legend-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
    .table-scroll { max-height: 40vh; overflow: auto; }
    th.sticky { position: sticky; top: 0; background: #fff; z-index: 1; }
    th.sortable { cursor: pointer; user-select: none; }
    .btn { padding: 0.375rem 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; background: #fff; }
    .btn[disabled] { opacity:.5; cursor:not-allowed; }
    .menu { position: relative; display: inline-block; }
    .menu-panel { position:absolute; right:0; top:100%; z-index:30; min-width: 220px; max-height: 50vh; overflow:auto; background:#fff; border:1px solid #e5e7eb; border-radius:0.5rem; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    .menu-item { display:flex; align-items:center; gap:.5rem; padding:.5rem .75rem; }
    .hidden { display:none; }
  </style>
</head>
<body class="bg-gray-50">
  <div class="max-w-7xl mx-auto p-4 space-y-4">
    <h1 class="text-2xl font-semibold">Mapa de Clientes por CNAE</h1>

    <!-- Filtros -->
    <div class="grid md:grid-cols-6 gap-3 bg-white p-4 rounded-xl shadow">
      <div>
        <input id="cidade" class="border rounded px-3 py-2 w-full" placeholder="Cidade" list="dl-cidades" />
        <datalist id="dl-cidades"></datalist>
      </div>
      <div>
        <input id="cnae" class="border rounded px-3 py-2 w-full" placeholder="CNAE principal" list="dl-cnaes" />
        <datalist id="dl-cnaes"></datalist>
      </div>
      <div>
        <input id="equipe" class="border rounded px-3 py-2 w-full" placeholder="Equipe de vendas (Supervisor)" list="dl-equipes" />
        <datalist id="dl-equipes"></datalist>
      </div>
      <div>
        <input id="vendedor" class="border rounded px-3 py-2 w-full" placeholder="Vendedor" list="dl-vendedores" />
        <datalist id="dl-vendedores"></datalist>
      </div>
      <div>
        <input id="ramo" class="border rounded px-3 py-2 w-full" placeholder="Ramo de atividade" list="dl-ramos" />
        <datalist id="dl-ramos"></datalist>
      </div>
      <div class="flex items-center gap-2">
        <input id="incluirExternos" type="checkbox" class="w-4 h-4" />
        <label for="incluirExternos">Incluir externos (mesmo CNAE)</label>
        <span id="externalsBadge" class="ml-2 text-xs px-2 py-1 rounded-full bg-red-100 text-red-700 hidden"></span>
      </div>
      <div class="md:col-span-6 flex items-center gap-3">
        <button id="btnAplicar" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Aplicar</button>
        <a id="btnExportar" class="px-4 py-2 bg-emerald-600 text-white rounded-lg" href="#">Exportar Excel</a>
        <div class="ml-auto text-sm text-gray-600">
          <span class="legend-dot" style="background:#2563eb"></span>Internos
          <span class="legend-dot ml-4" style="background:#dc2626"></span>Externos
        </div>
      </div>
    </div>

    <!-- Mapa -->
    <div id="map" class="rounded-xl shadow bg-white"></div>

    <!-- Barra do grid -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-2">
      <div class="flex items-center gap-2 flex-wrap">
        <input id="buscaGrid" class="border rounded px-3 py-2 min-w-[260px]" placeholder="Buscar no grid (nome, endereço, cidade, CNAE)..." />
        <label class="flex items-center gap-2 text-sm text-gray-700 ml-2">
          <input id="syncMap" type="checkbox" class="w-4 h-4" />
          Sincronizar mapa com a busca do grid
        </label>
        <span class="text-sm text-gray-600 ml-2">
          <span id="resumoCount">0</span> registros
        </span>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Colunas -->
        <div class="menu">
          <button id="btnCols" class="btn">Colunas ▾</button>
          <div id="menuCols" class="menu-panel hidden">
            <div class="menu-item"><label><input type="checkbox" data-col="origem" checked /> Origem</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="nome" checked /> Nome</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="endereco" checked /> Endereço</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="cidade" checked /> Cidade</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="uf" checked /> UF</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="cnae" checked /> CNAE</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="faturamento_medio" checked /> Faturamento Médio</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="data_ult_compra" checked /> Últ. Compra</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="lat" checked /> Latitude</label></div>
            <div class="menu-item"><label><input type="checkbox" data-col="lng" checked /> Longitude</label></div>
          </div>
        </div>
        <!-- CSV -->
        <button id="btnCsv" class="btn">Copiar CSV (filtrado)</button>

        <!-- Paginação -->
        <label class="text-sm text-gray-600 ml-2">Por página</label>
        <select id="pageSize" class="border rounded px-2 py-1">
          <option>25</option>
          <option>50</option>
          <option selected>100</option>
          <option>200</option>
        </select>
        <div class="flex items-center gap-1">
          <button id="pgFirst" class="btn" title="Primeira">«</button>
          <button id="pgPrev"  class="btn" title="Anterior">‹</button>
          <span id="pgInfo" class="px-2 text-sm text-gray-700"></span>
          <button id="pgNext"  class="btn" title="Próxima">›</button>
          <button id="pgLast"  class="btn" title="Última">»</button>
        </div>
      </div>
    </div>

    <!-- Grid -->
    <div class="bg-white rounded-xl shadow">
      <div class="table-scroll">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="sticky px-3 py-2 text-left w-24" data-col="origem">Origem</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="nome" data-col="nome">Nome</th>
              <th class="sticky px-3 py-2 text-left" data-col="endereco">Endereço</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="cidade" data-col="cidade">Cidade</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="uf" data-col="uf">UF</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="cnae" data-col="cnae">CNAE</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="faturamento_medio" data-col="faturamento_medio">Faturamento Médio</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="data_ult_compra" data-col="data_ult_compra">Últ. Compra</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="lat" data-col="lat">Latitude</th>
              <th class="sticky px-3 py-2 text-left sortable" data-key="lng" data-col="lng">Longitude</th>
            </tr>
          </thead>
          <tbody id="gridBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Clusterer -->
  <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
  <!-- Prospect (externos) -->
  <script src="{{ asset('js/prospect.js') }}"></script>

  <script>
    // ===== Logger de erros para facilitar debug =====
    window.onerror = function (msg, src, line, col, err) {
      console.error('[JS ERROR]', msg, 'em', src + ':' + line + ':' + col, err || '');
    };

    // === Estado global ===
    const GOOGLE_MAPS_KEY = "{{ $googleKey }}";
    let map, markers = [], clusterer = null, abortCtrl = null;

    const markerMap = new Map();
    let highlightedMarker = null;
    let highlightTimer = null;

    let currentData = [];     // internos + externos
    let filteredData = [];    // após busca do grid
    let sortState = { key: 'nome', dir: 'asc' };
    let pageState = { page: 1, size: 100, totalPages: 1 };

    const allColumns = ['origem','nome','endereco','cidade','uf','cnae','faturamento_medio','data_ult_compra','lat','lng'];
    const visibleCols = new Set(allColumns);

    // Badge de externos
    window.updateExternalBadge = function(count){
      const el = document.getElementById('externalsBadge');
      if (!el) return;
      if (count && Number(count) > 0) {
        el.textContent = `Externos (beta): ${count}`;
        el.classList.remove('hidden');
      } else {
        el.classList.add('hidden');
        el.textContent = '';
      }
    };

    // === Utils ===
    function debounce(fn, wait=300){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),wait); } }
    const safe = (v, d='—') => (v === undefined || v === null || v === '' ? d : v);
    const fmtMoney = v => (v === null || v === undefined || v === '' || isNaN(v)) ? '—'
                      : Number(v).toLocaleString('pt-BR', { style:'currency', currency:'BRL', maximumFractionDigits:0 });
    const fmtDate = s => { if (!s) return '—'; const d = new Date(s); return isNaN(d.getTime()) ? s : d.toLocaleDateString('pt-BR'); };
    const norm = (s) => ('' + (s ?? '')).normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();
    const fmtLatLng = n => (typeof n === 'number' && isFinite(n)) ? n.toFixed(6) : (typeof n === 'string' && !isNaN(+n) ? (+n).toFixed(6) : '—');

    function assignUids(arr){
      const seen = new Set();
      arr.forEach((it, idx) => {
        const base = (it.origem || 'interno') + '|' + (it.codigo || it.cnpj || it.place_id || it.nome || 's/ident');
        let uid = base;
        if (seen.has(uid)) uid = base + '|' + idx;
        seen.add(uid);
        it.uid = uid;
      });
    }

    // === Autocomplete (datalist) ===
    const fetchOptionsDebounced = debounce(async (endpoint, q, datalistId) => {
      try {
        const url = new URL(endpoint, window.location.origin);
        if (q) url.searchParams.set('q', q);
        url.searchParams.set('limit', 25);
        const res = await fetch(url);
        const arr = await res.json();
        const dl = document.getElementById(datalistId); dl.innerHTML='';
        arr.forEach(v => { const o=document.createElement('option'); o.value=v; dl.appendChild(o); });
      } catch {}
    }, 250);

    function bindAutocomplete(id, endpoint, dl) {
      const el = document.getElementById(id);
      el.addEventListener('input', e => fetchOptionsDebounced(endpoint, e.target.value, dl));
      el.addEventListener('focus', () => fetchOptionsDebounced(endpoint, el.value, dl));
    }

    // === Marcadores (mapa) ===
    function clearMarkers(){
      if (clusterer){ clusterer.clearMarkers(); clusterer = null; }
      markers.forEach(m => m.setMap(null));
      markers = [];
      markerMap.clear();
    }

    function baseIcon(isExterno){
      return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 7,
        fillColor: isExterno ? '#dc2626' : '#2563eb',
        fillOpacity: 1,
        strokeWeight: 0
      };
    }

    window.focusMarker = function(uid, lat, lng){
      const m = markerMap.get(uid);
      const pos = m ? m.getPosition() : new google.maps.LatLng(Number(lat), Number(lng));
      map.panTo(pos);
      map.setZoom(Math.max(map.getZoom(), 16));
      if (m) {
        google.maps.event.trigger(m, 'click');
        highlightMarker(m);
      }
    };

    function makeMarker(item){
      const isExterno = item.origem === 'externo';
      const icon = baseIcon(isExterno);
      const z = isExterno ? 10 : 100;

      const marker = new google.maps.Marker({
        position: { lat: Number(item.lat), lng: Number(item.lng) },
        icon, zIndex: z, title: item.nome || ''
      });
      marker._originalIcon = icon;
      marker._isExterno = isExterno;

      const partsTop = [item.endereco, item.numero].filter(Boolean).join(', ');
      const partsMid = [item.bairro, item.cidade, item.uf].filter(Boolean).join(' - ');
      const cepStr   = item.cep || '';

      const uidJs = (item.uid || '').replace(/'/g, "\\'");
      let content = '<div class="text-sm">';
      content += '<div class="font-semibold">' + safe(item.nome) + '</div>';
      content += '<div><strong>Endereço:</strong> ' + (partsTop || '—') + '</div>';
      if (partsMid) content += '<div>' + partsMid + '</div>';
      if (cepStr)   content += '<div>' + cepStr + '</div>';
      content += '<div class="mt-1"><strong>CNAE:</strong> ' + safe(item.cnae) + '</div>';
      content += '<div><strong>Faturamento Médio:</strong> ' + fmtMoney(item.faturamento_medio) + '</div>';
      content += '<div><strong>Últ. Compra:</strong> ' + fmtDate(item.data_ult_compra) + '</div>';
      content += '<div class="mt-1 text-xs text-gray-500">' + (isExterno ? 'externo' : 'interno') + '</div>';
      content += '<div class="mt-2">';
      content +=   '<button style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer"';
      content +=     ' onclick="window.focusMarker(\'' + uidJs + '\',' + Number(item.lat) + ',' + Number(item.lng) + ')">🔎 Recentrar e destacar</button>';
      content += '</div>';
      content += '</div>';

      marker.addListener('click', () => {
        if (!window._info) window._info = new google.maps.InfoWindow();
        window._info.setContent(content);
        window._info.open({ anchor: marker, map });
      });

      if (item.uid) markerMap.set(item.uid, marker);
      return marker;
    }

    function highlightMarker(marker){
      if (highlightTimer) { clearTimeout(highlightTimer); highlightTimer = null; }
      if (highlightedMarker){
        highlightedMarker.setAnimation(null);
        highlightedMarker.setIcon(highlightedMarker._originalIcon);
        highlightedMarker = null;
      }
      if (!marker) return;

      highlightedMarker = marker;
      const bigIcon = {
        ...marker._originalIcon,
        scale: 11,
        strokeWeight: 2,
        strokeColor: '#111827',
        fillOpacity: 1
      };
      marker.setIcon(bigIcon);
      marker.setAnimation(google.maps.Animation.BOUNCE);

      highlightTimer = setTimeout(() => {
        if (!highlightedMarker) return;
        highlightedMarker.setAnimation(null);
        highlightedMarker.setIcon(highlightedMarker._originalIcon);
        highlightedMarker = null;
      }, 1400);
    }

    function renderMarkersFrom(dataset){
      clearMarkers();
      const points = dataset.filter(p => p.lat && p.lng);
      let bounds = new google.maps.LatLngBounds();
      points.forEach(it => {
        const m = makeMarker(it);
        markers.push(m);
        bounds.extend({ lat: Number(it.lat), lng: Number(it.lng) });
      });
      if (markers.length){
        clusterer = new markerClusterer.MarkerClusterer({ map, markers, maxZoom: 17 });
      }
    }

    // === GRID core ===
    function applySearch() {
      const q = norm(document.getElementById('buscaGrid').value);
      if (!q) { filteredData = [...currentData]; return; }
      filteredData = currentData.filter(it => {
        const nome = norm(it.nome);
        const end1 = norm([it.endereco, it.numero].filter(Boolean).join(', '));
        const end2 = norm([it.bairro, it.cidade, it.uf].filter(Boolean).join(' - '));
        const cnae = norm(it.cnae);
        return nome.includes(q) || end1.includes(q) || end2.includes(q) || cnae.includes(q);
      });
    }

    function applySort(key = sortState.key, dir = sortState.dir){
      sortState = { key, dir };
      const numeric = new Set(['faturamento_medio','lat','lng']);
      const dateKeys = new Set(['data_ult_compra']);
      const val = (o) => (o[key] ?? '');
      filteredData.sort((a,b) => {
        let va = val(a), vb = val(b);
        if (dateKeys.has(key)) {
          const da = new Date(va), db = new Date(vb);
          const na = isNaN(da.getTime()) ? 0 : da.getTime();
          const nb = isNaN(db.getTime()) ? 0 : db.getTime();
          return (na - nb) * (dir === 'asc' ? 1 : -1);
        }
        if (numeric.has(key)) {
          const na = Number(va) || 0, nb = Number(vb) || 0;
          return (na - nb) * (dir === 'asc' ? 1 : -1);
        }
        const sa = String(va).toLocaleLowerCase('pt-BR');
        const sb = String(vb).toLocaleLowerCase('pt-BR');
        if (sa < sb) return dir === 'asc' ? -1 : 1;
        if (sa > sb) return dir === 'asc' ? 1 : -1;
        return 0;
      });
    }

    function applyPagination() {
      const total = filteredData.length;
      pageState.totalPages = Math.max(1, Math.ceil(total / pageState.size));
      pageState.page = Math.min(pageState.page, pageState.totalPages);

      const start = (pageState.page - 1) * pageState.size;
      const end   = start + pageState.size;
      const slice = filteredData.slice(start, end);

      document.getElementById('resumoCount').textContent = total;
      document.getElementById('pgInfo').textContent = `pág. ${pageState.page} / ${pageState.totalPages}`;

      document.getElementById('pgFirst').disabled = pageState.page === 1;
      document.getElementById('pgPrev').disabled  = pageState.page === 1;
      document.getElementById('pgNext').disabled  = pageState.page >= pageState.totalPages;
      document.getElementById('pgLast').disabled  = pageState.page >= pageState.totalPages;

      renderGrid(slice);
    }

    function renderGrid(rows){
      const body = document.getElementById('gridBody');
      body.innerHTML = '';
      const frag = document.createDocumentFragment();

      rows.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        tr.dataset.uid = item.uid;

        const tdOrig = document.createElement('td');
        tdOrig.className = 'px-3 py-2 whitespace-nowrap';
        tdOrig.dataset.col = 'origem';
        const dot = document.createElement('span');
        dot.className = 'inline-block w-2.5 h-2.5 rounded-full mr-2 align-middle';
        dot.style.background = (item.origem === 'externo') ? '#dc2626' : '#2563eb';
        tdOrig.appendChild(dot);
        tdOrig.appendChild(document.createTextNode(item.origem || '-'));
        tr.appendChild(tdOrig);

        const tdNome = document.createElement('td');
        tdNome.className = 'px-3 py-2';
        tdNome.dataset.col = 'nome';
        tdNome.textContent = item.nome || '—';
        tr.appendChild(tdNome);

        const tdEnd = document.createElement('td');
        tdEnd.className = 'px-3 py-2';
        tdEnd.dataset.col = 'endereco';
        const partsTop = [item.endereco, item.numero].filter(Boolean).join(', ');
        const partsMid = [item.bairro, item.cidade, item.uf].filter(Boolean).join(' - ');
        const cepStr   = item.cep || '';
        const endStr = [partsTop, partsMid, cepStr].filter(Boolean).join(' | ');
        tdEnd.textContent = endStr || '—';
        tr.appendChild(tdEnd);

        const tdCid = document.createElement('td'); tdCid.className='px-3 py-2'; tdCid.dataset.col='cidade'; tdCid.textContent=item.cidade || '—'; tr.appendChild(tdCid);
        const tdUf  = document.createElement('td'); tdUf.className='px-3 py-2'; tdUf.dataset.col='uf'; tdUf.textContent=item.uf || '—'; tr.appendChild(tdUf);
        const tdCnae= document.createElement('td'); tdCnae.className='px-3 py-2'; tdCnae.dataset.col='cnae'; tdCnae.textContent=item.cnae || '—'; tr.appendChild(tdCnae);
        const tdFat = document.createElement('td'); tdFat.className='px-3 py-2'; tdFat.dataset.col='faturamento_medio'; tdFat.textContent=fmtMoney(item.faturamento_medio); tr.appendChild(tdFat);
        const tdUlt = document.createElement('td'); tdUlt.className='px-3 py-2'; tdUlt.dataset.col='data_ult_compra'; tdUlt.textContent=fmtDate(item.data_ult_compra); tr.appendChild(tdUlt);

        const tdLat = document.createElement('td'); tdLat.className='px-3 py-2'; tdLat.dataset.col='lat'; tdLat.textContent=fmtLatLng(item.lat); tr.appendChild(tdLat);
        const tdLng = document.createElement('td'); tdLng.className='px-3 py-2'; tdLng.dataset.col='lng'; tdLng.textContent=fmtLatLng(item.lng); tr.appendChild(tdLng);

        tr.addEventListener('click', () => {
          if (!item.lat || !item.lng) return;
          const m = markerMap.get(tr.dataset.uid);
          map.panTo({lat: Number(item.lat), lng: Number(item.lng)});
          map.setZoom(Math.max(map.getZoom(), 16));
          if (m) {
            google.maps.event.trigger(m, 'click');
            highlightMarker(m);
          }
        });

        frag.appendChild(tr);
      });

      body.appendChild(frag);
      applyColumnVisibility();
    }

    function refreshGrid(){
      applySearch();
      applySort();
      applyPagination();
      if (document.getElementById('syncMap').checked) renderMarkersFrom(filteredData);
    }

    function applyColumnVisibility(){
      document.querySelectorAll('thead [data-col]').forEach(th=>{
        th.style.display = visibleCols.has(th.dataset.col) ? '' : 'none';
      });
      document.querySelectorAll('#gridBody [data-col]').forEach(td=>{
        td.style.display = visibleCols.has(td.dataset.col) ? '' : 'none';
      });
    }

    function toCsvValue(v){ if (v === null || v === undefined) return ''; const s = String(v).replace(/"/g,'""'); return `"${s}"`; }
    function buildCsvFrom(data){
      const cols = allColumns.filter(c => visibleCols.has(c));
      const labels = { origem:'Origem', nome:'Nome', endereco:'Endereço', cidade:'Cidade', uf:'UF', cnae:'CNAE',
                       faturamento_medio:'Faturamento Médio', data_ult_compra:'Últ. Compra', lat:'Latitude', lng:'Longitude' };
      const headers = cols.map(c => ({ key:c, label:labels[c] }));

      const lines = [];
      lines.push(headers.map(h => toCsvValue(h.label)).join(';'));
      data.forEach(it=>{
        const partsTop = [it.endereco, it.numero].filter(Boolean).join(', ');
        const partsMid = [it.bairro, it.cidade, it.uf].filter(Boolean).join(' - ');
        const cepStr   = it.cep || '';
        const endStr = [partsTop, partsMid, cepStr].filter(Boolean).join(' | ');

        const row = headers.map(h=>{
          let v = it[h.key];
          if (h.key === 'endereco') v = endStr || '';
          if (h.key === 'faturamento_medio') v = (it.faturamento_medio ?? '');
          if (h.key === 'data_ult_compra')   v = (it.data_ult_compra ?? '');
          if (h.key === 'lat') v = fmtLatLng(it.lat);
          if (h.key === 'lng') v = fmtLatLng(it.lng);
          return toCsvValue(v ?? '');
        });
        lines.push(row.join(';'));
      });
      return lines.join('\r\n');
    }
    async function copyCsv(){
      const csv = buildCsvFrom(filteredData);
      try {
        await navigator.clipboard.writeText(csv);
        alert('CSV (filtrado) copiado para a área de transferência.');
      } catch (e) {
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'clientes_filtrados.csv';
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
        alert('Não foi possível copiar. Baixei o CSV como arquivo.');
      }
    }

    // === Merge dos externos via /api/externals ===
    async function fetchAndMergeExternals(bounds, cnaeFilter = ''){
      if (typeof window.fetchExternals !== 'function') return;

      const params = new URLSearchParams();
      params.set('bounds', JSON.stringify(bounds));
      if (cnaeFilter) params.set('cnae', cnaeFilter);

      const url = '/api/externals?' + params.toString();
      const page1 = await fetch(url).then(r => r.json());
      const data = (page1.data ?? page1.items ?? []);
      if (!Array.isArray(data) || !data.length) return;

      const externos = data.map(r => ({
        origem: 'externo',
        nome: r.nome_fantasia || r.razao_social || '(sem nome)',
        endereco: r.endereco || '',
        numero: r.numero || '',
        bairro: r.bairro || '',
        cidade: r.cidade || '',
        uf: r.uf || '',
        cep: r.cep || '',
        cnae: r.codigo_cnae ? (r.codigo_cnae + (r.descricao_cnae ? ' - ' + r.descricao_cnae : '')) : '',
        faturamento_medio: null,
        data_ult_compra: null,
        lat: r.latitude,
        lng: r.longitude,
        cnpj: r.cnpj,
      }));

      const seenCnpj = new Set(currentData.filter(x => x.cnpj).map(x => x.cnpj));
      const novos = externos.filter(x => !x.cnpj || !seenCnpj.has(x.cnpj));
      if (!novos.length) return;

      currentData = [...currentData, ...novos];
      assignUids(currentData);
      refreshGrid();

      // adiciona marcadores dos novos
      novos.forEach(it => {
        if (!it.lat || !it.lng) return;
        const m = makeMarker(it);
        markers.push(m);
        if (clusterer) clusterer.addMarker(m);
      });
    }

    // Poll simples (6 tentativas / 10s)
    async function pollExternals(bounds, cnae = '', tries = 6) {
      for (let i = 0; i < tries; i++) {
        await fetchAndMergeExternals(bounds, cnae);
        if (currentData.some(x => x.origem === 'externo')) return;
        await new Promise(r => setTimeout(r, 10000));
      }
    }

    // === Carregar dados + mapa ===
    async function carregarDados({fitOnData}={fitOnData:false}){
      try {
        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();

        const cidade=document.getElementById('cidade').value.trim();
        const cnae=document.getElementById('cnae').value.trim();
        const equipe=document.getElementById('equipe').value.trim();
        const vendedor=document.getElementById('vendedor').value.trim();
        const ramo=document.getElementById('ramo').value.trim();
        const incluirExternos=document.getElementById('incluirExternos').checked;

        const qs=new URLSearchParams({cidade,cnae,equipe,vendedor,ramo,limit:1000});
        // internos (apenas)
        const internos = await fetch('/api/clientes?'+qs,{signal:abortCtrl.signal}).then(r=>r.json());

        currentData = [...(internos || [])];
        assignUids(currentData);
        pageState.page = 1;

        // GRID
        refreshGrid();

        // MAPA — renderiza internos
        clearMarkers();
        const all = currentData.filter(p=>p.lat && p.lng);
        let bounds=new google.maps.LatLngBounds();
        all.forEach(it=>{
          const m=makeMarker(it);
          markers.push(m);
          bounds.extend({lat:Number(it.lat),lng:Number(it.lng)});
        });
        if (markers.length){
          clusterer=new markerClusterer.MarkerClusterer({map,markers,maxZoom:17});
        }
        if (!bounds.isEmpty() && fitOnData){ map.fitBounds(bounds); }

        // Externos (novo fluxo)
        if (incluirExternos && typeof window.prospectExternals === 'function' && map && map.getBounds) {
          const b = map.getBounds();
          if (b) {
            const boundsObj = {
              n: b.getNorthEast().lat(),
              e: b.getNorthEast().lng(),
              s: b.getSouthWest().lat(),
              w: b.getSouthWest().lng()
            };
            // CNAEs dos internos
            const cnaesSeed = [...new Set((internos || [])
              .map(x => (x.cnae || x.codigo_cnae || '').toString().replace(/\D/g,'').slice(0,7))
              .filter(v => v.length === 7))];

            if (cnaesSeed.length) {
              await window.prospectExternals(boundsObj, cnaesSeed, cidade || null, null);
              window.startExternalPolling(boundsObj, cnae || '');
              pollExternals(boundsObj, cnae || ''); // tenta ~1 min
            }
          }
        }
      } catch (err) {
        console.error('Erro ao carregar dados', err);
      }
    }

    // === initMap (deve existir ANTES do script do Google) ===
    window.initMap = function(){
      if (!GOOGLE_MAPS_KEY){ alert('Configure GOOGLE_MAPS_KEY no .env e rode php artisan config:clear'); return; }

      map = new google.maps.Map(document.getElementById('map'), {
        center:{lat:-12.9714,lng:-38.5014},
        zoom:12,
        mapTypeControl:false,
        streetViewControl:false
      });

      bindAutocomplete('cidade','/api/filtros/cidades','dl-cidades');
      bindAutocomplete('cnae','/api/filtros/cnaes','dl-cnaes');
      bindAutocomplete('equipe','/api/filtros/equipes','dl-equipes');
      bindAutocomplete('vendedor','/api/filtros/vendedores','dl-vendedores');
      bindAutocomplete('ramo','/api/filtros/ramos','dl-ramos');

      document.getElementById('btnAplicar').addEventListener('click',()=>carregarDados({fitOnData:true}));
      document.getElementById('btnExportar').addEventListener('click',(e)=>{
        e.preventDefault();
        const cidade=document.getElementById('cidade').value.trim();
        const cnae=document.getElementById('cnae').value.trim();
        const equipe=document.getElementById('equipe').value.trim();
        const vendedor=document.getElementById('vendedor').value.trim();
        const ramo=document.getElementById('ramo').value.trim();
        const incluirExternos=document.getElementById('incluirExternos').checked;
        const qs=new URLSearchParams({cidade,cnae,equipe,vendedor,ramo,include_externals:incluirExternos});
        window.location.href='/export/excel?'+qs;
      });

      document.querySelectorAll('th.sortable').forEach(th=>{
        th.addEventListener('click', ()=>{
          const key = th.dataset.key;
          const dir = (sortState.key === key && sortState.dir === 'asc') ? 'desc' : 'asc';
          sortState = { key, dir };
          refreshGrid();
          document.querySelectorAll('th.sortable').forEach(h => h.classList.remove('text-blue-600'));
          th.classList.add('text-blue-600');
        });
      });

      document.getElementById('buscaGrid').addEventListener('input', debounce(()=>{
        pageState.page = 1;
        refreshGrid();
      }, 250));
      document.getElementById('pageSize').addEventListener('change', (e)=>{
        pageState.size = parseInt(e.target.value, 10) || 100;
        pageState.page = 1;
        refreshGrid();
      });
      document.getElementById('pgFirst').addEventListener('click', ()=>{ pageState.page = 1; refreshGrid(); });
      document.getElementById('pgPrev').addEventListener('click', ()=>{ pageState.page = Math.max(1, pageState.page-1); refreshGrid(); });
      document.getElementById('pgNext').addEventListener('click', ()=>{ pageState.page = Math.min(pageState.totalPages, pageState.page+1); refreshGrid(); });
      document.getElementById('pgLast').addEventListener('click', ()=>{ pageState.page = pageState.totalPages; refreshGrid(); });

      const menuBtn = document.getElementById('btnCols');
      const menu = document.getElementById('menuCols');
      menuBtn.addEventListener('click', (e)=>{ e.stopPropagation(); menu.classList.toggle('hidden'); });
      document.addEventListener('click', ()=> menu.classList.add('hidden'));
      menu.querySelectorAll('input[type="checkbox"][data-col]').forEach(chk=>{
        chk.addEventListener('change', ()=>{
          const col = chk.dataset.col;
          if (chk.checked) visibleCols.add(col); else visibleCols.delete(col);
          applyColumnVisibility();
        });
      });

      document.getElementById('btnCsv').addEventListener('click', copyCsv);

      carregarDados({fitOnData:true});
    };
  </script>

  <!-- Google Maps (callback chama window.initMap já definido acima) -->
  <script src="https://maps.googleapis.com/maps/api/js?key={{ $googleKey }}&callback=initMap&v=weekly&language=pt-BR&region=BR" async defer></script>
</body>
</html>
