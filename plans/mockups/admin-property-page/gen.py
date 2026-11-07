HEAD='''<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script src="common.js"></script>
<link rel="stylesheet" href="common.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
</head><body>
<div id="page" class="mx-auto max-w-[1600px]">
'''
TAIL='''</div></body></html>
'''
def page(name,title,body):
    open(name,'w').write(HEAD.replace('{title}',title)+body+TAIL)

BACK='<a href="#" class="mb-2 inline-flex items-center gap-1.5 text-[13px] font-semibold text-muted hover:text-ink"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Back</a>'
TITLE='<div class="mb-3 flex flex-wrap items-center gap-x-2.5 gap-y-1"><h1 class="text-[20px] font-semibold leading-7 tracking-tight">Two-Bedroom House near SRP Talisay</h1><span class="inline-flex h-[22px] items-center rounded-md bg-[#FBBF24]/[0.16] px-1.5 text-[12px] font-semibold text-[#B45309]">Pending</span></div>'
SUB='<p class="-mt-2.5 mb-3 text-[13px] text-muted">Bulacao, Talisay City, Cebu</p>'
PH=["photo-1568605114967-8130f3a36994","photo-1570129477492-45c003edd2be","photo-1512917774080-9991f1c4c750"]
def P(i): return 'https://images.unsplash.com/'+PH[i]+'?w=1200&q=80'
EXT='<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5-7.5H21m0 0v7.5M21 3l-9 9"/></svg>'
OPEN='<a href="https://www.google.com/maps?q=10.2571,123.8479" target="_blank" rel="noopener" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-white px-2.5 text-[12.5px] font-semibold shadow-sm hover:bg-canvas">Open in Google Maps '+EXT+'</a>'
def SAT(i,on=False):
    return '<button data-sat-toggle="'+i+'"'+(' data-on="1"' if on else '')+' class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-white px-2.5 text-[12.5px] font-semibold shadow-sm hover:bg-canvas"><span>'+('Map' if on else 'Satellite')+'</span></button>'
def ph(i,cls):
    return '<button data-photo="'+P(i)+'" class="group relative block overflow-hidden bg-tint '+cls+'" aria-label="Open photo '+str(i+1)+' full size"><img src="'+P(i)+'" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"></button>'
SIDE='<div class="space-y-3 lg:sticky lg:top-4"><div data-slot="decision"></div><div data-slot="landlord"></div><div data-slot="reservations"></div></div>'
DESC='Two-bedroom house with parking and a washing machine, twenty minutes from the SRP. Quiet street, pets welcome.'
LOWER='<div data-slot="units"></div><div class="grid items-start gap-3 xl:grid-cols-2"><div data-slot="documents"></div><div data-slot="activity"></div></div>'

# A
page('a-review-desk.html','A · Review desk',BACK+TITLE+SUB+'''
  <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
    <div class="min-w-0 space-y-3">
      <div class="grid gap-3 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        <div class="grid h-[22rem] grid-cols-4 grid-rows-2 gap-1.5">'''+ph(0,'col-span-3 row-span-2 rounded-xl')+ph(1,'rounded-xl')+ph(2,'rounded-xl')+'''</div>
        <div class="relative h-[22rem] overflow-hidden rounded-xl border border-line">
          <div id="mapA" class="js-map h-full w-full" data-zoom="16"></div>
          <div class="absolute right-2.5 top-2.5 z-[500] flex gap-1.5">'''+SAT('mapA')+'''</div>
          <div class="absolute bottom-2.5 left-2.5 right-2.5 z-[500] flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 shadow-md">
            <div class="min-w-0"><p class="truncate text-[13px] font-semibold">Bulacao, Talisay City, Cebu</p><p class="truncate text-[12px] tabular-nums text-muted">10.2571, 123.8479 · pinned by landlord</p></div>
            <a href="https://www.google.com/maps?q=10.2571,123.8479" target="_blank" rel="noopener" class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-line px-2.5 text-[12.5px] font-semibold hover:bg-canvas">Open map '''+EXT+'''</a>
          </div>
        </div>
      </div>
      <div class="rounded-xl border border-line bg-white p-4 shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
        <div data-slot="facts" data-cols="sm:grid-cols-4" data-items='[["Type","House"],["Area","Bulacao, Talisay City"],["Created","Oct 31, 2026"],["Last updated","Oct 31, 2026"]]'></div>
        <p class="mt-2.5 max-w-[75ch] text-[13px] leading-5 text-muted">'''+DESC+'''</p>
      </div>
      '''+LOWER+'''
    </div>
    '''+SIDE+'''
  </div>
''')

# B
stats=''.join('<div class="bg-white px-4 py-2.5"><p class="text-[12px] text-muted">'+k+'</p><p class="text-[16px] font-semibold tabular-nums leading-6">'+v+'</p></div>' for k,v in [('Type','House'),('Units','1'),('Rent','₱8,000'),('Photos','3'),('Documents','0/0')])
page('b-map-first.html','B · Map first',BACK+TITLE+SUB+'''
  <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
    <div class="min-w-0 space-y-3">
      <div class="relative h-[24rem] overflow-hidden rounded-xl border border-line">
        <div id="mapB" class="js-map h-full w-full" data-zoom="16" data-sat="1"></div>
        <div class="absolute right-2.5 top-2.5 z-[500] flex gap-1.5">'''+SAT('mapB',True)+OPEN+'''</div>
        <div class="absolute inset-x-2.5 bottom-2.5 z-[500] flex items-end justify-between gap-3">
          <div class="flex gap-1.5 rounded-xl bg-white p-1.5 shadow-lg">'''+ph(0,'h-[4.5rem] w-28 rounded-lg')+ph(1,'h-[4.5rem] w-28 rounded-lg')+ph(2,'h-[4.5rem] w-28 rounded-lg')+'''</div>
          <div class="hidden rounded-xl bg-white px-3 py-2 shadow-lg sm:block"><p class="text-[13px] font-semibold">Bulacao, Talisay City</p><p class="text-[12px] tabular-nums text-muted">10.2571, 123.8479</p></div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-5">'''+stats+'''</div>
      <p class="max-w-[75ch] text-[13px] leading-5 text-muted">'''+DESC+''' Listed Oct 31, 2026.</p>
      '''+LOWER+'''
    </div>
    '''+SIDE+'''
  </div>
''')

# C
tabs=[('overview','Overview',''),('units','Units','1'),('documents','Documents','0/0'),('activity','Activity','5'),('reservations','Reservations','0')]
TABS=''
for k,l,n in tabs:
    on = k=='overview'
    cnt = '<span class="text-[12px] tabular-nums text-muted">'+n+'</span>' if n else ''
    TABS+='<button data-tab="'+k+'" class="-mb-px inline-flex h-10 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-[13px] '+('border-coral font-semibold text-ink' if on else 'border-transparent font-medium text-muted hover:text-ink')+'">'+l+cnt+'</button>'
page('c-tabbed.html','C · Tabbed workspace',BACK+TITLE+SUB+'''
  <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
    <div class="min-w-0">
      <nav class="mb-3 flex gap-0.5 overflow-x-auto border-b border-line">'''+TABS+'''</nav>
      <section data-pane="overview" class="space-y-3">
        <div class="grid gap-3 xl:grid-cols-2">
          <div class="grid h-[20rem] grid-cols-2 grid-rows-2 gap-1.5">'''+ph(0,'col-span-2 rounded-xl')+ph(1,'rounded-xl')+ph(2,'rounded-xl')+'''</div>
          <div class="relative h-[20rem] overflow-hidden rounded-xl border border-line"><div id="mapC" class="js-map h-full w-full" data-zoom="16"></div><div class="absolute left-2.5 top-2.5 z-[500] flex gap-1.5">'''+SAT('mapC')+OPEN+'''</div></div>
        </div>
        <div class="rounded-xl border border-line bg-white p-4 shadow-[0_1px_3px_rgba(6,13,38,0.06)]"><div data-slot="facts"></div>
          <p class="mt-2.5 max-w-[75ch] text-[13px] leading-5 text-muted">'''+DESC+'''</p></div>
      </section>
      <section data-pane="units" class="hidden"><div data-slot="units"></div></section>
      <section data-pane="documents" class="hidden"><div data-slot="documents"></div></section>
      <section data-pane="activity" class="hidden"><div data-slot="activity"></div></section>
      <section data-pane="reservations" class="hidden"><div data-slot="reservations"></div></section>
    </div>
    <div class="space-y-3 lg:sticky lg:top-4"><div data-slot="decision"></div><div data-slot="landlord"></div></div>
  </div>
  <script>
  window.addEventListener('mock-ready',function(){var tabs=[].slice.call(document.querySelectorAll('[data-tab]'));tabs.forEach(function(t){t.addEventListener('click',function(){tabs.forEach(function(o){var on=o===t;o.classList.toggle('border-coral',on);o.classList.toggle('font-semibold',on);o.classList.toggle('text-ink',on);o.classList.toggle('border-transparent',!on);o.classList.toggle('text-muted',!on);o.classList.toggle('font-medium',!on);});document.querySelectorAll('[data-pane]').forEach(function(p){p.classList.toggle('hidden',p.dataset.pane!==t.dataset.tab);});document.querySelectorAll('.js-map').forEach(function(m){m._map.invalidateSize();});});});});
  </script>
''')

# D
OKI='<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>'
WARNI='<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v6m0 3.5h.01"/></svg>'
def step(ok,title,sub,body='',action=''):
    mark='<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full '+('bg-[#22C55E]/[0.14] text-[#15803D]' if ok else 'bg-[#FBBF24]/[0.2] text-[#B45309]')+'">'+(OKI if ok else WARNI)+'</span>'
    b='<div class="border-t border-line p-3">'+body+'</div>' if body else ''
    return '<section class="rounded-xl border border-line bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]"><header class="flex items-center gap-3 px-4 py-2.5">'+mark+'<div class="min-w-0 flex-1"><h2 class="text-[14px] font-semibold leading-5">'+title+'</h2><p class="text-[12.5px] leading-4 text-muted">'+sub+'</p></div>'+action+'</header>'+b+'</section>'
photos3='<div class="grid grid-cols-3 gap-1.5">'+ph(0,'aspect-[4/3] rounded-lg')+ph(1,'aspect-[4/3] rounded-lg')+ph(2,'aspect-[4/3] rounded-lg')+'</div>'
mapD='<div class="relative h-60 overflow-hidden rounded-lg border border-line"><div id="mapD" class="js-map h-full w-full" data-zoom="16"></div><div class="absolute left-2 top-2 z-[500] flex gap-1.5">'+SAT('mapD')+OPEN+'</div></div><p class="mt-2 text-[12.5px] tabular-nums text-muted">Bulacao, Talisay City, Cebu · 10.2571, 123.8479</p>'
seg=lambda c:'<span class="flex-1 rounded-full bg-['+c+']"></span>'
page('d-checklist.html','D · Approval checklist',BACK+TITLE+SUB+'''
  <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
    <div class="min-w-0 space-y-3">
      '''+step(True,'Photos','3 photos, the first is the cover',photos3)+'''
      '''+step(True,'Location','Pinned by the landlord. Check that the pin sits on the street in the address.',mapD)+'''
      '''+step(True,'Units','1 unit, ₱8,000 a month','<div data-slot="units" data-flat="1" class="overflow-hidden rounded-lg border border-line"></div>')+'''
      '''+step(False,'Documents','0 of 0 verified. Nothing has been uploaded or requested yet.','','<button class="h-8 rounded-lg border border-line bg-white px-3 text-[12.5px] font-semibold hover:bg-canvas">Request a document</button>')+'''
      '''+step(True,'Landlord','Lourdes Pepito, verified landlord · 4.3 from 3 reviews','','<a href="#" class="text-[13px] font-semibold hover:underline">Open profile</a>')+'''
      <div data-slot="activity"></div>
    </div>
    <div class="space-y-3 lg:sticky lg:top-4">
      <div class="rounded-xl border border-line bg-white p-4 shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
        <h2 class="text-[14px] font-semibold">Ready to approve?</h2>
        <div class="mt-2 flex h-1.5 gap-0.5">'''+seg('#22C55E')*3+seg('#FBBF24')+seg('#22C55E')+'''</div>
        <p class="mt-1.5 text-[12.5px] leading-5 text-muted"><span class="font-semibold text-ink">4 of 5 checks pass.</span> Documents are missing. You can still approve, or request one first.</p>
        <div class="mt-3 grid grid-cols-2 gap-2"><button class="h-9 rounded-lg bg-[#15803D] text-[13px] font-semibold text-white hover:brightness-95">Approve listing</button><button class="h-9 rounded-lg border border-[#EF4444]/30 text-[13px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.06]">Reject listing</button></div>
        <p class="mt-2 text-[12px] text-muted">Waiting since Oct 31, 2026.</p>
      </div>
      <div data-slot="landlord"></div><div data-slot="reservations"></div>
    </div>
  </div>
''')

# E
def tile(label,big,sub): return '<div class="rounded-xl border border-line bg-white p-4"><p class="text-[12px] text-muted">'+label+'</p>'+big+sub+'</div>'
page('e-bento.html','E · Bento with action bar',BACK+TITLE+SUB+'''
  <div class="space-y-3 pb-16">
    <div class="grid gap-3 xl:grid-cols-12">
      <div class="grid h-[22rem] grid-cols-4 grid-rows-2 gap-1.5 xl:col-span-7">'''+ph(0,'col-span-2 row-span-2 rounded-xl')+ph(1,'col-span-2 rounded-xl')+ph(2,'col-span-2 rounded-xl')+'''</div>
      <div class="relative h-[22rem] overflow-hidden rounded-xl border border-line xl:col-span-5">
        <div id="mapE" class="js-map h-full w-full" data-zoom="15" data-sat="1"></div>
        <div class="absolute left-2.5 top-2.5 z-[500] flex gap-1.5">'''+SAT('mapE',True)+OPEN+'''</div>
        <div class="absolute bottom-2.5 left-2.5 z-[500] rounded-lg bg-white px-3 py-1.5 shadow-md"><p class="text-[13px] font-semibold">Bulacao, Talisay City</p><p class="text-[12px] tabular-nums text-muted">10.2571, 123.8479</p></div>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
      '''+tile('Landlord','<p class="text-[14px] font-semibold leading-5">Lourdes Pepito</p>','<p class="truncate text-[12px] text-muted">lourdes.pepito@abangananhub.com</p>')+'''
      '''+tile('Rent','<p class="text-[20px] font-semibold leading-6 tabular-nums">₱8,000</p>','<p class="text-[12px] text-muted">a month · 1 unit</p>')+'''
      '''+tile('Units','<div class="mt-1.5 flex h-2 overflow-hidden rounded-full"><span class="w-full bg-[#22C55E]"></span></div>','<p class="mt-1.5 text-[12px] tabular-nums text-muted"><span class="font-semibold text-ink">1 free</span> · 0 held · 0 taken</p>')+'''
      '''+tile('Reviews','<p class="text-[20px] font-semibold leading-6 tabular-nums">4.3 <span class="text-[12px] font-normal text-muted">from 3</span></p>','<p class="text-[12px] text-muted">No reservations yet</p>')+'''
    </div>
    <div class="grid items-start gap-3 xl:grid-cols-12">
      <div class="space-y-3 xl:col-span-7"><div data-slot="units"></div><div data-slot="documents"></div></div>
      <div class="xl:col-span-5"><div data-slot="activity"></div></div>
    </div>
  </div>
  <div class="sticky bottom-3 z-40 mx-auto flex max-w-[52rem] flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-white px-4 py-2.5 shadow-[0_8px_24px_rgba(6,13,38,0.14)]">
    <p class="text-[13px]"><span class="font-semibold">Pending approval</span> <span class="text-muted">· waiting since Oct 31, 2026 · documents 0/0</span></p>
    <div class="flex gap-2"><button class="h-9 rounded-lg border border-[#EF4444]/30 px-4 text-[13px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.06]">Reject listing</button><button class="h-9 rounded-lg bg-[#15803D] px-4 text-[13px] font-semibold text-white hover:brightness-95">Approve listing</button></div>
  </div>
''')

items=[('a-review-desk','A · Review desk','Photos and the map side by side at equal height. Facts follow, then units, documents and activity. Decision rail on the right.'),
('b-map-first','B · Map first','A large satellite map is the hero, with the photo filmstrip on its bottom edge. Best when the location is what you verify.'),
('c-tabbed','C · Tabbed workspace','Overview holds photos, map and facts. Units, documents, activity and reservations each get a tab with a count. Shortest page.'),
('d-checklist','D · Approval checklist','Each thing an approval depends on is a section with a pass or warn mark, with a readiness summary beside the buttons. Best for pending listings.'),
('e-bento','E · Bento with action bar','Photo and map mosaic, a row of small fact tiles, and the decision pinned to the bottom edge instead of a rail.')]
cards=''.join('<a href="'+f+'.html" class="group block overflow-hidden rounded-xl border border-[#E2E4EC] bg-white shadow-sm transition hover:shadow-md"><div class="h-56 overflow-hidden border-b border-[#E2E4EC] bg-[#F7F8FC]"><iframe src="'+f+'.html" tabindex="-1" class="pointer-events-none h-[1000px] w-[1600px] origin-top-left border-0" style="transform:scale(.33)"></iframe></div><div class="p-4"><p class="text-[15px] font-semibold group-hover:underline">'+t+'</p><p class="mt-1 text-[13px] leading-5 text-[#5B6A8E]">'+d+'</p></div></a>' for f,t,d in items)
open('index.html','w').write('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin property page · 5 mockups</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><script src="https://cdn.tailwindcss.com/3.4.16"></script><link rel="stylesheet" href="common.css"></head><body class="p-6"><div class="mx-auto max-w-[1100px]"><h1 class="text-[22px] font-semibold tracking-tight">Admin property page: five directions</h1><p class="mt-1 max-w-[70ch] text-[14px] leading-6 text-[#5B6A8E]">The same sample listing in all five (Two-Bedroom House near SRP Talisay, set to Pending), with a real map and the listing\'s photos. Open one to scroll and click: photos open full size, the map pans and has a Satellite toggle.</p><div class="mt-5 grid gap-4 sm:grid-cols-2">'+cards+'</div></div></body></html>')
print('ok')
