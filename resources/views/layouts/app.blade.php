<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'AbangananHub' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #F7F8FA;
            color: #1A1A2E;
            min-height: 100vh;
        }

        /* ── NAV ── */
        .abg-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            padding: 0 40px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            box-shadow: 0 1px 12px rgba(0, 0, 0, 0.06);
        }

        /* Logo */
        .abg-logo {
            display: flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .abg-logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, #286CD2, #61B2F0);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(40, 108, 210, 0.35);
        }

        .abg-logo-text {
            font-size: 16px;
            font-weight: 800;
            color: #1A1A2E;
            letter-spacing: -0.4px;
        }

        .abg-logo-text span {
            color: #286CD2;
        }

        /* Search pill */
        .abg-search-pill {
            flex: 1;
            max-width: 520px;
            display: flex;
            align-items: center;
            border: 1.5px solid #E0E4EB;
            border-radius: 40px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
            overflow: hidden;
            transition: box-shadow 0.2s;
            cursor: pointer;
        }

        .abg-search-pill:focus-within {
            box-shadow: 0 4px 20px rgba(40, 108, 210, 0.18);
            border-color: #286CD2;
        }

        .abg-search-seg {
            flex: 1;
            padding: 10px 18px;
            display: flex;
            flex-direction: column;
            gap: 1px;
            border-right: 1px solid #E8ECF0;
            cursor: pointer;
        }

        .abg-search-seg:last-of-type {
            border-right: none;
        }

        .abg-search-label {
            font-size: 10px;
            font-weight: 700;
            color: #1A1A2E;
            letter-spacing: 0.2px;
        }

        .abg-search-input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 12.5px;
            color: #6B7280;
            font-family: inherit;
            font-weight: 400;
            width: 100%;
            cursor: pointer;
        }

        .abg-search-input::placeholder {
            color: #B0B8C4;
        }

        .abg-search-btn {
            width: 40px;
            height: 40px;
            margin: 4px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #286CD2, #61B2F0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(40, 108, 210, 0.4);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .abg-search-btn:hover {
            transform: scale(1.07);
            box-shadow: 0 4px 14px rgba(40, 108, 210, 0.5);
        }

        /* Nav right */
        .abg-nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .abg-btn-host {
            height: 40px;
            padding: 0 18px;
            border: 1.5px solid #E0E4EB;
            border-radius: 24px;
            background: #fff;
            font-size: 13px;
            font-weight: 700;
            color: #1A1A2E;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.18s;
            white-space: nowrap;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .abg-btn-host:hover {
            border-color: #286CD2;
            color: #286CD2;
            box-shadow: 0 2px 10px rgba(40, 108, 210, 0.15);
            background: #F0F6FF;
        }

        .abg-notif {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1.5px solid #E0E4EB;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5A6475;
            text-decoration: none;
            position: relative;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
            transition: all 0.18s;
        }

        .abg-notif:hover {
            border-color: #286CD2;
            color: #286CD2;
        }

        .abg-notif-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #BD5434;
            border: 2px solid #fff;
        }

        /* Avatar + dropdown */
        .abg-avatar-wrap {
            position: relative;
        }

        .abg-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e4fa3, #286CD2);
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2.5px solid #fff;
            box-shadow: 0 0 0 1.5px #D7E8F3, 0 2px 8px rgba(40, 108, 210, 0.25);
            transition: box-shadow 0.18s;
        }

        .abg-avatar:hover {
            box-shadow: 0 0 0 2px #286CD2, 0 4px 14px rgba(40, 108, 210, 0.3);
        }

        .abg-dropdown {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            background: #fff;
            border: 1px solid #E8ECF0;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.13);
            min-width: 210px;
            padding: 8px;
            z-index: 200;
            display: none;
            animation: dropIn 0.15s ease;
        }

        @keyframes dropIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .abg-dropdown.open {
            display: block;
        }

        .abg-dd-head {
            padding: 10px 12px 12px;
            border-bottom: 1px solid #F0F2F5;
            margin-bottom: 6px;
        }

        .abg-dd-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #1A1A2E;
        }

        .abg-dd-email {
            font-size: 11.5px;
            color: #9AA0AB;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .abg-dd-link,
        .abg-dd-btn {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 9px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            text-decoration: none;
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.12s;
            text-align: left;
        }

        .abg-dd-link:hover,
        .abg-dd-btn:hover {
            background: #F7F8FA;
        }

        .abg-dd-btn.danger {
            color: #DC2626;
            margin-top: 4px;
            border-top: 1px solid #F0F2F5;
            padding-top: 12px;
        }

        .abg-dd-btn.danger:hover {
            background: #FEF2F2;
        }

        /* Flash messages */
        .abg-flash {
            max-width: 1280px;
            margin: 16px auto 0;
            padding: 0 40px;
        }

        .abg-flash-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .abg-flash-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .abg-flash-close {
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 16px;
            line-height: 1;
            padding: 0 0 0 12px;
        }

        .abg-flash-close:hover {
            opacity: 1;
        }

        /* Footer */
        .abg-footer {
            background: #5E6968;
            color: rgba(255, 255, 255, 0.7);
            padding: 24px 40px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .abg-nav {
                padding: 0 20px;
                height: 68px;
            }

            .abg-search-pill {
                display: none;
            }

            .abg-flash {
                padding: 0 20px;
            }

            .abg-footer {
                padding: 20px;
                flex-direction: column;
                gap: 6px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .abg-btn-host {
                display: none;
            }
        }
    </style>
</head>

<body>

    {{-- ══════════════════════════════════════════
    TOP NAV
    ══════════════════════════════════════════ --}}
    <nav class="abg-nav">

        {{-- Logo --}}
        <a href="{{ route('dashboard') }}" class="abg-logo">
            <div class="abg-logo-icon">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </div>
            <span class="abg-logo-text">Abanganan<span>Hub</span></span>
        </a>

        {{-- Center search pill --}}
        <form action="{{ route('properties.index') }}" method="GET" class="abg-search-pill">
            <div class="abg-search-seg">
                <span class="abg-search-label">Location</span>
                <input type="text" name="location" class="abg-search-input" placeholder="Cebu City, Philippines">
            </div>
            <div class="abg-search-seg" style="flex:0.7;">
                <span class="abg-search-label">Type</span>
                <input type="text" name="type" class="abg-search-input" placeholder="Bedspace, Room…">
            </div>
            <div class="abg-search-seg" style="flex:0.8; border-right:none;">
                <span class="abg-search-label">Budget</span>
                <input type="text" name="budget" class="abg-search-input" placeholder="Any price">
            </div>
            <button type="submit" class="abg-search-btn" aria-label="Search">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
        </form>

        {{-- Right side --}}
        <div class="abg-nav-right">

            {{-- Become a Host / My Listings — role-aware --}}
            @if(auth()->user()->hasRole('Landlord'))
                <a href="{{ route('landlord.listings.index') }}" class="abg-btn-host">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    My Listings
                </a>
            @else
                <a href="#" class="abg-btn-host">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Become a Host
                </a>
            @endif

            {{-- Notifications --}}
            <a href="{{ route('notifications.index') }}" class="abg-notif" aria-label="Notifications">
                @php $unread = auth()->user()->notifications()->where('is_read', false)->count(); @endphp
                @if($unread > 0)
                    <div class="abg-notif-dot"></div>
                @endif
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </a>

            {{-- Avatar + dropdown --}}
            <div class="abg-avatar-wrap">
                <div class="abg-avatar" id="abg-avatar-btn" role="button" tabindex="0" aria-haspopup="true"
                    aria-expanded="false">
                    {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                </div>
                <div class="abg-dropdown" id="abg-avatar-menu" role="menu">
                    <div class="abg-dd-head">
                        <div class="abg-dd-name">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
                        <div class="abg-dd-email">{{ auth()->user()->email }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="abg-dd-link" role="menuitem">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Account Settings
                    </a>
                    <a href="{{ route('reservations.index') }}" class="abg-dd-link" role="menuitem">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        My Reservations
                    </a>
                    <a href="{{ route('conversations.index') }}" class="abg-dd-link" role="menuitem">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        Messages
                    </a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="abg-dd-btn danger" role="menuitem">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </nav>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="abg-flash">
            <div class="abg-flash-success">
                <span>{{ session('success') }}</span>
                <button class="abg-flash-close" onclick="this.parentElement.remove()">✕</button>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="abg-flash">
            <div class="abg-flash-error">
                <span>{{ session('error') }}</span>
                <button class="abg-flash-close" onclick="this.parentElement.remove()">✕</button>
            </div>
        </div>
    @endif

    {{-- Page content --}}
    {{ $slot }}

    {{-- Footer --}}
    <footer class="abg-footer">
        <span>© {{ date('Y') }} AbangananHub. All rights reserved.</span>
        <span>Cebu, Philippines · SDG 16</span>
    </footer>

    {{-- Avatar dropdown JS --}}
    <script>
        (function () {
            const btn = document.getElementById('abg-avatar-btn');
            const menu = document.getElementById('abg-avatar-menu');
            if (!btn || !menu) return;
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
        })();
    </script>

    @stack('scripts')
</body>

</html>