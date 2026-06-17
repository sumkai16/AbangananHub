<x-app-layout>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.abg { font-family: 'Inter', -apple-system, sans-serif; background: #F5F7FB; color: #1A1A2E; min-height: 100vh; }

/* ════════════════════════════════════════════
   TOP NAV — Premium Airbnb-style sticky nav
   ════════════════════════════════════════════ */
.abg-nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(255,255,255,0.97);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(40,108,210,0.08);
    padding: 0 48px;
    height: 80px;
    display: flex; align-items: center; justify-content: space-between; gap: 32px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
}

/* Logo */
.abg-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
.abg-logo-icon {
    width: 42px; height: 42px; border-radius: 13px;
    background: linear-gradient(135deg, #1A3A6E 0%, #286CD2 50%, #61B2F0 100%);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 6px 20px rgba(40,108,210,0.32);
    transition: transform 0.2s;
}
.abg-logo-icon:hover { transform: scale(1.05); }
.abg-logo-text { font-size: 17px; font-weight: 900; color: #1A1A2E; letter-spacing: -0.5px; }
.abg-logo-text span { color: #286CD2; }

/* Nav right */
.abg-nav-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.abg-btn-host {
    height: 44px; padding: 0 20px;
    border: 1.5px solid #E0E6F0; border-radius: 28px;
    background: #fff; font-size: 14px; font-weight: 700;
    color: #1A1A2E; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px;
    transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.abg-btn-host:hover {
    border-color: #286CD2; color: #286CD2;
    box-shadow: 0 4px 16px rgba(40,108,210,0.18);
    background: linear-gradient(135deg, #F0F6FF, #F7FBFF);
}
.abg-notif {
    width: 44px; height: 44px; border-radius: 50%;
    border: 1.5px solid #E0E6F0; background: #fff;
    display: flex; align-items: center; justify-content: center;
    color: #5A6475; text-decoration: none; position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s;
}
.abg-notif:hover { 
    border-color: #286CD2; color: #286CD2;
    box-shadow: 0 4px 12px rgba(40,108,210,0.15);
}
.abg-notif-dot {
    position: absolute; top: 6px; right: 6px;
    width: 10px; height: 10px; border-radius: 50%;
    background: #BD5434; border: 2.5px solid #fff;
    box-shadow: 0 0 0 1px rgba(189, 84, 52, 0.2);
}
.abg-avatar-wrap { position: relative; }
.abg-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #1e4fa3, #286CD2);
    color: #fff; font-size: 16px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: 2.5px solid #fff;
    box-shadow: 0 0 0 1.5px #D7E8F3, 0 4px 12px rgba(40,108,210,0.28);
    transition: all 0.2s;
}
.abg-avatar:hover { 
    box-shadow: 0 0 0 2px #286CD2, 0 6px 16px rgba(40,108,210,0.36);
    transform: scale(1.04);
}
.abg-dropdown {
    position: absolute; top: calc(100% + 14px); right: 0;
    background: #fff; border: 1px solid #E8ECF0; border-radius: 18px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.15); min-width: 220px;
    padding: 8px; z-index: 200; display: none;
    animation: dropIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes dropIn { from { opacity:0; transform: translateY(-10px); } to { opacity:1; transform: none; } }
.abg-dropdown.open { display: block; }
.abg-dd-head { padding: 12px 14px 14px; border-bottom: 1px solid #F2F4F7; margin-bottom: 8px; }
.abg-dd-name { font-size: 14px; font-weight: 700; color: #1A1A2E; }
.abg-dd-email { font-size: 12px; color: #9AA0AB; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.abg-dd-link, .abg-dd-btn {
    display: flex; align-items: center; gap: 11px;
    width: 100%; padding: 10px 14px; border-radius: 11px;
    font-size: 13.5px; font-weight: 500; color: #374151;
    text-decoration: none; background: transparent;
    border: none; cursor: pointer; font-family: inherit;
    transition: all 0.12s; text-align: left;
}
.abg-dd-link:hover, .abg-dd-btn:hover { background: #F9FAFB; color: #286CD2; }
.abg-dd-btn.danger { color: #DC2626; margin-top: 6px; border-top: 1px solid #F2F4F7; padding-top: 14px; }
.abg-dd-btn.danger:hover { background: #FEF2F2; }

/* HOST DROPDOWN */
.abg-host-wrap { position: relative; }
.abg-host-dropdown {
    position: absolute; top: calc(100% + 12px); right: 0; z-index: 220;
    width: 220px; max-width: 92vw; background: #fff; border: 1px solid #E8ECF0; border-radius: 12px;
    box-shadow: 0 20px 50px rgba(16,24,40,0.08); padding: 6px; transform-origin: top right;
    opacity: 0; transform: scale(0.96) translateY(-6px); pointer-events: none;
    transition: transform 220ms cubic-bezier(.2,.9,.2,1), opacity 180ms ease;
}
.abg-host-dropdown.open { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; }
.abg-host-dropdown a, .abg-host-dropdown button {
    display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #111827;
    border-radius: 8px; font-weight: 600; font-size: 14px; background: transparent; width:100%; text-align:left; border:none; cursor:pointer;
}
.abg-host-dropdown a:hover, .abg-host-dropdown button:hover { background: #F8FAFC; color: #0F172A; }
.abg-host-divider { height:1px; background:#F1F5F9; margin:8px 6px; border-radius:1px; }
.abg-host-note { display:flex; gap:10px; padding:8px 10px; font-size:13px; color:#475569; align-items:flex-start; }
.abg-host-note img { width:36px; height:36px; border-radius:6px; object-fit:cover; }

/* ════════════════════════════════════════════
   MAIN BODY & PROFILE CONTENT
   ════════════════════════════════════════════ */
.abg-body { max-width: 1320px; margin: 0 auto; padding: 48px 48px 80px; }

/* Banner Card */
.abg-profile-banner {
    background: linear-gradient(135deg, #1A3A6E 0%, #286CD2 50%, #61B2F0 100%);
    border-radius: 24px; padding: 40px; color: #fff;
    box-shadow: 0 10px 32px rgba(40,108,210,0.22);
    margin-bottom: 36px; display: flex; align-items: center; justify-content: space-between;
    position: relative; overflow: hidden;
}
.abg-profile-banner::after {
    content: ''; position: absolute; right: -40px; top: -40px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(255,255,255,0.06); pointer-events: none;
}
.abg-profile-banner-left { display: flex; align-items: center; gap: 24px; position: relative; z-index: 2; }
.abg-profile-banner-avatar {
    width: 84px; height: 84px; border-radius: 50%;
    background: #fff; color: #286CD2; font-size: 32px; font-weight: 900;
    display: flex; align-items: center; justify-content: center;
    border: 3.5px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.abg-profile-banner-name { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
.abg-profile-banner-email { font-size: 14.5px; color: rgba(255,255,255,0.85); margin-top: 4px; font-weight: 500; }
.abg-profile-banner-meta { display: flex; gap: 8px; margin-top: 12px; }
.abg-profile-badge {
    background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px);
    color: #fff; font-size: 10.5px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.8px; padding: 4px 12px; border-radius: 20px;
}

/* Two-column layout */
.abg-profile-grid { display: grid; grid-template-columns: 280px 1fr; gap: 32px; }

/* Sidebar navigation */
.abg-profile-sidebar {
    background: #fff; border: 1px solid #EAECF0; border-radius: 20px;
    padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    height: fit-content; position: sticky; top: 112px;
}
.abg-profile-sidebar-title {
    font-size: 11px; font-weight: 800; color: #9AA0AB;
    text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 16px;
}
.abg-sidebar-menu { display: flex; flex-direction: column; gap: 8px; }
.abg-sidebar-link {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 12px;
    font-size: 14px; font-weight: 600; color: #5A6475;
    text-decoration: none; transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
    border: 1px solid transparent;
}
.abg-sidebar-link:hover {
    background: #F8FAFC; color: #286CD2;
}
.abg-sidebar-link.active {
    background: #EEF5FF; color: #286CD2;
    border-color: rgba(40,108,210,0.08);
}
.abg-sidebar-link svg { flex-shrink: 0; }

/* Styled Forms and Cards */
.abg-card {
    background: #fff; border: 1px solid #EAECF0; border-radius: 20px;
    padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    margin-bottom: 28px; transition: border-color 0.2s;
}
.abg-card:hover { border-color: #D7E8F3; }
.abg-card-title { font-size: 18px; font-weight: 800; color: #1A1A2E; margin-bottom: 6px; letter-spacing: -0.3px; }
.abg-card-sub { font-size: 13.5px; color: #9AA0AB; margin-bottom: 24px; font-weight: 500; line-height: 1.5; }

/* Form Field Styling */
.abg-label { font-size: 12.5px; font-weight: 700; color: #374151; margin-bottom: 6px; display: block; }
.abg-input-group { margin-bottom: 20px; }
.abg-input-group:last-of-type { margin-bottom: 0; }
.abg-input {
    width: 100%; height: 46px; border: 1.5px solid #E8ECF0; border-radius: 12px;
    padding: 0 16px; font-size: 14px; font-family: inherit; font-weight: 500;
    color: #1A1A2E; background: #F9FAFB; outline: none; transition: all 0.2s;
}
.abg-input:focus {
    border-color: #286CD2; background: #fff;
    box-shadow: 0 0 0 3.5px rgba(40,108,210,0.08);
}
.abg-input::placeholder { color: #B8C2D4; }

/* Errors & Feedback */
.abg-error { font-size: 12.5px; color: #DC2626; font-weight: 600; margin-top: 6px; display: block; }

/* Buttons */
.abg-btn-primary {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 46px; padding: 0 28px; background: linear-gradient(135deg, #286CD2, #61B2F0);
    color: #fff; border-radius: 12px; font-size: 14.5px; font-weight: 700;
    border: none; cursor: pointer; text-decoration: none;
    box-shadow: 0 6px 18px rgba(40,108,210,0.28);
    transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
}
.abg-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(40,108,210,0.36);
}
.abg-btn-primary:active { transform: translateY(0); }

.abg-btn-danger {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 46px; padding: 0 28px; background: linear-gradient(135deg, #DC2626, #F87171);
    color: #fff; border-radius: 12px; font-size: 14.5px; font-weight: 700;
    border: none; cursor: pointer; text-decoration: none;
    box-shadow: 0 6px 18px rgba(220,38,38,0.22);
    transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
}
.abg-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(220,38,38,0.32);
}
.abg-btn-danger:active { transform: translateY(0); }

.abg-btn-secondary {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 46px; padding: 0 24px; background: #fff;
    color: #475569; border-radius: 12px; font-size: 14.5px; font-weight: 700;
    border: 1.5px solid #E2E8F0; cursor: pointer; text-decoration: none;
    transition: all 0.15s;
}
.abg-btn-secondary:hover {
    background: #F8FAFC; border-color: #CBD5E1; color: #1E293B;
}

.abg-card-danger {
    border-color: rgba(220, 38, 38, 0.15);
    background: #FFFDFD;
}
.abg-card-danger:hover {
    border-color: rgba(220, 38, 38, 0.3);
}

/* Modal styling overrides to fit the abg theme */
.abg-modal-form {
    padding: 32px;
}
.abg-modal-title {
    font-size: 20px; font-weight: 800; color: #1A1A2E; margin-bottom: 12px; letter-spacing: -0.4px;
}
.abg-modal-text {
    font-size: 14px; color: #5A6475; line-height: 1.6; margin-bottom: 24px; font-weight: 500;
}

/* RESPONSIVE LAYOUT */
@media (max-width: 1024px) {
    .abg-profile-grid { grid-template-columns: 1fr; }
    .abg-profile-sidebar { display: none; }
    .abg-nav { padding: 0 32px; }
    .abg-body { padding: 36px 32px 64px; }
}

@media (max-width: 768px) {
    .abg-nav { padding: 0 20px; height: 72px; }
    .abg-btn-host { display: none; }
    .abg-body { padding: 28px 20px 56px; }
    .abg-profile-banner { padding: 28px; flex-direction: column; align-items: flex-start; gap: 20px; }
    .abg-profile-banner-avatar { width: 76px; height: 76px; font-size: 28px; }
    .abg-profile-banner-name { font-size: 22px; }
    .abg-card { padding: 24px; }
}
</style>

<div class="abg">
    {{-- ══════════════════════════════════════════
         TOP NAV — Sticky Header
    ══════════════════════════════════════════ --}}
    <nav class="abg-nav">
        <a href="{{ route('dashboard') }}" class="abg-logo">
            <div class="abg-logo-icon">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
            <span class="abg-logo-text">Abanganan<span>Hub</span></span>
        </a>

        <div class="abg-nav-right">
            <div class="abg-host-wrap">
                <button type="button" id="abg-host-btn" class="abg-btn-host" aria-haspopup="true" aria-expanded="false">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Become a Landlord
                </button>
                <div class="abg-host-dropdown" id="abg-host-menu" role="menu" aria-hidden="true">
                    <a href="#wishlists" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 10-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z"/></svg>
                        Wishlists
                    </a>
                    <a href="#trips" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7"/></svg>
                        Trips
                    </a>
                    <a href="#messages" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        Messages
                    </a>
                    <a href="{{ route('profile.edit') }}" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Profile
                    </a>
                    <div class="abg-host-divider"></div>
                    <a href="#notifications" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L3 17h5"/></svg>
                        Notifications
                    </a>
                    <a href="#account" role="menuitem">Account settings</a>
                    <a href="#language" role="menuitem">Languages & currency</a>
                    <a href="#help" role="menuitem">Help Center</a>
                    <div class="abg-host-divider"></div>
                    <div class="abg-host-note">
                        <img src="/images/host-note.jpg" alt="host" onerror="this.style.display='none'">
                        <div>
                            <div style="font-weight:800;color:#0F172A;">Become a host</div>
                            <div style="font-size:13px;color:#64748B;">It's easy to start hosting and earn extra income.</div>
                        </div>
                    </div>
                    <div class="abg-host-divider"></div>
                    <a href="#refer" role="menuitem">Refer a Host</a>
                    <a href="#cohost" role="menuitem">Find a co-host</a>
                    <a href="#gifts" role="menuitem">Gift cards</a>
                    <form id="logout-form-2" action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="abg-dd-btn" role="menuitem">Log out</button>
                    </form>
                </div>
            </div>

            <a href="#" class="abg-notif" aria-label="Notifications">
                <div class="abg-notif-dot"></div>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </a>

            <div class="abg-avatar-wrap">
                <div class="abg-avatar" id="abg-avatar-btn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                    {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}
                </div>
                <div class="abg-dropdown" id="abg-avatar-menu" role="menu">
                    <div class="abg-dd-head">
                        <div class="abg-dd-name">{{ trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? '')) ?: 'User' }}</div>
                        <div class="abg-dd-email">{{ Auth::user()->email }}</div>
                    </div>
                    <a href="{{ route('dashboard') }}" class="abg-dd-link" role="menuitem">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('profile.edit') }}" class="abg-dd-link" role="menuitem" style="color: #286CD2; font-weight: 700; background: #F9FAFB;">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Account Settings
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="abg-dd-btn danger" role="menuitem">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    {{-- ══════════════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════════════ --}}
    <div class="abg-body">
        
        {{-- Profile Header Banner --}}
        <div class="abg-profile-banner">
            <div class="abg-profile-banner-left">
                <div class="abg-profile-banner-avatar">
                    {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <h1 class="abg-profile-banner-name">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</h1>
                    <div class="abg-profile-banner-email">{{ Auth::user()->email }}</div>
                    <div class="abg-profile-banner-meta">
                        <span class="abg-profile-badge">Member since {{ Auth::user()->created_at?->format('M Y') ?? 'Joined recently' }}</span>
                        @if(Auth::user()->account_status)
                            <span class="abg-profile-badge" style="background: rgba(255, 255, 255, 0.25);">{{ Auth::user()->account_status }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 2-Column Responsive Layout --}}
        <div class="abg-profile-grid">
            
            {{-- Left column: Sidebar --}}
            <div class="abg-profile-sidebar">
                <div class="abg-profile-sidebar-title">Profile Settings</div>
                <div class="abg-sidebar-menu">
                    <a href="#personal-info" class="abg-sidebar-link active" id="link-personal">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Personal Details
                    </a>
                    <a href="#security" class="abg-sidebar-link" id="link-security">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Security & Password
                    </a>
                    <a href="#danger-zone" class="abg-sidebar-link" id="link-danger">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Danger Zone
                    </a>
                </div>
            </div>

            {{-- Right column: Forms --}}
            <div class="abg-profile-content">
                
                <div id="personal-info">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <div id="security">
                    @include('profile.partials.update-password-form')
                </div>

                <div id="danger-zone">
                    @include('profile.partials.delete-user-form')
                </div>

            </div>
            
        </div>
    </div>
</div>

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

    // Host dropdown (Become a Landlord)
    const hostBtn = document.getElementById('abg-host-btn');
    const hostMenu = document.getElementById('abg-host-menu');
    if (hostBtn && hostMenu) {
        hostBtn.addEventListener('click', e => {
            e.stopPropagation();
            const open = hostMenu.classList.toggle('open');
            hostMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
            hostBtn.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', () => {
            hostMenu.classList.remove('open');
            hostMenu.setAttribute('aria-hidden', 'true');
            hostBtn.setAttribute('aria-expanded', 'false');
        });
        hostMenu.addEventListener('click', e => e.stopPropagation());
        hostBtn.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); hostBtn.click(); }
        });
    }

    // Navigation scroll spy highlights
    const sections = [
        { id: 'personal-info', linkId: 'link-personal' },
        { id: 'security', linkId: 'link-security' },
        { id: 'danger-zone', linkId: 'link-danger' }
    ];

    window.addEventListener('scroll', () => {
        let activeId = 'personal-info';
        
        for (const section of sections) {
            const el = document.getElementById(section.id);
            if (el) {
                const rect = el.getBoundingClientRect();
                // If top of section is within upper 180px of screen
                if (rect.top <= 180) {
                    activeId = section.id;
                }
            }
        }

        sections.forEach(section => {
            const link = document.getElementById(section.linkId);
            if (link) {
                if (section.id === activeId) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            }
        });
    });
})();
</script>
</x-app-layout>
