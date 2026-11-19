/* Shared by the five mockups: the admin shell, one set of sample data, and the small pieces every layout reuses
   (decision, landlord, units, documents, activity), so the five differ only in layout. Sample data is property 76,
   "Two-Bedroom House near SRP Talisay", set to Pending so the decision block shows. Not production code. */
window.tailwind = { config: { theme: { extend: {
  fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
  colors: { ink: '#060D26', coral: '#FF8A66', 'coral-d': '#E96F4F', muted: '#5B6A8E', line: '#E2E4EC', canvas: '#F7F8FC', tint: '#ECEEF6' },
} } } };

const DATA = {
  title: 'Two-Bedroom House near SRP Talisay',
  address: 'Bulacao, Talisay City, Cebu',
  type: 'House', city: 'Bulacao, Talisay City', created: 'Oct 31, 2026', updated: 'Oct 31, 2026', waiting: 'Oct 31, 2026',
  lat: 10.2571, lng: 123.8479,
  description: 'Two-bedroom house with parking and a washing machine, twenty minutes from the SRP. Quiet street, pets welcome.',
  photos: [
    'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&q=80',
    'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=1200&q=80',
    'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&q=80',
  ],
  landlord: { name: 'Lourdes Pepito', email: 'lourdes.pepito@abangananhub.com', rating: '4.3', reviews: 3 },
  units: [{ label: 'Whole house', rent: '₱8,000.00', availability: 'Available' }],
  activity: [
    ['Landlord', 'Added unit "Whole house"', 'Oct 31 · 5:30 AM'],
    ['Landlord', 'Created the listing "Two-Bedroom House near SRP Talisay"', 'Oct 31 · 5:30 AM'],
    ['Tenant', 'Jasmine Lim left a 5-star review', 'Sep 6 · 5:31 AM'],
    ['Tenant', 'Carlo Bacalso left a 4-star review', 'Aug 14 · 5:31 AM'],
    ['Tenant', 'Nicole Ortega left a 4-star review', 'Jul 22 · 5:31 AM'],
  ],
};
window.DATA = DATA;

const ICON = {
  back: '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>',
  ext: '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5-7.5H21m0 0v7.5M21 3l-9 9"/></svg>',
  check: '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>',
  warn: '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5m0 3.5h.01"/></svg>',
};
window.ICON = ICON;

const chip = (text, tone = 'neutral') => `<span class="inline-flex h-[22px] items-center whitespace-nowrap rounded-md px-1.5 text-[12px] font-semibold ${{
  green: 'bg-[#22C55E]/[0.12] text-[#15803D]', amber: 'bg-[#FBBF24]/[0.16] text-[#B45309]', red: 'bg-[#EF4444]/[0.08] text-[#DC2626]', neutral: 'bg-tint text-muted',
}[tone]}">${text}</span>`;
window.chip = chip;

const card = (inner, cls = '') => `<div class="rounded-xl border border-line bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] ${cls}">${inner}</div>`;

const SLOTS = {
  decision: (el) => card(`
    <div class="p-4">
      <h2 class="text-[14px] font-semibold">Your decision</h2>
      <p class="text-[12.5px] text-muted">Waiting since ${DATA.waiting}.</p>
      <div class="mt-2.5 grid grid-cols-2 gap-2">
        <button class="h-9 rounded-lg bg-[#15803D] text-[13px] font-semibold text-white hover:brightness-95">Approve listing</button>
        <button class="h-9 rounded-lg border border-[#EF4444]/30 text-[13px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.06]">Reject listing</button>
      </div>
      ${el.dataset.note ? `<p class="mt-2.5 text-[12.5px] leading-5 text-muted">${el.dataset.note}</p>` : ''}
    </div>`),
  landlord: () => card(`
    <div class="px-4 py-2.5"><p class="text-[12px] text-muted">Landlord</p>
      <a href="#" class="block text-[13.5px] font-semibold leading-5 hover:underline">${DATA.landlord.name}</a>
      <p class="text-[12px] leading-4 text-muted">${DATA.landlord.email}</p></div>
    <div class="flex items-baseline justify-between border-t border-line px-4 py-2"><p class="text-[12px] text-muted">Reviews</p>
      <p class="text-[13px] font-semibold tabular-nums">${DATA.landlord.rating} <span class="font-normal text-muted">from ${DATA.landlord.reviews} reviews</span></p></div>`),
  reservations: () => card(`
    <div class="border-b border-line px-4 py-2.5"><h2 class="text-[14px] font-semibold">Reservations</h2></div>
    <p class="px-4 py-5 text-center text-[13px] font-medium text-muted">No reservations yet</p>`),
  units: (el) => el.dataset.flat ? `<table class="min-w-full"><thead><tr class="border-b border-line bg-canvas text-left text-[11px] font-semibold uppercase tracking-wide text-muted"><th class="px-3 py-2">Unit</th><th class="px-3 py-2 text-right">Rent</th><th class="px-3 py-2">Availability</th><th class="px-3 py-2"></th></tr></thead><tbody class="divide-y divide-line">${DATA.units.map(u => `<tr><td class="px-3 py-2 text-[13px] font-semibold">${u.label}</td><td class="px-3 py-2 text-right text-[13px] font-semibold tabular-nums">${u.rent}</td><td class="px-3 py-2">${chip(u.availability, 'green')}</td><td class="px-3 py-2 text-right"><a href="#" class="text-[13px] font-semibold hover:underline">View</a></td></tr>`).join('')}</tbody></table>` : card(`
    <div class="border-b border-line px-4 py-2.5"><h2 class="text-[14px] font-semibold">Units <span class="font-normal tabular-nums text-muted">${DATA.units.length}</span></h2></div>
    <table class="min-w-full"><thead><tr class="border-b border-line bg-canvas text-left text-[11px] font-semibold uppercase tracking-wide text-muted"><th class="px-4 py-2">Unit</th><th class="px-4 py-2 text-right">Rent</th><th class="px-4 py-2">Availability</th><th class="px-4 py-2"></th></tr></thead>
    <tbody class="divide-y divide-line">${DATA.units.map(u => `<tr class="hover:bg-canvas"><td class="px-4 py-2 text-[13px] font-semibold">${u.label}</td><td class="px-4 py-2 text-right text-[13px] font-semibold tabular-nums">${u.rent}</td><td class="px-4 py-2">${chip(u.availability, 'green')}</td><td class="px-4 py-2 text-right"><a href="#" class="text-[13px] font-semibold hover:underline">View</a></td></tr>`).join('')}</tbody></table>`, 'overflow-hidden'),
  documents: () => card(`
    <div class="flex items-center justify-between border-b border-line px-4 py-2"><h2 class="text-[14px] font-semibold">Documents</h2>
      <button class="h-8 rounded-lg border border-line bg-white px-3 text-[12.5px] font-semibold hover:bg-canvas">Request a document</button></div>
    <p class="px-4 py-5 text-center text-[13px] font-medium text-muted">No documents yet</p>`, 'overflow-hidden'),
  activity: (el) => card(`
    <div class="flex items-baseline justify-between border-b border-line px-4 py-2.5"><h2 class="text-[14px] font-semibold">Recent activity</h2><p class="text-[12px] tabular-nums text-muted">${DATA.activity.length} lines</p></div>
    <ul class="divide-y divide-line">${DATA.activity.map(([who, what, when]) => `<li class="flex items-center gap-2.5 px-4 py-1.5"><span class="inline-flex h-5 w-[3.75rem] shrink-0 items-center justify-center rounded text-[11px] font-semibold ${who === 'Landlord' ? 'bg-tint text-ink' : 'bg-coral/15 text-[#B35A3D]'}">${who}</span><span class="min-w-0 flex-1 text-[13px] leading-5">${what}</span><time class="shrink-0 whitespace-nowrap text-[12px] tabular-nums text-muted">${when}</time></li>`).join('')}</ul>`, 'overflow-hidden'),
  facts: (el) => `<dl class="grid grid-cols-2 gap-x-5 gap-y-2.5 ${el.dataset.cols || 'sm:grid-cols-5'}">${(el.dataset.items ? JSON.parse(el.dataset.items) : [['Type', DATA.type], ['City', DATA.city], ['Map location', DATA.lat.toFixed(7) + ', ' + DATA.lng.toFixed(7)], ['Created', DATA.created], ['Last updated', DATA.updated]]).map(([k, v]) => `<div class="min-w-0"><dt class="text-[12px] leading-4 text-muted">${k}</dt><dd class="text-[13.5px] font-medium leading-5">${v}</dd></div>`).join('')}</dl>`,
};

function mountShell() {
  const page = document.getElementById('page');
  const nav = [['Dashboard'], ['USER MANAGEMENT'], ['Users'], ['VERIFICATION & APPROVALS'], ['Landlord Verifications'], ['Property Verifications'], ['Property Documents'], ['Unit Approvals'], ['PROPERTY MANAGEMENT'], ['Properties', 1], ['Units'], ['RENTALS'], ['Reservations'], ['Payments'], ['Payouts'], ['Deposit Charges']];
  const shell = document.createElement('div');
  shell.className = 'flex min-h-screen';
  shell.innerHTML = `<aside class="sticky top-0 hidden h-screen w-56 shrink-0 flex-col bg-ink lg:flex">
      <div class="flex h-14 items-center gap-2.5 border-b border-white/[0.06] px-4"><div class="h-7 w-7 rounded-md bg-coral/90"></div><div><p class="text-[14px] font-extrabold leading-tight text-white">Abanganan<span class="text-coral">Hub</span></p><p class="text-[10px] font-semibold uppercase tracking-widest text-white/40">Admin Panel</p></div></div>
      <nav class="flex-1 space-y-0.5 overflow-hidden px-2.5 py-3">${nav.map(([t, on]) => t === t.toUpperCase() && t.length > 6 ? `<p class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-widest text-white/50">${t}</p>` : `<div class="flex h-9 items-center rounded-lg px-3 text-[13px] font-medium ${on ? 'bg-white/10 text-white' : 'text-white/60'}">${t}</div>`).join('')}</nav>
    </aside>
    <main class="min-w-0 flex-1 p-4 lg:px-5 lg:py-4"></main>`;
  document.body.prepend(shell);
  shell.querySelector('main').appendChild(page);
}

function mountSlots() {
  document.querySelectorAll('[data-slot]').forEach((el) => { el.innerHTML = SLOTS[el.dataset.slot](el); });
}

/* Real maps: Leaflet with OpenStreetMap tiles, or Esri satellite, with the app's own navy pin. */
function mountMaps() {
  document.querySelectorAll('.js-map').forEach((el) => {
    const map = L.map(el, { scrollWheelZoom: false, zoomControl: el.dataset.zoomcontrol !== 'off' }).setView([DATA.lat, DATA.lng], +(el.dataset.zoom || 16));
    const street = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', { attribution: 'Esri', maxZoom: 19 });
    const sat = L.layerGroup([
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' }),
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 }),
    ]);
    (el.dataset.sat === '1' ? sat : street).addTo(map);
    L.marker([DATA.lat, DATA.lng], { icon: L.divIcon({ className: '', iconSize: [34, 34], iconAnchor: [17, 17], html: '<div style="width:34px;height:34px;border-radius:50%;background:#060D26;border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center"><div style="width:10px;height:10px;border-radius:50%;background:#fff"></div></div>' }) }).addTo(map);
    L.circle([DATA.lat, DATA.lng], { radius: 150, color: '#FF8A66', weight: 1.5, fillColor: '#FF8A66', fillOpacity: 0.12 }).addTo(map);
    el._map = map; el._street = street; el._sat = sat;
    el.addEventListener('click', () => map.scrollWheelZoom.enable(), { once: true });
  });
  document.querySelectorAll('[data-sat-toggle]').forEach((btn) => btn.addEventListener('click', () => {
    const el = document.getElementById(btn.dataset.satToggle); const on = btn.dataset.on !== '1';
    btn.dataset.on = on ? '1' : '0'; btn.querySelector('span').textContent = on ? 'Map' : 'Satellite';
    el._map[on ? 'removeLayer' : 'addLayer'](el._street); el._map[on ? 'addLayer' : 'removeLayer'](el._sat);
  }));
}

function mountLightbox() {
  const box = document.createElement('div');
  box.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-6'; box.innerHTML = '<img class="max-h-full max-w-full rounded-lg" alt="">';
  box.addEventListener('click', () => { box.classList.add('hidden'); box.classList.remove('flex'); }); document.body.appendChild(box);
  document.querySelectorAll('[data-photo]').forEach((b) => b.addEventListener('click', (e) => { e.preventDefault(); box.querySelector('img').src = b.dataset.photo; box.classList.remove('hidden'); box.classList.add('flex'); }));
}

document.addEventListener('DOMContentLoaded', () => { mountShell(); mountSlots(); mountMaps(); mountLightbox(); window.dispatchEvent(new Event('mock-ready')); });
