// public/js/prospect.js
async function prospectExternals(bounds, cnaes, cidade, uf) {
  const res = await fetch('/api/externals/prospect', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ bounds, cnaes, cidade, uf })
  });
  return res.json();
}

let pollId;
async function startExternalPolling(bounds, cnae) {
  clearInterval(pollId);
  let tries = 0;
  pollId = setInterval(async () => {
    tries++;
    const qs = new URLSearchParams({ bounds: JSON.stringify(bounds), cnae: cnae || '' });
    const r = await fetch('/api/externals/count?' + qs.toString());
    const { count } = await r.json();
    if (window.updateExternalBadge) updateExternalBadge(count);
    if (tries > 6) clearInterval(pollId); // ~1min (10s*6)
  }, 10000);
}

async function fetchExternals(bounds, cnae, page = 1) {
  const qs = new URLSearchParams({ bounds: JSON.stringify(bounds), cnae: cnae || '', page });
  const r = await fetch('/api/externals?' + qs.toString());
  return r.json();
}

function renderExternalMarkers(items) {
  items.forEach(row => {
    if (!row.latitude || !row.longitude) return;
    const pos = { lat: parseFloat(row.latitude), lng: parseFloat(row.longitude) };
    const marker = new google.maps.Marker({
      position: pos,
      map,
      icon: { url: '/images/marker-external.png', scaledSize: new google.maps.Size(28, 28) }
    });
    if (window.attachHighlightHandlers) attachHighlightHandlers(marker, row);
  });
}

// expõe no escopo global para usar a partir do Blade
window.prospectExternals = prospectExternals;
window.startExternalPolling = startExternalPolling;
window.fetchExternals = fetchExternals;
window.renderExternalMarkers = renderExternalMarkers;
