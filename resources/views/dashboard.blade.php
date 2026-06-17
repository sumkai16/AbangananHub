<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .abg {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #F5F7FB;
            color: #1A1A2E;
            min-height: 100vh;
        }

        /* ════════════════════════════════════════════
   TOP NAV — Premium Airbnb-style sticky nav
   ════════════════════════════════════════════ */
        .abg-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(40, 108, 210, 0.08);
            padding: 0 48px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 32px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
        }

        /* Logo */
        .abg-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .abg-logo-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: linear-gradient(135deg, #1A3A6E 0%, #286CD2 50%, #61B2F0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(40, 108, 210, 0.32);
            transition: transform 0.2s;
        }

        .abg-logo-icon:hover {
            transform: scale(1.05);
        }

        .abg-logo-text {
            font-size: 17px;
            font-weight: 900;
            color: #1A1A2E;
            letter-spacing: -0.5px;
        }

        .abg-logo-text span {
            color: #286CD2;
        }

        /* Center search pill — signature Airbnb element */
        .abg-search-pill {
            flex: 1;
            max-width: 580px;
            display: flex;
            align-items: center;
            border: 1.5px solid #E0E6F0;
            border-radius: 48px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(40, 108, 210, 0.08);
            overflow: hidden;
            transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            cursor: pointer;
        }

        .abg-search-pill:hover {
            border-color: #D7E8F3;
            box-shadow: 0 8px 28px rgba(40, 108, 210, 0.12);
        }

        .abg-search-pill:focus-within {
            box-shadow: 0 12px 32px rgba(40, 108, 210, 0.16);
            border-color: #286CD2;
        }

        .abg-search-seg {
            flex: 1;
            padding: 11px 20px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            border-right: 1px solid #EAEEF5;
            cursor: pointer;
        }

        .abg-search-seg:last-of-type {
            border-right: none;
        }

        .abg-search-label {
            font-size: 10px;
            font-weight: 700;
            color: #1A1A2E;
            letter-spacing: 0.3px;
        }

        .abg-search-input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 13px;
            color: #6B7280;
            font-family: inherit;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
        }

        .abg-search-input::placeholder {
            color: #B8C2D4;
            font-weight: 400;
        }

        .abg-search-btn {
            width: 44px;
            height: 44px;
            margin: 4px 4px 4px 6px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #286CD2, #61B2F0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(40, 108, 210, 0.36);
            transition: all 0.2s;
        }

        .abg-search-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 20px rgba(40, 108, 210, 0.44);
        }

        .abg-search-btn:active {
            transform: scale(0.96);
        }

        /* Nav right */
        .abg-nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .abg-btn-host {
            height: 44px;
            padding: 0 20px;
            border: 1.5px solid #E0E6F0;
            border-radius: 28px;
            background: #fff;
            font-size: 14px;
            font-weight: 700;
            color: #1A1A2E;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .abg-btn-host:hover {
            border-color: #286CD2;
            color: #286CD2;
            box-shadow: 0 4px 16px rgba(40, 108, 210, 0.18);
            background: linear-gradient(135deg, #F0F6FF, #F7FBFF);
        }

        .abg-notif {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1.5px solid #E0E6F0;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5A6475;
            text-decoration: none;
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        .abg-notif:hover {
            border-color: #286CD2;
            color: #286CD2;
            box-shadow: 0 4px 12px rgba(40, 108, 210, 0.15);
        }

        .abg-notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #BD5434;
            border: 2.5px solid #fff;
            box-shadow: 0 0 0 1px rgba(189, 84, 52, 0.2);
        }

        .abg-avatar-wrap {
            position: relative;
        }

        .abg-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e4fa3, #286CD2);
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2.5px solid #fff;
            box-shadow: 0 0 0 1.5px #D7E8F3, 0 4px 12px rgba(40, 108, 210, 0.28);
            transition: all 0.2s;
        }

        .abg-avatar:hover {
            box-shadow: 0 0 0 2px #286CD2, 0 6px 16px rgba(40, 108, 210, 0.36);
            transform: scale(1.04);
        }

        .abg-dropdown {
            position: absolute;
            top: calc(100% + 14px);
            right: 0;
            background: #fff;
            border: 1px solid #E8ECF0;
            border-radius: 18px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            padding: 8px;
            z-index: 200;
            display: none;
            animation: dropIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes dropIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
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
            padding: 12px 14px 14px;
            border-bottom: 1px solid #F2F4F7;
            margin-bottom: 8px;
        }

        .abg-dd-name {
            font-size: 14px;
            font-weight: 700;
            color: #1A1A2E;
        }

        .abg-dd-email {
            font-size: 12px;
            color: #9AA0AB;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .abg-dd-link,
        .abg-dd-btn {
            display: flex;
            align-items: center;
            gap: 11px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 11px;
            font-size: 13.5px;
            font-weight: 500;
            color: #374151;
            text-decoration: none;
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.12s;
            text-align: left;
        }

        .abg-dd-link:hover,
        .abg-dd-btn:hover {
            background: #F9FAFB;
            color: #286CD2;
        }

        .abg-dd-btn.danger {
            color: #DC2626;
            margin-top: 6px;
            border-top: 1px solid #F2F4F7;
            padding-top: 14px;
        }

        .abg-dd-btn.danger:hover {
            background: #FEF2F2;
        }

        /* HOST DROPDOWN */
        .abg-host-wrap {
            position: relative;
        }

        .abg-host-dropdown {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            z-index: 220;
            width: 220px;
            max-width: 92vw;
            background: #fff;
            border: 1px solid #E8ECF0;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(16, 24, 40, 0.08);
            padding: 6px;
            transform-origin: top right;
            opacity: 0;
            transform: scale(0.96) translateY(-6px);
            pointer-events: none;
            transition: transform 220ms cubic-bezier(.2, .9, .2, 1), opacity 180ms ease;
        }

        .abg-host-dropdown.open {
            opacity: 1;
            transform: scale(1) translateY(0);
            pointer-events: auto;
        }

        .abg-host-dropdown a,
        .abg-host-dropdown button {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: #111827;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            background: transparent;
            width: 100%;
            text-align: left;
            border: none;
            cursor: pointer;
        }

        .abg-host-dropdown a:hover,
        .abg-host-dropdown button:hover {
            background: #F8FAFC;
            color: #0F172A;
        }

        .abg-host-divider {
            height: 1px;
            background: #F1F5F9;
            margin: 8px 6px;
            border-radius: 1px;
        }

        .abg-host-note {
            display: flex;
            gap: 10px;
            padding: 8px 10px;
            font-size: 13px;
            color: #475569;
            align-items: flex-start;
        }

        .abg-host-note img {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            object-fit: cover;
        }

        .abg-host-dropdown a,
        .abg-host-dropdown button {
            font-size: 14px;
            padding: 9px 10px;
        }

        /* ════════════════════════════════════════════
   CATEGORY STRIP — Premium scrollable tabs
   ════════════════════════════════════════════ */
        <style>

        /* ── CATEGORY STRIP ── */
        .abg-strip {
            background: #fff;
            border-bottom: 1px solid rgba(40, 108, 210, 0.06);
            padding: 0 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            overflow-x: auto;
            scrollbar-width: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .abg-strip::-webkit-scrollbar {
            display: none;
        }

        .abg-strip-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 16px 24px;
            cursor: pointer;
            flex-shrink: 0;
            border-bottom: 3px solid transparent;
            color: #9AA0AB;
            transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            text-decoration: none;
        }

        .abg-strip-item:hover {
            color: #1A1A2E;
            border-bottom-color: #E0E6F0;
        }

        .abg-strip-item.active {
            color: #286CD2;
            border-bottom-color: #286CD2;
        }

        .abg-strip-item svg {
            flex-shrink: 0;
            transition: transform 0.2s;
        }

        .abg-strip-item:hover svg {
            transform: scale(1.05);
        }

        .abg-strip-label {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .abg-strip-item:hover {
            color: #1A1A2E;
            border-bottom-color: #E0E4EB;
        }

        .abg-strip-item.active {
            color: #1A1A2E;
            border-bottom-color: #1A1A2E;
        }

        .abg-strip-label {
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ════════════════════════════════════════════
   MAIN CONTENT
   ════════════════════════════════════════════ */

        .abg-body {
            max-width: 1320px;
            margin: 0 auto;
            padding: 48px 48px 80px;
        }

        /* ── BODY ── */
        .abg-body {
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 40px 60px;
        }

        /* AIRBNB-STYLE SEARCH HERO — centered search pill below the nav */
        .abg-search-hero {
            display: flex;
            justify-content: center;
            padding: 28px 48px;
            background: #fff;
            border-bottom: 1px solid rgba(16, 24, 40, 0.04);

            /* ── HERO ── */
            .abg-hero {
                border-radius: 24px;
                margin-bottom: 40px;
                background: linear-gradient(120deg, #1A3A6E 0%, #286CD2 55%, #61B2F0 100%);
                padding: 40px 48px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 28px;
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 32px rgba(40, 108, 210, 0.28);
            }

            .abg-search-hero .abg-search-pill {
                max-width: 920px;
                width: 100%;
                display: flex;
                align-items: center;
                border-radius: 44px;
                background: #fff;
                box-shadow: 0 10px 36px rgba(16, 24, 40, 0.08);
                padding: 10px;
                gap: 0;
            }

            .abg-search-hero .abg-search-seg {
                flex: 1;
                padding: 12px 18px;
                border-right: 1px solid #EEF2F7;
            }

            .abg-search-hero .abg-search-seg:last-of-type {
                border-right: none;
            }

            .abg-search-hero .abg-search-label {
                font-size: 12px;
                color: #6B7280;
                font-weight: 600;
                display: block;
                margin-bottom: 6px;
            }

            .abg-search-hero .abg-search-input {
                font-size: 15px;
                color: #111827;
                width: 100%;
                padding: 3px 0;
                background: transparent;
                border: none;
                outline: none;
            }

            .abg-search-hero .abg-search-btn {
                width: 44px;
                height: 44px;
                margin: 0 8px 0 12px;
                border-radius: 50%;
                background: #ff385c;
                box-shadow: 0 8px 20px rgba(255, 56, 92, 0.18);
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                flex-shrink: 0;
            }

            .abg-hero::after {
                content: '';
                position: absolute;
                right: 120px;
                bottom: -100px;
                width: 240px;
                height: 240px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.04);
                pointer-events: none;
            }

            .abg-hero-text {
                position: relative;
                z-index: 1;
            }

            .abg-hero-eyebrow {
                display: inline-block;
                font-size: 10.5px;
                font-weight: 700;
                letter-spacing: 1px;
                text-transform: uppercase;
                color: rgba(255, 255, 255, 0.65);
                margin-bottom: 10px;
                background: rgba(255, 255, 255, 0.1);
                padding: 4px 12px;
                border-radius: 20px;
            }

            .abg-hero h1 {
                font-size: 28px;
                font-weight: 900;
                color: #fff;
                line-height: 1.2;
                letter-spacing: -0.6px;
                margin-bottom: 8px;
            }

            .abg-hero p {
                font-size: 14px;
                color: rgba(255, 255, 255, 0.72);
                line-height: 1.55;
                max-width: 420px;
            }

            .abg-hero-actions {
                display: flex;
                gap: 10px;
                flex-shrink: 0;
                position: relative;
                z-index: 1;
                flex-direction: column;
                align-items: flex-end;
            }

            .abg-btn-white {
                height: 48px;
                padding: 0 28px;
                background: #fff;
                color: #286CD2;
                border-radius: 14px;
                font-size: 14.5px;
                font-weight: 800;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 9px;
                border: none;
                cursor: pointer;
                white-space: nowrap;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.14);
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            }

            .abg-btn-white:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
            }

            .abg-btn-white:active {
                transform: translateY(0);
            }

            .abg-btn-outline-white {
                height: 48px;
                padding: 0 28px;
                background: rgba(255, 255, 255, 0.15);
                color: #fff;
                border-radius: 14px;
                font-size: 14.5px;
                font-weight: 700;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 9px;
                border: 1.5px solid rgba(255, 255, 255, 0.35);
                cursor: pointer;
                white-space: nowrap;
                backdrop-filter: blur(6px);
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            }

            .abg-btn-outline-white:hover {
                background: rgba(255, 255, 255, 0.22);
                border-color: rgba(255, 255, 255, 0.55);
                transform: translateY(-2px);
            }

            /* STATS ROW — Premium stat cards */
            /* ── STATS ── */
            .abg-stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 18px;
                margin-bottom: 48px;
            }

            .abg-stat {
                background: #fff;
                border-radius: 20px;
                border: 1px solid #EAECF0;
                padding: 26px 24px 22px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
                position: relative;
                overflow: hidden;
            }

            .abg-stat:hover {
                box-shadow: 0 12px 32px rgba(40, 108, 210, 0.12);
                transform: translateY(-4px);
                border-color: #D7E8F3;
            }

            .abg-stat-glow {
                position: absolute;
                right: -24px;
                top: -24px;
                width: 110px;
                height: 110px;
                border-radius: 50%;
                opacity: 0.09;
            }

            .abg-stat-icon {
                width: 44px;
                height: 44px;
                border-radius: 13px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 14px;
                width: 38px;
                height: 38px;
                border-radius: 11px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12px;
            }

            .abg-stat-num {
                font-size: 36px;
                font-weight: 900;
                line-height: 1;
                letter-spacing: -1.5px;
                color: #1A1A2E;
                margin-bottom: 6px;
            }

            .abg-stat-label {
                font-size: 11px;
                font-weight: 700;
                color: #9AA0AB;
                text-transform: uppercase;
                letter-spacing: 0.6px;
                margin-bottom: 3px;
            }

            .abg-stat-sub {
                font-size: 12px;
                color: #C0C7D0;
                font-weight: 500;
            }

            /* ── SECTION HEADERS ── */
            .abg-section-row {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                margin-bottom: 22px;
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                margin-bottom: 20px;
            }

            .abg-section-title {
                font-size: 21px;
                font-weight: 800;
                color: #1A1A2E;
                letter-spacing: -0.4px;
            }

            .abg-section-sub {
                font-size: 13.5px;
                color: #9AA0AB;
                margin-top: 3px;
                font-weight: 500;
            }

            .abg-see-all {
                font-size: 14px;
                font-weight: 700;
                color: #286CD2;
                text-decoration: none;
                padding: 8px 16px;
                border: 1.5px solid #D7E8F3;
                border-radius: 12px;
                transition: all 0.15s;
                font-size: 13px;
                font-weight: 700;
                color: #286CD2;
                text-decoration: none;
                padding: 6px 14px;
                border: 1.5px solid #D7E8F3;
                border-radius: 10px;
                transition: background 0.15s;
            }

            .abg-see-all:hover {
                background: #EEF5FF;
                border-color: #286CD2;
            }

            /* RESERVATION FILTER BAR */
            .abg-filter-bar {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .abg-filter-search {
                flex: 1;
                min-width: 200px;
                position: relative;
            }

            /* ── FILTER BAR ── */
            .abg-filter-bar {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 18px;
                flex-wrap: wrap;
            }

            .abg-filter-search {
                flex: 1;
                min-width: 180px;
                position: relative;
            }

            .abg-filter-search input {
                width: 100%;
                height: 44px;
                border: 1.5px solid #E8ECF0;
                border-radius: 14px;
                padding: 0 16px 0 42px;
                font-size: 13.5px;
                font-family: inherit;
                color: #1A1A2E;
                background: #F9FAFB;
                outline: none;
                transition: all 0.2s;
            }

            .abg-filter-search input:focus {
                border-color: #286CD2;
                background: #fff;
                box-shadow: 0 0 0 3px rgba(40, 108, 210, 0.08);
            }

            .abg-filter-search input::placeholder {
                color: #B8C2D4;
                font-weight: 500;
            }

            .abg-filter-search .fs-icon {
                position: absolute;
                left: 14px;
                top: 50%;
                transform: translateY(-50%);
                color: #B8C2D4;
            }

            .abg-filter-select {
                height: 44px;
                padding: 0 16px;
                border: 1.5px solid #E8ECF0;
                border-radius: 14px;
                font-size: 13.5px;
                font-family: inherit;
                color: #374151;
                background: #F9FAFB;
                outline: none;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.2s;
            }

            .abg-filter-select:focus {
                border-color: #286CD2;
                box-shadow: 0 0 0 3px rgba(40, 108, 210, 0.08);
            }

            /* RESERVATION CARDS — Premium horizontal cards */
            .abg-resv-list {
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            /* ── RESERVATION CARDS ── */
            .abg-resv-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .abg-resv-card {
                display: flex;
                align-items: center;
                gap: 18px;
                background: #fff;
                border: 1px solid #EAECF0;
                border-radius: 18px;
                padding: 18px 22px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            }

            .abg-resv-card:hover {
                box-shadow: 0 10px 28px rgba(40, 108, 210, 0.11);
                border-color: #D7E8F3;
                transform: translateY(-2px);
            }

            .abg-resv-card:hover {
                box-shadow: 0 6px 24px rgba(40, 108, 210, 0.09);
                border-color: #D7E8F3;
                transform: translateY(-1px);
            }

            .abg-resv-thumb {
                width: 62px;
                height: 62px;
                border-radius: 16px;
                flex-shrink: 0;
                background: linear-gradient(135deg, #286CD2 0%, #61B2F0 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 20px;
                font-weight: 900;
                box-shadow: 0 6px 18px rgba(40, 108, 210, 0.32);
            }

            .abg-resv-info {
                flex: 1;
                min-width: 0;
            }

            .abg-resv-title {
                font-size: 15px;
                font-weight: 700;
                color: #1A1A2E;
                margin-bottom: 5px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .abg-resv-meta {
                font-size: 13px;
                color: #9AA0AB;
                font-weight: 500;
            }

            .abg-resv-meta span {
                color: #5A6475;
                font-weight: 600;
            }

            .abg-resv-right {
                text-align: right;
                flex-shrink: 0;
            }

            .abg-pill {
                display: inline-block;
                font-size: 11.5px;
                font-weight: 700;
                letter-spacing: 0.4px;
                text-transform: uppercase;
                padding: 5px 13px;
                border-radius: 22px;
                margin-bottom: 7px;
            }

            .abg-pill-approved {
                background: #ECFDF5;
                color: #065F46;
            }

            .abg-pill-pending {
                background: #FFFBEB;
                color: #92400E;
            }

            .abg-pill-rejected {
                background: #FEF2F2;
                color: #991B1B;
            }

            .abg-pill-cancelled {
                background: #F4F4F4;
                color: #6B7280;
            }

            .abg-resv-ref {
                font-size: 12px;
                color: #C0C7D0;
                font-weight: 700;
                letter-spacing: 0.3px;
            }

            /* ── EMPTY STATE ── */
            .abg-empty {
                text-align: center;
                padding: 60px 28px;
                border: 2px dashed #E8ECF0;
                border-radius: 20px;
                background: linear-gradient(160deg, #F9FBFF 0%, #F0F6FF 100%);
            }

            .abg-empty-icon {
                width: 80px;
                height: 80px;
                border-radius: 22px;
                background: linear-gradient(135deg, #EEF5FF, #D7E8F3);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 18px;
                color: #286CD2;
                box-shadow: 0 6px 20px rgba(40, 108, 210, 0.14);
            }

            .abg-empty-title {
                font-size: 16px;
                font-weight: 800;
                color: #1A1A2E;
                margin-bottom: 8px;
            }

            .abg-empty-sub {
                font-size: 13.5px;
                color: #9AA0AB;
                margin-bottom: 26px;
                line-height: 1.6;
            }

            .abg-btn-primary {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 26px;
                background: linear-gradient(135deg, #286CD2, #61B2F0);
                color: #fff;
                border-radius: 14px;
                font-size: 14px;
                font-weight: 700;
                text-decoration: none;
                border: none;
                cursor: pointer;
                box-shadow: 0 6px 18px rgba(40, 108, 210, 0.32);
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            }

            .abg-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(40, 108, 210, 0.4);
            }

            .abg-btn-primary:active {
                transform: translateY(0);
            }

            /* TWO-COL GRID */
            .abg-two-col {
                display: grid;
                grid-template-columns: 1fr 360px;
                gap: 28px;
                margin-top: 48px;
            }

            /* ── TWO-COL ── */
            .abg-two-col {
                display: grid;
                grid-template-columns: 1fr 340px;
                gap: 24px;
                margin-top: 40px;
            }

            /* ── BECOME A LANDLORD CARD ── */
            .abg-host-card {
                border-radius: 22px;
                padding: 28px 26px;
                background: linear-gradient(145deg, #1A2A40 0%, #2C3E55 60%, #5E6968 100%);
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 30px rgba(26, 42, 64, 0.35);
            }

            .abg-host-card::before {
                content: '';
                position: absolute;
                right: -30px;
                top: -30px;
                width: 140px;
                height: 140px;
                border-radius: 50%;
                background: rgba(97, 178, 240, 0.12);
            }

            .abg-host-card::after {
                content: '';
                position: absolute;
                left: -20px;
                bottom: -30px;
                width: 110px;
                height: 110px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.04);
            }

            .abg-host-badge {
                display: inline-block;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 1px;
                text-transform: uppercase;
                color: #61B2F0;
                background: rgba(97, 178, 240, 0.15);
                padding: 4px 10px;
                border-radius: 20px;
                margin-bottom: 12px;
            }

            .abg-host-title {
                font-size: 18px;
                font-weight: 900;
                color: #fff;
                margin-bottom: 8px;
                line-height: 1.3;
                letter-spacing: -0.3px;
                position: relative;
                z-index: 1;
            }

            .abg-host-sub {
                font-size: 12.5px;
                color: rgba(255, 255, 255, 0.58);
                line-height: 1.6;
                margin-bottom: 20px;
                position: relative;
                z-index: 1;
            }

            .abg-host-perks {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 22px;
                position: relative;
                z-index: 1;
            }

            .abg-host-perk {
                display: flex;
                align-items: center;
                gap: 9px;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.75);
                font-weight: 500;
            }

            .abg-host-perk-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #61B2F0;
                flex-shrink: 0;
            }

            .abg-btn-host-card {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 11px 22px;
                background: #fff;
                color: #1A2A40;
                border-radius: 12px;
                font-size: 13.5px;
                font-weight: 800;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-family: inherit;
                position: relative;
                z-index: 1;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
                transition: opacity 0.15s, transform 0.15s;
            }

            .abg-btn-host-card:hover {
                opacity: 0.92;
                transform: translateY(-1px);
            }

            /* ── QUICK ACTIONS ── */
            .abg-qa-card {
                background: #fff;
                border: 1px solid #EAECF0;
                border-radius: 20px;
                padding: 26px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                margin-bottom: 20px;
                background: #fff;
                border: 1px solid #EAECF0;
                border-radius: 18px;
                padding: 22px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                margin-bottom: 18px;
            }

            .abg-qa-title {
                font-size: 15px;
                font-weight: 800;
                color: #1A1A2E;
                margin-bottom: 5px;
            }

            .abg-qa-sub {
                font-size: 12.5px;
                color: #9AA0AB;
                margin-bottom: 18px;
                font-weight: 500;
            }

            .abg-qa-item {
                display: flex;
                align-items: center;
                gap: 13px;
                padding: 12px 16px;
                border-radius: 13px;
                border: 1px solid #F2F4F7;
                text-decoration: none;
                color: #374151;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 9px;
                background: #FAFBFC;
                transition: all 0.15s cubic-bezier(0.2, 0, 0, 1);
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 11px 14px;
                border-radius: 12px;
                border: 1px solid #F0F2F5;
                text-decoration: none;
                color: #374151;
                font-size: 13.5px;
                font-weight: 600;
                margin-bottom: 8px;
                background: #FAFBFC;
                transition: all 0.15s;
            }

            .abg-qa-item:last-child {
                margin-bottom: 0;
            }

            .abg-qa-item:hover {
                border-color: #D7E8F3;
                background: #EEF5FF;
                color: #286CD2;
                transform: translateX(3px);
            }

            .abg-qa-item:hover .abg-qa-icon {
                background: linear-gradient(135deg, #286CD2, #61B2F0);
                color: #fff;
                box-shadow: 0 4px 12px rgba(40, 108, 210, 0.28);
            }

            .abg-qa-item:hover {
                border-color: #D7E8F3;
                background: #F0F6FF;
                color: #286CD2;
                transform: translateX(2px);
            }

            .abg-qa-item:hover .abg-qa-icon {
                background: #286CD2;
                color: #fff;
            }

            .abg-qa-icon {
                width: 40px;
                height: 40px;
                border-radius: 11px;
                background: #F0F2F5;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6B7280;
                flex-shrink: 0;
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            }

            .abg-qa-arrow {
                margin-left: auto;
                color: #D0D5DD;
                flex-shrink: 0;
                transition: transform 0.2s;
            }

            .abg-qa-item:hover .abg-qa-arrow {
                transform: translateX(2px);
                color: #286CD2;
            }

            /* ACTIVITY FEED */
            .abg-activity {
                background: #fff;
                border: 1px solid #EAECF0;
                border-radius: 20px;
                padding: 26px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }

            /* ── ACTIVITY ── */
            .abg-activity {
                background: #fff;
                border: 1px solid #EAECF0;
                border-radius: 18px;
                padding: 22px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            }

            .abg-act-item {
                display: flex;
                align-items: flex-start;
                gap: 13px;
                padding: 12px 0;
                border-bottom: 1px solid #F4F6FA;
            }

            .abg-act-item:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .abg-act-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                flex-shrink: 0;
                margin-top: 4px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .abg-act-text {
                flex: 1;
                font-size: 13px;
                color: #374151;
                line-height: 1.6;
                font-weight: 500;
            }

            .abg-act-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                flex-shrink: 0;
                margin-top: 5px;
            }

            .abg-act-text {
                flex: 1;
                font-size: 12.5px;
                color: #374151;
                line-height: 1.5;
                font-weight: 500;
            }

            .abg-act-text strong {
                color: #1A1A2E;
                font-weight: 700;
            }

            .abg-act-time {
                font-size: 11.5px;
                color: #C0C7D0;
                font-weight: 600;
                white-space: nowrap;
                flex-shrink: 0;
            }

            /* BECOME HOST CARD — Premium dark gradient card */
            .abg-host-card {
                border-radius: 24px;
                padding: 32px 28px;
                background: linear-gradient(145deg, #1A2A40 0%, #2C3E55 40%, #5E6968 100%);
                position: relative;
                overflow: hidden;
                box-shadow: 0 12px 40px rgba(26, 42, 64, 0.38);
            }

            .abg-host-card::before {
                content: '';
                position: absolute;
                right: -40px;
                top: -40px;
                width: 160px;
                height: 160px;
                border-radius: 50%;
                background: rgba(97, 178, 240, 0.14);
            }

            .abg-host-card::after {
                content: '';
                position: absolute;
                left: -30px;
                bottom: -40px;
                width: 130px;
                height: 130px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.05);
            }

            .abg-host-badge {
                display: inline-block;
                font-size: 10.5px;
                font-weight: 800;
                letter-spacing: 1.1px;
                text-transform: uppercase;
                color: #61B2F0;
                background: rgba(97, 178, 240, 0.16);
                padding: 5px 12px;
                border-radius: 22px;
                margin-bottom: 14px;
                backdrop-filter: blur(8px);
            }

            .abg-host-title {
                font-size: 20px;
                font-weight: 900;
                color: #fff;
                margin-bottom: 10px;
                line-height: 1.3;
                letter-spacing: -0.4px;
                position: relative;
                z-index: 1;
            }

            .abg-host-sub {
                font-size: 13px;
                color: rgba(255, 255, 255, 0.62);
                line-height: 1.7;
                margin-bottom: 24px;
                position: relative;
                z-index: 1;
                font-weight: 500;
            }

            .abg-host-perks {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-bottom: 26px;
                position: relative;
                z-index: 1;
            }

            .abg-host-perk {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 12.5px;
                color: rgba(255, 255, 255, 0.78);
                font-weight: 500;
                line-height: 1.5;
            }

            .abg-host-perk-dot {
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background: #61B2F0;
                flex-shrink: 0;
                box-shadow: 0 2px 6px rgba(97, 178, 240, 0.3);
            }

            .abg-btn-host-card {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: #fff;
                color: #1A2A40;
                border-radius: 14px;
                font-size: 14px;
                font-weight: 800;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-family: inherit;
                position: relative;
                z-index: 1;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
                transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
            }

            .abg-btn-host-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.22);
            }

            .abg-btn-host-card:active {
                transform: translateY(0);
            }

            /* ── RESPONSIVE ── */
            @media (max-width: 1100px) {
                .abg-stats {
                    grid-template-columns: repeat(2, 1fr);
                }

                .abg-two-col {
                    grid-template-columns: 1fr;
                }

                .abg-nav {
                    padding: 0 32px;
                }

                .abg-body {
                    padding: 36px 32px 64px;
                }

                .abg-strip {
                    padding: 0 32px;
                }
            }

            @media (max-width: 768px) {
                .abg-nav {
                    padding: 0 20px;
                    height: 72px;
                    gap: 16px;
                }

                .abg-logo-text {
                    font-size: 15px;
                }

                .abg-search-pill {
                    display: none;
                }

                .abg-body {
                    padding: 28px 20px 56px;
                }

                .abg-hero {
                    padding: 36px 28px;
                    flex-direction: column;
                    align-items: flex-start;
                }

                .abg-hero h1 {
                    font-size: 26px;
                }

                .abg-hero-actions {
                    flex-direction: row;
                    align-items: flex-start;
                    width: 100%;
                    gap: 8px;
                }

                .abg-btn-white,
                .abg-btn-outline-white {
                    flex: 1;
                    justify-content: center;
                }

                .abg-strip {
                    padding: 0 20px;
                }

                .abg-stats {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 14px;
                }

                .abg-stat {
                    padding: 20px 18px 16px;
                }

                .abg-stat-num {
                    font-size: 28px;
                }

                .abg-strip {
                    padding: 0 20px;
                }

                .abg-body {
                    padding: 24px 20px 48px;
                }

                .abg-hero {
                    padding: 28px 24px;
                    flex-direction: column;
                    align-items: flex-start;
                }

                .abg-hero h1 {
                    font-size: 22px;
                }

                .abg-hero-actions {
                    flex-direction: row;
                    align-items: flex-start;
                    width: 100%;
                }
            }

            @media (max-width: 480px) {
                .abg-stats {
                    grid-template-columns: 1fr 1fr;
                }

                .abg-btn-host {
                    display: none;
                }

                .abg-resv-card {
                    flex-direction: column;
                    align-items: flex-start;
                    text-align: left;
                }

                .abg-resv-right {
                    width: 100%;
                    text-align: left;
                }

                .abg-hero h1 {
                    font-size: 22px;
                }

                .abg-section-title {
                    font-size: 18px;
                }
            }
    </style>

    {{-- ══════════════════════════════════════════
    TOP NAV — Sticky Header
    ══════════════════════════════════════════ --}}
    <nav class="abg-nav">
        <a href="{{ route('dashboard') }}" class="abg-logo">
            <div class="abg-logo-icon">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </div>
            <span class="abg-logo-text">Abanganan<span>Hub</span></span>
        </a>

        <!-- search pill moved below nav into .abg-search-hero for Airbnb-style layout -->

        <div class="abg-nav-right">
            <div class="abg-host-wrap">
                <button type="button" id="abg-host-btn" class="abg-btn-host" aria-haspopup="true" aria-expanded="false">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Become a Landlord
                </button>
                <div class="abg-host-dropdown" id="abg-host-menu" role="menu" aria-hidden="true">
                    <a href="#wishlists" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 10-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z" />
                        </svg>
                        Wishlists
                    </a>
                    <a href="#trips" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7" />
                        </svg>
                        Trips
                    </a>
                    <a href="#messages" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                        </svg>
                        Messages
                    </a>
                    <a href="{{ route('profile.edit') }}" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profile
                    </a>
                    <div class="abg-host-divider"></div>
                    <a href="#notifications" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L3 17h5" />
                        </svg>
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
                            <div style="font-size:13px;color:#64748B;">It's easy to start hosting and earn extra income.
                            </div>
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
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </a>

            <div class="abg-avatar-wrap">
                <div class="abg-avatar" id="abg-avatar-btn" role="button" tabindex="0" aria-haspopup="true"
                    aria-expanded="false">
                    {{ strtoupper(substr(Auth::user()?->first_name ?? 'T', 0, 1)) }}
                </div>
                <div class="abg-dropdown" id="abg-avatar-menu" role="menu">
                    <div class="abg-dd-head">
                        <div class="abg-dd-name">
                            {{ trim((Auth::user()?->first_name ?? '') . ' ' . (Auth::user()?->last_name ?? '')) ?: 'Tenant' }}
                        </div>
                        <div class="abg-dd-email">{{ Auth::user()?->email }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="abg-dd-link" role="menuitem">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Account Settings
                    </a>
                    <a href="#reservations" class="abg-dd-link" role="menuitem">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        My Reservations
                    </a>
                    <a href="#messages" class="abg-dd-link" role="menuitem">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        Messages
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="abg-dd-btn danger" role="menuitem">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
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

    <div class="abg-search-hero">
        <div class="abg-search-pill" role="search">
            <div class="abg-search-seg">
                <span class="abg-search-label">Where</span>
                <input type="text" class="abg-search-input" placeholder="Search destinations">
            </div>
            <div class="abg-search-seg" style="flex:0.6;">
                <span class="abg-search-label">When</span>
                <input type="text" class="abg-search-input" placeholder="Add dates">
            </div>
            <div class="abg-search-seg" style="flex:0.6;">
                <span class="abg-search-label">Who</span>
                <input type="text" class="abg-search-input" placeholder="Add guests">
            </div>
            <button class="abg-search-btn" aria-label="Search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
    CATEGORY STRIP
    ══════════════════════════════════════════ --}}
    <div class="abg-strip">
        <a href="#" class="abg-strip-item active">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="abg-strip-label">All</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M5 3h14a2 2 0 012 2v4H3V5a2 2 0 012-2zm0 0v16m14-16v16M3 9h18M9 9v10m6-10v10" />
            </svg>
            <span class="abg-strip-label">Bedspace</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="10" width="18" height="11" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l9-7 9 7" />
            </svg>
            <span class="abg-strip-label">Room</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="2" y="7" width="20" height="14" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
            </svg>
            <span class="abg-strip-label">Apartment</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V8l8-5 8 5v13M9 21v-6h6v6" />
            </svg>
            <span class="abg-strip-label">House</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            <span class="abg-strip-label">Saved</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <span class="abg-strip-label">Near Me</span>
        </a>
        <a href="#" class="abg-strip-item">
            <svg width="23" height="23" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
            <span class="abg-strip-label">Verified</span>
        </a>
    </div>
    {{-- ══════════════════════════════════════════
    CATEGORY STRIP
    ══════════════════════════════════════════ --}}
    <div class="abg-strip">
        <a href="{{ route('properties.index') }}" class="abg-strip-item active" data-type="all">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="abg-strip-label">All</span>
        </a>
        <a href="{{ route('properties.index', ['type' => 'Bedspace']) }}" class="abg-strip-item" data-type="Bedspace">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M5 3h14a2 2 0 012 2v4H3V5a2 2 0 012-2zm0 0v16m14-16v16M3 9h18M9 9v10m6-10v10" />
            </svg>
            <span class="abg-strip-label">Bedspace</span>
        </a>
        <a href="{{ route('properties.index', ['type' => 'Room']) }}" class="abg-strip-item" data-type="Room">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="10" width="18" height="11" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l9-7 9 7" />
            </svg>
            <span class="abg-strip-label">Room</span>
        </a>
        <a href="{{ route('properties.index', ['type' => 'Apartment']) }}" class="abg-strip-item" data-type="Apartment">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="2" y="7" width="20" height="14" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
            </svg>
            <span class="abg-strip-label">Apartment</span>
        </a>
        <a href="{{ route('properties.index', ['type' => 'House']) }}" class="abg-strip-item" data-type="House">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V8l8-5 8 5v13M9 21v-6h6v6" />
            </svg>
            <span class="abg-strip-label">House</span>
        </a>
        <a href="{{ route('favorites.index') }}" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            <span class="abg-strip-label">Saved</span>
        </a>
        <a href="{{ route('properties.index', ['verified' => true]) }}" class="abg-strip-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
            <span class="abg-strip-label">Verified</span>
        </a>
    </div>

    {{-- ══════════════════════════════════════════
    MAIN BODY
    ══════════════════════════════════════════ --}}
    <div class="abg-body">

        {{-- hero removed; replaced with Airbnb-style centered search above --}}
        {{-- HERO --}}
        <div class="abg-hero">
            <div class="abg-hero-text">
                <div class="abg-hero-eyebrow">🏡 Cebu's Verified Rental Platform</div>
                <h1>Welcome back,<br>{{ auth()->user()->first_name }}!</h1>
                <p>Find safe, verified accommodations across Cebu. Every listing reviewed. Every landlord checked.</p>
            </div>
            <div class="abg-hero-actions">
                <a href="{{ route('properties.index') }}" class="abg-btn-white">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Browse Properties
                </a>
                <a href="{{ route('reservations.index') }}" class="abg-btn-outline-white">
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
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="abg-stat-label">Upcoming Stays</div>
                <div class="abg-stat-num" style="color:#286CD2;">{{ $upcomingCount ?? 0 }}</div>
                <div class="abg-stat-sub">Active reservations</div>
            </div>
            {{-- Messages --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#7C3AED;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#F3EEFF,#EDE0FF);">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="#7C3AED" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </div>
                <div class="abg-stat-label">Messages</div>
                <div class="abg-stat-num" style="color:#7C3AED;">{{ $messagesCount ?? 0 }}</div>
                <div class="abg-stat-sub">Unread threads</div>
            </div>
            {{-- Saved --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#059669;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#ECFDF5,#D1FAE5);">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>
                <div class="abg-stat-label">Saved Listings</div>
                <div class="abg-stat-num" style="color:#059669;">{{ $savedCount ?? 0 }}</div>
                <div class="abg-stat-sub">In your favorites</div>
            </div>
            {{-- Support --}}
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#BD5434;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#FFF4EE,#FFE5D8);">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="#BD5434" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18.364 5.636l-3.536 3.536m0 0A7.5 7.5 0 1115.75 21a7.5 7.5 0 00-1.25-10.25M14.828 8.485L12 11.314" />
                    </svg>
                </div>
                <div class="abg-stat-label">Support Tickets</div>
                <div class="abg-stat-num" style="color:#BD5434;">{{ $supportCount ?? 0 }}</div>
                <div class="abg-stat-sub">Open cases</div>
            </div>
        </div>
        {{-- STATS --}}
        <div class="abg-stats">
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#286CD2;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#EEF5FF,#D7E8F3);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="abg-stat-label">Upcoming Stays</div>
                <div class="abg-stat-num" style="color:#286CD2;">{{ $upcomingCount }}</div>
                <div class="abg-stat-sub">Active reservations</div>
            </div>
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#7C3AED;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#F3EEFF,#EDE0FF);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#7C3AED" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </div>
                <div class="abg-stat-label">Messages</div>
                <div class="abg-stat-num" style="color:#7C3AED;">{{ $messagesCount }}</div>
                <div class="abg-stat-sub">Unread threads</div>
            </div>
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#059669;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#ECFDF5,#D1FAE5);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>
                <div class="abg-stat-label">Saved Listings</div>
                <div class="abg-stat-num" style="color:#059669;">{{ $savedCount }}</div>
                <div class="abg-stat-sub">In your favorites</div>
            </div>
            <div class="abg-stat">
                <div class="abg-stat-glow" style="background:#BD5434;"></div>
                <div class="abg-stat-icon" style="background:linear-gradient(135deg,#FFF4EE,#FFE5D8);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#BD5434" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                    </svg>
                </div>
                <div class="abg-stat-label">Open Reports</div>
                <div class="abg-stat-num" style="color:#BD5434;">{{ $reportsCount }}</div>
                <div class="abg-stat-sub">Pending resolution</div>
            </div>
        </div>

        {{-- RESERVATIONS --}}
        <div id="reservations">
            <div class="abg-section-row">
                <div>
                    <div class="abg-section-title">My Reservations</div>
                    <div class="abg-section-sub">Track your current and upcoming bookings in Cebu</div>
                </div>
                <a href="{{ route('reservations.index') }}" class="abg-see-all">View all</a>
            </div>

            <div class="abg-filter-bar">
                <div class="abg-filter-search">
                    <svg class="fs-icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
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
            <div class="abg-filter-bar">
                <div class="abg-filter-search">
                    <svg class="fs-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" id="reservation-search" placeholder="Search by property or reference…">
                </div>
                <select id="reservation-filter" class="abg-filter-select">
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
                        $pillClass = match ($reservation->reservation_status) {
                            'Approved' => 'abg-pill-approved',
                            'Pending' => 'abg-pill-pending',
                            'Rejected' => 'abg-pill-rejected',
                            default => 'abg-pill-cancelled',
                        };
                    @endphp
                    <div class="abg-resv-card reservation-row" data-status="{{ $reservation->reservation_status }}">
                        <div class="abg-resv-thumb">
                            {{ strtoupper(substr($reservation->property->title ?? 'P', 0, 1)) }}
                        </div>
                        <div class="abg-resv-info">
                            <div class="abg-resv-title property-title">{{ $reservation->property->title ?? 'Property' }}
                            </div>
                            <div class="abg-resv-meta">
                                Check-in: <span>{{ $reservation->reservation_date?->format('M d, Y') ?? 'TBD' }}</span>
                                @if($reservation->property->address ?? false)
                                    &nbsp;·&nbsp; <span>{{ Str::limit($reservation->property->address, 42) }}</span>
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
                            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="abg-empty-title">No reservations yet</div>
                        <div class="abg-empty-sub">Browse verified properties across Cebu and make your first booking today.
                        </div>
                        <a href="#" class="abg-btn-primary">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Browse Properties
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="abg-resv-list" id="reservations-list">
            @forelse($reservations as $reservation)
                @php
                    $pillClass = match ($reservation->reservation_status) {
                        'Approved' => 'abg-pill-approved',
                        'Pending' => 'abg-pill-pending',
                        'Rejected' => 'abg-pill-rejected',
                        default => 'abg-pill-cancelled',
                    };
                @endphp
                <div class="abg-resv-card reservation-row" data-status="{{ $reservation->reservation_status }}">
                    <div class="abg-resv-thumb">
                        {{ strtoupper(substr($reservation->property->title ?? 'P', 0, 1)) }}
                    </div>
                    <div class="abg-resv-info">
                        <div class="abg-resv-title property-title">
                            {{ $reservation->property->title ?? 'Property' }}
                        </div>
                        <div class="abg-resv-meta">
                            Check-in: <span>{{ $reservation->reservation_date?->format('M d, Y') ?? 'TBD' }}</span>
                            @if($reservation->property->address ?? false)
                                &nbsp;·&nbsp;
                                <span>{{ Str::limit($reservation->property->address, 40) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="abg-resv-right">
                        <div class="abg-pill {{ $pillClass }}">{{ $reservation->reservation_status }}</div>
                        {{-- Fixed: reservation_id not id --}}
                        <div class="abg-resv-ref reference-id">#R{{ $reservation->reservation_id }}</div>
                    </div>
                </div>
            @empty
                <div class="abg-empty">
                    <div class="abg-empty-icon">
                        <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="abg-empty-title">No reservations yet</div>
                    <div class="abg-empty-sub">Browse verified properties across Cebu and make your first booking today.
                    </div>
                    <a href="{{ route('properties.index') }}" class="abg-btn-primary">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Browse Properties
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    {{-- TWO-COL --}}
    <div class="abg-two-col">

        {{-- LEFT: Quick Actions + Activity --}}
        <div>
            <div class="abg-section-row" style="margin-bottom:22px;">
                <div>
                    <div class="abg-section-title">Quick Actions</div>
                    <div class="abg-section-sub">Shortcuts to your most-used features</div>
                </div>
            </div>
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
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        Search Properties
                        <svg class="abg-qa-arrow" width="15" height="15" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="#" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        View Saved Favorites
                        <svg class="abg-qa-arrow" width="15" height="15" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="#messages" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        Open Messages
                        <svg class="abg-qa-arrow" width="15" height="15" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        Account Settings
                        <svg class="abg-qa-arrow" width="15" height="15" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="#" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M18.364 5.636l-3.536 3.536m0 0A7.5 7.5 0 1115.75 21a7.5 7.5 0 00-1.25-10.25" />
                            </svg>
                        </div>
                        Contact Support
                        <svg class="abg-qa-arrow" width="15" height="15" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
                <div class="abg-qa-card">
                    <a href="{{ route('properties.index') }}" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        Search Properties
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="{{ route('favorites.index') }}" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        View Saved Favorites
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="{{ route('conversations.index') }}" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        Open Messages
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="abg-qa-item">
                        <div class="abg-qa-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        Account Settings
                        <svg class="abg-qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

                {{-- Activity --}}
                <div style="margin-top:28px;">
                    <div class="abg-section-row" style="margin-bottom:16px;">
                        <div>
                            <div class="abg-section-title" style="font-size:17px;">Recent Activity</div>
                            <div class="abg-section-sub">Latest updates on your account</div>
                        </div>
                    </div>
                    <div class="abg-activity">
                        {{-- Activity --}}
                        <div style="margin-top:24px;">
                            <div class="abg-section-row" style="margin-bottom:14px;">
                                <div>
                                    <div class="abg-section-title" style="font-size:16px;">Recent Activity</div>
                                    <div class="abg-section-sub">Latest updates on your account</div>
                                </div>
                            </div>
                            <div class="abg-activity">
                                @forelse($recentActivity as $activity)
                                    <div class="abg-act-item">
                                        <div class="abg-act-dot" style="background:#286CD2;"></div>
                                        <div class="abg-act-text">{{ $activity->message }}</div>
                                        <div class="abg-act-time">{{ $activity->created_at->diffForHumans() }}</div>
                                    </div>
                                @empty
                                    <div class="abg-act-item">
                                        <div class="abg-act-dot" style="background:#286CD2;"></div>
                                        <div class="abg-act-text">Welcome to <strong>AbangananHub</strong>! Start by
                                            browsing verified properties in Cebu.</div>
                                        <div class="abg-act-time">Just now</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: Become a Landlord card (hidden if already Landlord) --}}
                    @if(!auth()->user()->hasRole('Landlord'))
                        <div>
                            <div class="abg-section-row" style="margin-bottom:22px;">
                                <div>
                                    <div class="abg-section-title">Become a Landlord</div>
                                    <div class="abg-section-sub">Earn income from your property</div>
                                </div>
                            </div>

                            <div class="abg-host-card">
                                <div class="abg-host-badge">Landlord Application</div>
                                <div class="abg-host-title">List your property on AbangananHub</div>
                                <div class="abg-host-sub">
                                    Apply to become a verified landlord. Submit your government ID for admin review —
                                    once approved, you can list properties and connect with tenants directly.
                                </div>
                                <div class="abg-host-perks">
                                    <div class="abg-host-perk">
                                        <div class="abg-host-perk-dot"></div>Submit government ID for verification
                                    </div>
                                    <div class="abg-host-perk">
                                        <div class="abg-host-perk-dot"></div>Admin reviews and approves your account
                                    </div>
                                    <div class="abg-host-perk">
                                        <div class="abg-host-perk-dot"></div>Create verified listings tenants can trust
                                    </div>
                                    <div class="abg-host-perk">
                                        <div class="abg-host-perk-dot"></div>Manage reservations and tenant inquiries
                                    </div>
                                </div>
                                <a href="#" class="abg-btn-host-card">
                                    Apply to be a Host
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                    Apply to be a Landlord
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endif

                </div>

            </div>{{-- end abg-body --}}

            <script>
                (function () {
                    // Avatar dropdown
                    const btn = document.getElementById('abg-avatar-btn');
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

                    // Category strip tabs
                    document.querySelectorAll('.abg-strip-item').forEach(item => {
                        item.addEventListener('click', e => {
                            e.preventDefault();
                            document.querySelectorAll('.abg-strip-item').forEach(i => i.classList.remove('active'));
                            item.classList.add('active');
                        });
                    });
                    @push('scripts')
                                        <script>
                                            (function () {
                            // Category strip — visual active state only (actual filtering happens server-side via URL)
                            const currentType = new URLSearchParams(window.location.search).get('type');
                            document.querySelectorAll('.abg-strip-item[data-type]').forEach(item => {
                                                item.classList.remove('active');
                                            if ((!currentType && item.dataset.type === 'all') || item.dataset.type === currentType) {
                                                item.classList.add('active');
                                }
                            });

                                            // Reservation client-side filter + search
                                            const filter = document.getElementById('reservation-filter');
                                            const search = document.getElementById('reservation-search');
                                            const rows   = document.querySelectorAll('#reservations-list .reservation-row');

                                            function applyFilters() {
                                const status = filter ? filter.value : 'all';
                                            const query  = search ? search.value.toLowerCase().trim() : '';
                                rows.forEach(row => {
                                    const matchStatus = status === 'all' || row.dataset.status === status;
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
                    @endpush

</x-app-layout>