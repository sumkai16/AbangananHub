# DESIGN.md — Design System

## 0. The three defaults to actively avoid
None of the three AI-default looks apply. AbangananHub uses a teal-forward identity with glassmorphism — distinct from cream/serif, dark-mode acid, and broadsheet layouts.

## 1. Reference / Inspiration
- Airbnb (June 2026) — card layout, image-is-the-card pattern, browse grid, clean listing detail pages
- Glassmorphism trend (frosted glass panels over gradient backgrounds) — applied as the signature visual layer

## 2. Brief → Token System
- Subject: A rental accommodation marketplace for tenants and landlords in Cebu. The page's job varies by role: tenants browse/search/reserve; landlords manage listings/units/reservations; admin verifies/approves/monitors.
- Tone: **Trustworthy.** What it should NOT feel like: playful, startup-trendy, or corporate enterprise.
- Signature element: Frosted glass panels (`bg-white/70 backdrop-blur-xl`) floating over a teal mesh gradient background — ties to the "transparency and trust" brand promise (you can see through the glass, the platform is transparent).

## 3. Color Palette

| Role | Hex | Usage |
|---|---|---|
| Primary (Deep Ocean Blue) | `#156F8C` | Navigation bar, headings, key interface elements |
| Secondary (Ocean Teal) | `#2AA7A1` | Primary buttons, icons, active states — fills/borders/backgrounds only, **never foreground text on white** (fails WCAG AA, ~2.9:1) |
| Accent (Aqua) | `#69D2C6` | Badges, highlights — fills only, **never foreground text on white** (fails WCAG AA, ~2.2:1) |
| CTA (Soft Coral) | `#FF8A65` | CTA buttons: Search, Book Now, List Property |
| Background (Ice White) | `#F7FCFC` | Main page background |
| Section Background (Mist Blue) | `#EEF8F8` | Distinguishes content sections |
| Surface/Card (White) | `#FFFFFF` | Property cards, forms, panels |
| Text primary (Charcoal) | `#1F2937` | Headings and essential content |
| Text muted (Slate Gray) | `#64748B` | Descriptions, labels, supporting info |
| Borders (Soft Gray) | `#E2E8F0` | Subtle separation between components |
| Success (Emerald Green) | `#22C55E` | Successful actions, verified statuses |
| Warning (Amber) | `#FBBF24` | Notifications, cautionary messages |
| Error (Red) | `#EF4444` | Validation errors, failed actions, critical alerts |
| Footer background | `#0F172A` | Accepted palette exception for footer only |

Rules:
- One accent color for CTAs (`#FF8A65`) — everything else neutral or teal-family.
- `#2AA7A1` and `#69D2C6` are restricted to fills, borders, and backgrounds. Never as foreground text on white.
- Hover states: `hover:brightness-95` only. No hardcoded darker hex values.
- No custom CSS class systems (`abg-*`), no inline `<style>` blocks — pure Tailwind only.

## 4. Typography
- Display/heading font: **Poppins** (Google Fonts, loaded in base layout)
- Body font: **Inter** (Google Fonts, loaded in base layout)
- Utility font: Inter (same as body — no third font needed)
- Type scale: 12 / 14 / 16 / 20 / 24 / 32 / 48 (maps to Tailwind `text-xs` through `text-5xl`)
- Weight rules: Poppins 600–700 for headings, Inter 400 for body, Inter 500 for labels/buttons
- Line-height: 1.5–1.75 for body text
- Line length: 65–75 characters max (`max-w-prose` or equivalent)

## 5. Spacing & Layout
- Base unit: 4px (Tailwind default)
- Scale: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 (Tailwind `p-1` through `p-24`)
- Max content width: `max-w-7xl` (1280px) — used consistently, no mixed container widths
- z-index scale: 10 (dropdowns) / 20 (sticky nav) / 30 (modals/overlays) / 50 (toasts/notifications)

## 6. Layout Concept
Teal mesh gradient (`bg-fixed`, 135° angle) covers the full viewport as the base layer. All content panels float on top as frosted glass cards (`bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg`). This creates depth without heavy shadows and ties every page to the brand identity. The structure serves trust: transparency (literal glass) maps to platform transparency.

Property browse grid: Airbnb-style image-is-the-card pattern — no white box wrapping. Text sits below the image on the page background. 3-column grid on desktop, 2 on tablet, 1 on mobile.

## 7. Components
- Border radius default: `rounded-2xl` (standard), `rounded-3xl` (hero sections only)
- Shadow style: `shadow-lg` on glassmorphism panels; property cards use the image-is-the-card pattern (no shadow wrapper)
- Button rules: `cursor-pointer` on all clickable elements; hover via `hover:brightness-95`, never layout-shifting scale. CTA buttons use `#FF8A65`. Standard buttons use `#2AA7A1` fill.
- Input/form rules: every input has a real `<label for>`, not placeholder-as-label
- Icon set: Heroicons (outline/stroke), inline SVG only. **No emojis anywhere, ever.** Unicode checkmarks (✓) acceptable as plain text only.
- Touch targets: minimum 44x44px on interactive elements
- Glassmorphism spec: `bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg`
- Accepted glassmorphism exceptions: floating dropdowns, modals, dark-background cards, and Airbnb-style borderless image tiles remain opaque

## 8. Motion
- Standard transition: `transition-all duration-200 ease-in-out` (200ms)
- Use `transform`/`opacity` for animation, not `width`/`height`
- Respect `prefers-reduced-motion`
- Favorites: unfavorite triggers fade-out + DOM removal (not left sitting unfavorited)
- No orchestrated page-load animations — keep it fast and functional

## 9. Accessibility
- Minimum contrast ratio: 4.5:1 for normal text (why `#2AA7A1` and `#69D2C6` are banned as foreground text on white)
- Focus state: visible focus ring on every interactive element
- Keyboard nav: tab order matches visual order
- Alt text on meaningful images, `aria-label` on icon-only buttons
- `[x-cloak] { display: none; }` in global CSS to prevent Alpine flash-of-unstyled-content

## 10. Anti-patterns (things to actively cut)
- No emojis as icons — SVG only
- No inline `<style>` blocks — Tailwind utilities only
- No custom CSS class systems (`abg-*` prefixes)
- No hardcoded hover hex values — `hover:brightness-95` only
- No `Storage::url()` on full external URLs (Cloudinary/Unsplash) — output `$media->media_url` directly
- No mixing container widths across pages
- No `<template x-if>` inside `<svg>` elements — use `x-show` on `<path>` instead
- No explicit `x-init="init()"` — Alpine.js v3 auto-invokes `init()`
- Joseph audit items: watch for `#1A1A2E` (wrong palette), unresolved git merge conflict markers, `<x-app-layout>` instead of `@extends`

## 11. Self-Critique Checklist
- [ ] Does this look like generic Tailwind UI? If yes, the glassmorphism signature isn't prominent enough
- [ ] Is the frosted glass effect the ONE bold element, with everything else quiet?
- [ ] Responsive at 375px, 768px, 1024px, 1440px — no horizontal scroll
- [ ] Glassmorphism panels have real opacity (`bg-white/70`, not `bg-white/10`)
- [ ] Borders visible (`border-white/30` reads on gradient background)
- [ ] Content doesn't hide behind fixed nav
- [ ] Teal mesh gradient uses `bg-fixed` (doesn't scroll with content)
