
<x-app-layout>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.abg { font-family: 'Inter', -apple-system, sans-serif; background: #F7F8FA; color: #1A1A2E; min-height: 100vh; }

/* ════════════════════════════════
   TOP NAV — Airbnb-style full-width
   ════════════════════════════════ */
.abg-nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0,0,0,0.08);
    padding: 0 40px;
    height: 80px;
    display: flex; align-items: center; justify-content: space-between; gap: 24px;
    box-shadow: 0 1px 12px rgba(0,0,0,0.06);
}

/* Logo */
.abg-logo { display: flex; align-items: center; gap: 9px; text-decoration: none; flex-shrink: 0; }
.abg-logo-icon {
    width: 38px; height: 38px; border-radius: 11px;
    background: linear-gradient(135deg, #286CD2, #61B2F0);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(40,108,210,0.35);
}
.abg-logo-text { font-size: 16px; font-weight: 800; color: #1A1A2E; letter-spacing: -0.4px; }
.abg-logo-text span { color: #286CD2; }

/* Center search pill — signature Airbnb element */
.abg-search-pill {
    flex: 1; max-width: 520px;
    display: flex; align-items: center;
    border: 1.5px solid #E0E4EB;
    border-radius: 40px;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.07);
    overflow: hidden;
    transition: box-shadow 0.2s;
    cursor: pointer;
}
.abg-search-pill:focus-within {
    box-shadow: 0 4px 20px rgba(40,108,210,0.18);
    border-color: #286CD2;
}
.abg-search-seg {
    flex: 1; padding: 10px 18px;
    display: flex; flex-direction: column; gap: 1px;
    border-right: 1px solid #E8ECF0;
    cursor: pointer;
}
.abg-search-seg:last-of-type { border-right: none; }
.abg-search-label { font-size: 10px; font-weight: 700; color: #1A1A2E; letter-spacing: 0.2px; }
.abg-search-input {
    border: none; outline: none; background: transparent;
    font-size: 12.5px; color: #6B7280; font-family: inherit; font-weight: 400;
    width: 100%; cursor: pointer;
}
.abg-search-input::placeholder { color: #B0B8C4; }
.abg-search-btn {
    width: 40px; height: 40px; margin: 4px;
    border-radius: 50%; border: none; cursor: pointer;
    background: linear-gradient(135deg, #286CD2, #61B2F0);
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(40,108,210,0.4);
    transition: transform 0.15s, box-shadow 0.15s;
}
.abg-search-btn:hover { transform: scale(1.07); box-shadow: 0 4px 14px rgba(40,108,210,0.5); }

/* Nav right */
.abg-nav-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.abg-btn-host {
    height: 40px; padding: 0 18px;
    border: 1.5px solid #E0E4EB; border-radius: 24px;
    background: #fff; font-size: 13px; font-weight: 700;
    color: #1A1A2E; text-decoration: none;
    display: inline-flex; align-items: center; gap: 7px;
    transition: all 0.18s; white-space: nowrap;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.abg-btn-host:hover {
    border-color: #286CD2; color: #286CD2;
    box-shadow: 0 2px 10px rgba(40,108,210,0.15);
    background: #F0F6FF;
}
.abg-notif {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #E0E4EB; background: #fff;
    display: flex; align-items: center; justify-content: center;
    color: #5A6475; text-decoration: none; position: relative;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    transition: all 0.18s;
}
.abg-notif:hover { border-color: #286CD2; color: #286CD2; }
.abg-notif-dot {
    position: absolute; top: 7px; right: 7px;
    width: 8px; height: 8px; border-radius: 50%;
    background: #BD5434; border: 2px solid #fff;
}
.abg-avatar-wrap { position: relative; }
.abg-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #1e4fa3, #286CD2);
    color: #fff; font-size: 15px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: 2.5px solid #fff;
    box-shadow: 0 0 0 1.5px #D7E8F3, 0 2px 8px rgba(40,108,210,0.25);
    transition: box-shadow 0.18s;
}
.abg-avatar:hover { box-shadow: 0 0 0 2px #286CD2, 0 4px 14px rgba(40,108,210,0.3); }
.abg-dropdown {
    position: absolute; top: calc(100% + 12px); right: 0;
    background: #fff; border: 1px solid #E8ECF0; border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.13); min-width: 210px;
    padding: 8px; z-index: 200; display: none;
    animation: dropIn 0.15s ease;
}
@keyframes dropIn { from { opacity:0; transform: translateY(-6px); } to { opacity:1; transform: none; } }
.abg-dropdown.open { display: block; }
.abg-dd-head { padding: 10px 12px 12px; border-bottom: 1px solid #F0F2F5; margin-bottom: 6px; }
.abg-dd-name { font-size: 13.5px; font-weight: 700; color: #1A1A2E; }
.abg-dd-email { font-size: 11.5px; color: #9AA0AB; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.abg-dd-link, .abg-dd-btn {
    display: flex; align-items: center; gap: 9px;
    width: 100%; padding: 9px 12px; border-radius: 10px;
    font-size: 13px; font-weight: 500; color: #374151;
    text-decoration: none; background: transparent;
    border: none; cursor: pointer; font-family: inherit;
    transition: background 0.12s; text-align: left;
}
.abg-dd-link:hover, .abg-dd-btn:hover { background: #F7F8FA; }
.abg-dd-btn.danger { color: #DC2626; margin-top: 4px; border-top: 1px solid #F0F2F5; padding-top: 12px; }
.abg-dd-btn.danger:hover { background: #FEF2F2; }

/* ════════════════════════════════
   CATEGORY STRIP — Airbnb-style
   ════════════════════════════════ */
.abg-strip {
    background: #fff; border-bottom: 1px solid rgba(0,0,0,0.07);
    padding: 0 40px; display: flex; align-items: center;
    gap: 0; overflow-x: auto; scrollbar-width: none;
}
.abg-strip::-webkit-scrollbar { display: none; }
.abg-strip-item {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 14px 20px; cursor: pointer; flex-shrink: 0;
    border-bottom: 2.5px solid transparent;
    color: #9AA0AB; transition: color 0.18s, border-color 0.18s;
    text-decoration: none;
}
.abg-strip-item:hover { color: #1A1A2E; border-bottom-color: #E0E4EB; }
.abg-strip-item.active { color: #1A1A2E; border-bottom-color: #1A1A2E; }
.abg-strip-item svg { flex-shrink: 0; }
.abg-strip-label { font-size: 11px; font-weight: 600; white-space: nowrap; }

/* ════════════════════════════════
   MAIN CONTENT
   ════════════════════════════════ */
.abg-body { max-width: 1280px; margin: 0 auto; padding: 40px 40px 60px; }

/* WELCOME HERO — full-width gradient */
.abg-hero {
    border-radius: 24px; margin-bottom: 40px;
    background: linear-gradient(120deg, #1A3A6E 0%, #286CD2 55%, #61B2F0 100%);
    padding: 40px 48px;
    display: flex; align-items: center; justify-content: space-between; gap: 28px;
    position: relative; overflow: hidden;
    box-shadow: 0 8px 32px rgba(40,108,210,0.28);
}
.abg-hero::before {
    content: ''; position: absolute; right: -80px; top: -80px;
    width: 320px; height: 320px; border-radius: 50%;
    background: rgba(255,255,255,0.06); pointer-events: none;
}
.abg-hero::after {
    content: ''; position: absolute; right: 120px; bottom: -100px;
    width: 240px; height: 240px; border-radius: 50%;
    background: rgba(255,255,255,0.04); pointer-events: none;
}
.abg-hero-text { position: relative; z-index: 1; }
.abg-hero-eyebrow {
    display: inline-block; font-size: 10.5px; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(255,255,255,0.65); margin-bottom: 10px;
    background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px;
}
.abg-hero h1 {
    font-size: 28px; font-weight: 900; color: #fff;
    line-height: 1.2; letter-spacing: -0.6px; margin-bottom: 8px;
}
.abg-hero p { font-size: 14px; color: rgba(255,255,255,0.72); line-height: 1.55; max-width: 420px; }
.abg-hero-actions { display: flex; gap: 10px; flex-shrink: 0; position: relative; z-index: 1; flex-direction: column; align-items: flex-end; }
.abg-btn-white {
    height: 44px; padding: 0 24px;
    background: #fff; color: #286CD2; border-radius: 12px;
    font-size: 14px; font-weight: 800; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px;
    border: none; cursor: pointer; white-space: nowrap;
    box-shadow: 0 2px 12px rgba(0,0,0,0.12);
    transition: transform 0.15s, box-shadow 0.15s;
}
.abg-btn-white:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,0.16); }
.abg-btn-outline-white {
    height: 44px; padding: 0 24px;
    background: rgba(255,255,255,0.12); color: #fff; border-radius: 12px;
    font-size: 14px; font-weight: 700; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px;
    border: 1.5px solid rgba(255,255,255,0.3); cursor: pointer; white-space: nowrap;
    backdrop-filter: blur(4px);
    transition: background 0.15s, border-color 0.15s;
}
.abg-btn-outline-white:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.5); }

/* STATS ROW */
.abg-stats {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 40px;
}
.abg-stat {
    background: #fff; border-radius: 18px;
    border: 1px solid #EAECF0; padding: 22px 22px 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s, transform 0.18s;
    position: relative; overflow: hidden;
}
.abg-stat:hover { box-shadow: 0 6px 24px rgba(40,108,210,0.1); transform: translateY(-2px); }
.abg-stat-glow {
    position: absolute; right: -20px; top: -20px;
    width: 90px; height: 90px; border-radius: 50%; opacity: 0.08;
}
.abg-stat-icon {
    width: 38px; height: 38px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 12px;
}
.abg-stat-num {
    font-size: 32px; font-weight: 900; line-height: 1;
    letter-spacing: -1.5px; color: #1A1A2E; margin-bottom: 4px;
}
.abg-stat-label { font-size: 11px; font-weight: 700; color: #9AA0AB; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.abg-stat-sub { font-size: 11.5px; color: #C0C7D0; font-weight: 400; }

/* SECTION TITLE ROW */
.abg-section-row {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 20px;
}
.abg-section-title { font-size: 19px; font-weight: 800; color: #1A1A2E; letter-spacing: -0.3px; }
.abg-section-sub { font-size: 13px; color: #9AA0AB; margin-top: 2px; }
.abg-see-all {
    font-size: 13px; font-weight: 700; color: #286CD2;
    text-decoration: none; padding: 6px 14px;
    border: 1.5px solid #D7E8F3; border-radius: 10px;
    transition: background 0.15s;
}
.abg-see-all:hover { background: #EEF5FF; }

/* RESERVATION FILTER BAR */
.abg-filter-bar {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 18px; flex-wrap: wrap;
}
.abg-filter-search { flex: 1; min-width: 180px; position: relative; }
.abg-filter-search input {
    width: 100%; height: 40px;
    border: 1.5px solid #E8ECF0; border-radius: 12px;
    padding: 0 14px 0 38px; font-size: 13px; font-family: inherit;
    color: #1A1A2E; background: #F7F8FA; outline: none;
    transition: border-color 0.15s, background 0.15s;
}
.abg-filter-search input:focus { border-color: #286CD2; background: #fff; }
.abg-filter-search input::placeholder { color: #B0B8C4; }
.abg-filter-search .fs-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #B0B8C4; }
.abg-filter-select {
    height: 40px; padding: 0 14px; border: 1.5px solid #E8ECF0;
    border-radius: 12px; font-size: 13px; font-family: inherit;
    color: #374151; background: #F7F8FA; outline: none; cursor: pointer;
    font-weight: 600; transition: border-color 0.15s;
}
.abg-filter-select:focus { border-color: #286CD2; }

/* RESERVATION CARDS — Airbnb-style horizontal card */
.abg-resv-list { display: flex; flex-direction: column; gap: 12px; }
.abg-resv-card {
    display: flex; align-items: center; gap: 16px;
    background: #fff; border: 1px solid #EAECF0;
    border-radius: 16px; padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: box-shadow 0.18s, border-color 0.18s, transform 0.15s;
}
.abg-resv-card:hover {
    box-shadow: 0 6px 24px rgba(40,108,210,0.09);
    border-color: #D7E8F3; transform: translateY(-1px);
}
.abg-resv-thumb {
    width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, #286CD2 0%, #61B2F0 100%);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px; font-weight: 900;
    box-shadow: 0 4px 14px rgba(40,108,210,0.3);
}
.abg-resv-info { flex: 1; min-width: 0; }
.abg-resv-title { font-size: 14.5px; font-weight: 700; color: #1A1A2E; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.abg-resv-meta { font-size: 12px; color: #9AA0AB; font-weight: 500; }
.abg-resv-meta span { color: #5A6475; font-weight: 600; }
.abg-resv-right { text-align: right; flex-shrink: 0; }
.abg-pill {
    display: inline-block; font-size: 11px; font-weight: 700;
    letter-spacing: 0.3px; text-transform: uppercase;
    padding: 4px 12px; border-radius: 20px; margin-bottom: 6px;
}
.abg-pill-approved { background: #ECFDF5; color: #065F46; }
.abg-pill-pending  { background: #FFFBEB; color: #92400E; }
.abg-pill-rejected { background: #FEF2F2; color: #991B1B; }
.abg-pill-cancelled { background: #F4F4F4; color: #6B7280; }
.abg-resv-ref { font-size: 11px; color: #C0C7D0; font-weight: 700; letter-spacing: 0.2px; }

/* EMPTY STATE */
.abg-empty {
    text-align: center; padding: 52px 24px;
    border: 2px dashed #E8ECF0; border-radius: 18px;
    background: linear-gradient(160deg, #F9FBFF 0%, #F0F6FF 100%);
}
.abg-empty-icon {
    width: 72px; height: 72px; border-radius: 20px;
    background: linear-gradient(135deg, #EEF5FF, #D7E8F3);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; color: #286CD2;
    box-shadow: 0 4px 16px rgba(40,108,210,0.12);
}
.abg-empty-title { font-size: 15px; font-weight: 800; color: #1A1A2E; margin-bottom: 6px; }
.abg-empty-sub { font-size: 13px; color: #9AA0AB; margin-bottom: 22px; line-height: 1.5; }
.abg-btn-primary {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 22px; background: linear-gradient(135deg, #286CD2, #61B2F0);
    color: #fff; border-radius: 12px; font-size: 13.5px; font-weight: 700;
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 4px 16px rgba(40,108,210,0.3);
    transition: opacity 0.15s, transform 0.15s;
}
.abg-btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

/* TWO-COL GRID */
.abg-two-col { display: grid; grid-template-columns: 1fr 340px; gap: 24px; margin-top: 40px; }

/* BECOME HOST CARD */
.abg-host-card {
    border-radius: 22px; padding: 28px 26px;
    background: linear-gradient(145deg, #1A2A40 0%, #2C3E55 60%, #5E6968 100%);
    position: relative; overflow: hidden;
    box-shadow: 0 8px 30px rgba(26,42,64,0.35);
}
.abg-host-card::before {
    content: ''; position: absolute;
    right: -30px; top: -30px; width: 140px; height: 140px;
    border-radius: 50%; background: rgba(97,178,240,0.12);
}
.abg-host-card::after {
    content: ''; position: absolute;
    left: -20px; bottom: -30px; width: 110px; height: 110px;
    border-radius: 50%; background: rgba(255,255,255,0.04);
}
.abg-host-badge {
    display: inline-block; font-size: 10px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    color: #61B2F0; background: rgba(97,178,240,0.15);
    padding: 4px 10px; border-radius: 20px; margin-bottom: 12px;
}
.abg-host-title { font-size: 18px; font-weight: 900; color: #fff; margin-bottom: 8px; line-height: 1.3; letter-spacing: -0.3px; position: relative; z-index: 1; }
.abg-host-sub { font-size: 12.5px; color: rgba(255,255,255,0.58); line-height: 1.6; margin-bottom: 20px; position: relative; z-index: 1; }
.abg-host-perks { display: flex; flex-direction: column; gap: 8px; margin-bottom: 22px; position: relative; z-index: 1; }
.abg-host-perk { display: flex; align-items: center; gap: 9px; font-size: 12px; color: rgba(255,255,255,0.75); font-weight: 500; }
.abg-host-perk-dot { width: 6px; height: 6px; border-radius: 50%; background: #61B2F0; flex-shrink: 0; }
.abg-btn-host-card {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 22px; background: #fff; color: #1A2A40;
    border-radius: 12px; font-size: 13.5px; font-weight: 800;
    text-decoration: none; border: none; cursor: pointer;
    font-family: inherit; position: relative; z-index: 1;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
    transition: opacity 0.15s, transform 0.15s;
}
.abg-btn-host-card:hover { opacity: 0.92; transform: translateY(-1px); }

/* QUICK ACTIONS */
.abg-qa-card {
    background: #fff; border: 1px solid #EAECF0; border-radius: 18px;
    padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 18px;
}
.abg-qa-title { font-size: 14px; font-weight: 800; color: #1A1A2E; margin-bottom: 4px; }
.abg-qa-sub { font-size: 11.5px; color: #9AA0AB; margin-bottom: 16px; }
.abg-qa-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; border-radius: 12px; border: 1px solid #F0F2F5;
    text-decoration: none; color: #374151; font-size: 13.5px; font-weight: 600;
    margin-bottom: 8px; background: #FAFBFC;
    transition: all 0.15s;
}
.abg-qa-item:last-child { margin-bottom: 0; }
.abg-qa-item:hover {
    border-color: #D7E8F3; background: #F0F6FF;
    color: #286CD2; transform: translateX(2px);
}
.abg-qa-item:hover .abg-qa-icon { background: #286CD2; color: #fff; }
.abg-qa-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: #F0F2F5; display: flex; align-items: center;
    justify-content: center; color: #6B7280; flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}
.abg-qa-arrow { margin-left: auto; color: #D0D5DD; flex-shrink: 0; }

/* ACTIVITY FEED */
.abg-activity { background: #fff; border: 1px solid #EAECF0; border-radius: 18px; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.abg-act-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid #F4F6FA;
}
.abg-act-item:last-child { border-bottom: none; padding-bottom: 0; }
.abg-act-dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0; margin-top: 5px;
}
.abg-act-text { flex: 1; font-size: 12.5px; color: #374151; line-height: 1.5; font-weight: 500; }
.abg-act-text strong { color: #1A1A2E; font-weight: 700; }
.abg-act-time { font-size: 11px; color: #C0C7D0; font-weight: 600; white-space: nowrap; flex-shrink: 0; }

/* RESPONSIVE */
@media (max-width: 1100px) {
    .abg-stats { grid-template-columns: repeat(2, 1fr); }
    .abg-two-col { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .abg-nav { padding: 0 20px; height: 68px; }
    .abg-search-pill { display: none; }
    .abg-body { padding: 24px 20px 48px; }
    .abg-hero { padding: 28px 24px; flex-direction: column; align-items: flex-start; }
    .abg-hero h1 { font-size: 22px; }
    .abg-hero-actions { flex-direction: row; align-items: flex-start; width: 100%; }
    .abg-strip { padding: 0 20px; }
    .abg-stats { grid-template-columns: repeat(2, 1fr); gap: 12px; }
}
@media (max-width: 480px) {
    .abg-stats { grid-template-columns: 1fr 1fr; }
    .abg-btn-host { display: none; }
}
</style>

<div class="abg">

    {{-- ══════════════════════════════════════════
         TOP NAV
    ══════════════════════════════════════════ --}}
    <nav class="abg-nav">
        {{-- Logo --}}
        <a href="{{ route('dashboard') }}" class="abg-logo">
            <div class="abg-logo-icon">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
            <span class="abg-logo-text">Abanganan<span>Hub</span></span>
        </a>

        {{-- Center search pill --}}
        <div class="abg-search-pill">
            <div class="abg-search-seg">
                <span class="abg-search-label">Location</span>
                <input type="text" class="abg-search-input" placeholder="Cebu City, Philippines">
            </div>
            <div class="abg-search-seg" style="flex:0.7;">
                <span class="abg-search-label">Type</span>
                <input type="text" class="abg-search-input" placeholder="Bedspace, Room…">
            </div>
            <div class="abg-search-seg" style="flex:0.8; border-right:none;">
                <span class="abg-search-label">Budget</span>
                <input type="text" class="abg-search-input" placeholder="Any price">
            </div>
            <button class="abg-search-btn" aria-label="Search">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
        </div>

        {{-- Right side --}}
        <div class="abg-nav-right">
            <a href="#become-host" class="abg-btn-host">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Become a Host
            </a>

            <a href="#" class="abg-notif" aria-label="Notifications">
                <div class="abg-notif-dot"></div>
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </a>

            <div class="abg-avatar-wrap">
                <div class="abg-avatar" id="abg-avatar-btn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                    {{ strtoupper(substr(Auth::user()?->first_name ?? 'T', 0, 1)) }}
                </div>
                <div class="abg-dropdown" id="abg-avatar-menu" role="menu">
                    <div class="abg-dd-head">
                        <div class="abg-dd-name">{{ trim((Auth::user()?->first_name ?? '') . ' ' . (Auth::user()?->last_name ?? '')) ?: 'Tenant' }}</div>
                        <div class="abg-dd-email">{{ Auth::user()?->email }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="abg-dd-link" role="menuitem">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Account Settings
                    </a>
                    <a href="#reservations" class="abg-dd-link" role="menuitem">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        My Reservations
                    </a>
                    <a href="#messages" class="abg-dd-link" role="menuitem">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        Messages
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="abg-dd-btn danger" role="menuitem">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    {{-- ══════════════════════════════════════════
         CATEGORY STRIP — Airbnb icon tabs
    ══════════════════════════════════════════ --}}
    <div class="abg-strip">
        <a href="#" class="abg-strip-item active">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span class="abg-strip-label">All</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v4H3V5a2 2 0 012-2zm0 0v16m14-16v16M3 9h18M9 9v10m6-10v10"/></svg>
            <span class="abg-strip-label">Bedspace</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="10" width="18" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 10l9-7 9 7"/></svg>
            <span class="abg-strip-label">Room</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
            <span class="abg-strip-label">Apartment</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V8l8-5 8 5v13M9 21v-6h6v6"/></svg>
            <span class="abg-strip-label">House</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            <span class="abg-strip-label">Saved</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            <span class="abg-strip-label">Near Me</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            <span class="abg-strip-label">Verified</span>
        </a>
    </div>

    {{-- ══════════════════════════════════════════
         MAIN BODY
    ══════════════════════════════════════════ --}}
    <div class="abg-body">

        {{-- HERO BANNER --}}
        <div class="abg-hero">
            <div class="abg-hero-text">
                <div class="abg-hero-eyebrow">🏡 Cebu's Verified Rental Platform</div>
                <h1>Welcome back,<br>{{ Auth::user()?->first_name ?? 'Tenant' }}!</h1>
                <p>Find safe, verified accommodations across Cebu. Every listing reviewed. Every landlord checked.</p>
            </div>
            <div class="abg-hero-actions">
                <a href="#" class="abg-btn-white">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Browse Properties
                </a>
                <a href="#reservations" class="abg-btn-outline-white">
                    View My Reservations
                </a>
            </div>
        </div>

        {{-- STAT CARDS --}}
        <div class="abg-stats">
            {{-- Upcoming Stays --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#286CD2;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#EEF5FF,#D7E8F3);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="abg-stat-label">Upcoming Stays</div>
                <div class="abg-stat-num" style="color:#286CD2;">{{ $upcomingCount ?? 0 }}</div>
                <div class="abg-stat-sub">Active reservations</div>
            </div>
            {{-- Messages --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#7C3AED;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#F3EEFF,#EDE0FF);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#7C3AED" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                </div>
                <div class="abg-stat-label">Messages</div>
                <div class="abg-stat-num" style="color:#7C3AED;">{{ $messagesCount ?? 0 }}</div>
                <div class="abg-stat-sub">Unread threads</div>
            </div>
            {{-- Saved --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#059669;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#ECFDF5,#D1FAE5);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <div class="abg-stat-label">Saved Listings</div>
                <div class="abg-stat-num" style="color:#059669;">{{ $savedCount ?? 0 }}</div>
                <div class="abg-stat-sub">In your favorites</div>
            </div>
            {{-- Support --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#BD5434;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#FFF4EE,#FFE5D8);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#BD5434" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 0A7.5 7.5 0 1115.75 21a7.5 7.5 0 00-1.25-10.25M14.828 8.485L12 11.314"/></svg>
                </div>
                <div class="abg-stat-label">Support Tickets</div>
                <div class="abg-stat-num" style="color:#BD5434;">{{ $supportCount ?? 0 }}</div>
                <div class="abg-stat-sub">Open cases</div>
            </div>
        </div>

        {{-- RESERVATIONS SECTION --}}
        <div id="reservations">
            <div class="abg-section-row">
                <div>
                    <div class="abg-section-title">My Reservations</div>
                    <div class="abg-section-sub">Track your current and upcoming bookings in Cebu</div>
                </div>
                <a href="#" class="abg-see-all">View all</a>
            </div>

            <div class="abg-filter-bar">
                <div class="abg-filter-search">
                    <svg class="fs-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="reservation-search" placeholder="Search by property or reference…">
                </div>
                <select id="reservation-filter" class="abg-filter-select" aria-label="Filter by status">
                    <option value="all">All Statuses</option>
                    <option value="Approved">Approved</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="abg-resv-list" id="reservations-list">
                @forelse($reservations as $reservation)
                    @php
                        $pillClass = match($reservation->reservation_status) {
                            'Approved'  => 'abg-pill-approved',
                            'Pending'   => 'abg-pill-pending',
                            'Rejected'  => 'abg-pill-rejected',
                            default     => 'abg-pill-cancelled',
                        };
                    @endphp
                    <div class="abg-resv-card reservation-row" data-status="{{ $reservation->reservation_status }}">
                        <div class="abg-resv-thumb">
                            {{ strtoupper(substr($reservation->property->title ?? 'P', 0, 1)) }}
                        </div>
                        <div class="abg-resv-info">
                            <div class="abg-resv-title property-title">{{ $reservation->property->title ?? 'Property' }}</div>
                            <div class="abg-resv-meta">
                                Check-in: <span>{{ $reservation->reservation_date?->format('M d, Y') ?? 'TBD' }}</span>
                                @if($reservation->property->address ?? false)
                                    &nbsp;·&nbsp; <span>{{ Str::limit($reservation->property->address, 40) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="abg-resv-right">
                            <div class="abg-pill {{ $pillClass }}">{{ $reservation->reservation_status }}</div>
                            <div class="abg-resv-ref reference-id">#R{{ $reservation->id }}</div>
                        </div>
                    </div>
                @empty
                    <div class="abg-empty">
                        <div class="abg-empty-icon">
                            <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="abg-empty-title">No reservations yet</div>
                        <div class="abg-empty-sub">Browse verified properties across Cebu and make your first booking today.</div>
                        <a href="#" class="abg-btn-primary">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Browse Properties
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- TWO-COL: QUICK ACTIONS + HOST CARD --}}
        <div class="abg-two-col" id="become-host">

            {{-- LEFT: Quick Actions + Activity --}}
            <div>
                <div class="abg-section-row" style="margin-bottom:20px;">
                    <div>
                        <div class="abg-section-title">Quick Actions</div>
                        <div class="abg-section-sub">Shortcuts to your most-used features</div>
                    </div>
                </div>

                <div class="abg-qa-card">
                    <a href="#" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        Search Properties
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        View Saved Favorites
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#messages" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        </div>
                        Open Messages
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        Account Settings
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 0A7.5 7.5 0 1115.75 21a7.5 7.5 0 00-1.25-10.25"/></svg>
                        </div>
                        Contact Support
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Activity --}}
                <div style="margin-top:24px;">
                    <div class="abg-section-row" style="margin-bottom:14px;">
                        <div>
                            <div class="abg-section-title" style="font-size:16px;">Recent Activity</div>
                            <div class="abg-section-sub">Latest updates on your account</div>
                        </div>
                    </div>
                    <div class="abg-activity">
                        <div class="abg-act-item">
                            <div class="abg-act-dot" style="background:#286CD2;"></div>
                            <div class="abg-act-text">Account created successfully. Welcome to <strong>AbangananHub</strong>!</div>
                            <div class="abg-act-time">Just now</div>
                        </div>
                        <div class="abg-act-item">
                            <div class="abg-act-dot" style="background:#059669;"></div>
                            <div class="abg-act-text">Email verified. You can now browse and reserve properties across <strong>Cebu</strong>.</div>
                            <div class="abg-act-time">Today</div>
                        </div>
                        <div class="abg-act-item">
                            <div class="abg-act-dot" style="background:#C0C7D0;"></div>
                            <div class="abg-act-text">No new notifications at this time.</div>
                            <div class="abg-act-time">—</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Become a Host card --}}
            <div>
                <div class="abg-section-row" style="margin-bottom:20px;">
                    <div>
                        <div class="abg-section-title">Become a Host</div>
                        <div class="abg-section-sub">Earn income from your property</div>
                    </div>
                </div>

                <div class="abg-host-card">
                    <div class="abg-host-badge">For Landlords</div>
                    <div class="abg-host-title">List your property on AbangananHub</div>
                    <div class="abg-host-sub">Join verified landlords across Cebu and connect directly with trusted tenants on a secure platform.</div>
                    <div class="abg-host-perks">
                        <div class="abg-host-perk"><div class="abg-host-perk-dot"></div>Admin-verified listing approval</div>
                        <div class="abg-host-perk"><div class="abg-host-perk-dot"></div>Real-time tenant inquiries</div>
                        <div class="abg-host-perk"><div class="abg-host-perk-dot"></div>Dashboard analytics & reports</div>
                        <div class="abg-host-perk"><div class="abg-host-perk-dot"></div>Reservation management tools</div>
                    </div>
                    <a href="#" class="abg-btn-host-card">
                        Apply to be a Host
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>

    </div>{{-- end body --}}
</div>{{-- end abg --}}

<script>
(function () {
    // Avatar dropdown
    const btn  = document.getElementById('abg-avatar-btn');
    const menu = document.getElementById('abg-avatar-menu');
    if (btn && menu) {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const open = menu.classList.toggle('open');
            btn.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', () => {
            menu.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        });
        menu.addEventListener('click', e => e.stopPropagation());
        btn.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
        });
    }

    // Category strip tabs
    document.querySelectorAll('.abg-strip-item').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            document.querySelectorAll('.abg-strip-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
        });
    });

    // Reservation filter + search
    const filter  = document.getElementById('reservation-filter');
    const search  = document.getElementById('reservation-search');
    const rows    = document.querySelectorAll('#reservations-list .reservation-row');

    function applyFilters() {
        const status = filter ? filter.value : 'all';
        const query  = search ? search.value.toLowerCase().trim() : '';
        rows.forEach(row => {
            const matchStatus = status === 'all' || row.getAttribute('data-status') === status;
            const title = row.querySelector('.property-title')?.textContent.toLowerCase() || '';
            const ref   = row.querySelector('.reference-id')?.textContent.toLowerCase() || '';
            const matchQuery = !query || title.includes(query) || ref.includes(query);
            row.style.display = (matchStatus && matchQuery) ? '' : 'none';
        });
    }
    if (filter) filter.addEventListener('change', applyFilters);
    if (search) search.addEventListener('input', applyFilters);
})();
</script>

</x-app-layout>
BLADE_EOF
echo "Done — $(wc -l < /mnt/user-data/outputs/dashboard.blade.php) lines"
Output