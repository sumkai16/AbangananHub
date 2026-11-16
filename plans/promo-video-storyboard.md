# AbangananHub promo video — storyboard, script, shot list

Status: **draft, awaiting approval.** No images, clips or audio have been generated yet.
Length: **4:15**. English narration (~315 words, so the UI has room to breathe).
Style: Ghibli-inspired painted 2D, original characters, shown with real screen recordings.
Source of truth for features: `context/PRD.md`. Colors: `context/DESIGN.md` §3 (navy `#060D26`, coral `#FF8A66`, white).

## 1. Characters (original, not Ghibli's)

| | Mika (tenant) | Aling Rosa (landlord) |
|---|---|---|
| Who | 22, new graduate, first job in Cebu City, needs a room in Talisay | 50s, owns a small apartment building in Minglanilla |
| Look | Short bob, round glasses, **coral** tote bag | Gray-streaked bun, reading glasses, **navy** cardigan |
| Pain | Facebook posts, fake-looking photos, no way to check who is real | Messenger threads, rent in a notebook, receipts in a drawer |
| Phone use | Everything | Slowly, one finger |

Brand hints live in the clothes only. Backgrounds stay painted and warm. The app UI stays clean navy/coral/white.

## 2. Style bible (use in every image prompt)

- Hand-painted watercolor backgrounds, soft natural morning light, gentle rounded faces, visible brush texture, warm green and sky palette.
- Do not name a studio or a film in prompts. Describe the look instead.
- Fixed per character: outfit, hair, glasses, bag. Reuse the same character sheet as the first frame of every clip.
- Aspect 16:9. Keep faces large and simple so the video model does not warp them.

## 3. Timeline

| Time | Scene | What the viewer learns | Visual source |
|---|---|---|---|
| 0:00–0:20 | **Mika's search** | Facebook rental hunting is a gamble | AI clips C1, C2 |
| 0:20–0:38 | **Rosa's mess** | Landlords juggle chats, notebooks, receipts | AI clips C3, C4 |
| 0:38–0:45 | **Match cut** | Two people, same problem | Parallax P1 + motion graphic |
| 0:45–1:00 | **Logo reveal** | AbangananHub | Motion graphic M1 |
| 1:00–1:50 | **Rosa gets verified and lists** | Landlord ID check, admin review, approved listings | Screen recordings S1–S4 + clip C5 |
| 1:50–2:50 | **Mika finds a room** | Search, filters, verified badge, map, favorites, chat, reservation | Screen recordings S5–S10 + clip C6 |
| 2:50–3:20 | **The deal** | Direct QR payment, "I paid", landlord confirms, move-in | S11–S13 + clips C7, C8 |
| 3:20–3:50 | **Rosa stays organized** | Tenants, contracts, rent ledger, receipts, reminders, occupancy | S14–S17 + clip C9 |
| 3:50–4:15 | **Close** | Ratings both ways, tagline | Clips C10, C11 + M2 end card |

Why this order: a listing can't exist until the landlord is verified and approved, so the story follows the real flow. Rosa first, then Mika.

## 4. Narration script (English)

Pace: calm, warm, about 110 words per minute. Pauses over UI moments.

| Time | Line |
|---|---|
| 0:03 | Finding a place to rent in Cebu shouldn't feel like a gamble. But for Mika, it starts in a Facebook group — a hundred posts, a few photos, and no way to know who's real. |
| 0:20 | And for Aling Rosa, who owns a small apartment building, it's the other side of the same mess. Messages in Messenger. Rent in a notebook. Receipts in a drawer. |
| 0:38 | Two people. One city. No way to trust each other. |
| 0:46 | This is AbangananHub — a verified rental platform for Cebu, for tenants and property owners. |
| 1:02 | It starts with the landlord. Rosa verifies her identity with her phone camera — her ID, then a quick face check. An admin reviews every request before she's approved. |
| 1:25 | Then she lists her rooms, with photos and details. The admin approves each listing before anyone can see it. |
| 1:43 | No verification, no listing. That's the rule. |
| 1:52 | Now Mika searches again. She filters by type and budget — and every landlord she sees has already been verified. |
| 2:12 | She checks the map to see how far it is from work, and saves the ones she likes. |
| 2:28 | When she finds the one, she messages Rosa right inside the app. Replies show up live. |
| 2:41 | Mika reserves the room, and both of them can see exactly where the booking stands. |
| 2:52 | Rosa shares her QR code in the chat. Mika pays her directly, and taps "I paid" with the reference number. Rosa confirms — and only then does it count. |
| 3:12 | Move-in day. Rosa confirms, and the unit becomes occupied. |
| 3:22 | Every tenant, contract and payment now lives in one place. Rosa sees who's paid, who's late, and prints a receipt in a tap. |
| 3:40 | The app reminds her before rent is due — and shows how full her building is. |
| 3:52 | After the stay, they rate each other — so the next person can trust what they read. |
| 4:05 | AbangananHub. Find a place you can trust. Manage it with ease. |

## 5. Shot list

### 5a. Character clips (image-to-video, 5s each, 16:9, audio off)

| ID | Time | Shot | Camera |
|---|---|---|---|
| C1 | 0:00 | Mika on a Cebu jeepney bench, phone glowing, scrolling a crowded group feed | Slow push-in |
| C2 | 0:12 | Close on Mika's face: squints at a blurry listing photo, frowns | Static, slight handheld drift |
| C3 | 0:20 | Rosa at a wooden desk, notebook open, phone buzzing with message bubbles | Pan across desk |
| C4 | 0:30 | Rosa rummages a drawer of loose receipts, sighs | Over-shoulder, slow dolly |
| C5 | 1:12 | Rosa holds her phone up to her face for the selfie check, smiles at the result | Medium, soft push-in |
| C6 | 2:03 | Mika walks into a sunny painted room, opens a window, curtains move | Slow walk-in |
| C7 | 2:55 | Mika on the phone, thumb taps, relieved smile | Close, rack focus |
| C8 | 3:13 | Rosa hands Mika a key at the door | Two-shot, slow push |
| C9 | 3:32 | Rosa at the same desk, now tidy, relaxed over her phone | Pull-back reveal |
| C10 | 3:52 | Mika waves from the doorway of her new room, afternoon light | Wide, gentle drift |
| C11 | 4:00 | Rosa waters a plant on the balcony, building behind her | Slow push |

Parallax-only stills (no AI video, saves credits): P1 split-screen match cut, Cebu street establishing shot, building exterior.

### 5b. Screen recordings to capture

Tenant surfaces at **375px phone**. Landlord surfaces at phone, except admin (desktop). Use clean demo data (see §8).

| ID | Role | What to record | Zoom/highlight |
|---|---|---|---|
| S1 | Landlord | `/register/landlord` — ID camera capture | Coral ring on viewfinder |
| S2 | Landlord | Face check (liveness steps) | Step indicator |
| S3 | Admin (desktop) | Verification review screen, approve | Highlight Approve |
| S4 | Landlord | Add property/unit with photos, status "approved" | Photo upload + status badge |
| S5 | Tenant | Home search | Search bar |
| S6 | Tenant | Filters: type chip (Apartment), budget chip, "Show N places" (there is no verified-only filter: every listing already has a verified landlord) | Each filter as it's tapped |
| S7 | Tenant | Property detail with verified-landlord badge | Badge |
| S8 | Tenant | Map with directions to a landmark | Route line |
| S9 | Tenant | Favorite toggle, favorites page | Heart |
| S10 | Both | Chat, live message arriving | New message pop |
| S11 | Tenant | Reservation status timeline | Current step |
| S12 | Landlord | QR shared in chat | QR code |
| S13 | Tenant + Landlord | "I paid" with reference, landlord confirm | Confirm button |
| S14 | Landlord | Tenants & Contracts page, confirm move-in | Confirm button |
| S15 | Landlord | Rent ledger statuses (Paid/Overdue etc.) | Status column |
| S16 | Landlord | Printable receipt | Receipt |
| S17 | Landlord | Analytics, occupancy | Occupancy number |

### 5c. Google Flow prompts (image-to-video)

**Tested Nov 14 2026 — use these settings** (flow.google.com): prompt-bar settings → Video → **Frames** → 16:9 → model **Omni 1.2 Flash** → **720p** → 8 sec → **x1** = **24 credits per clip**. Then click Start, upload the keyframe, "Add to prompt", paste the prompt, generate. Download → 720p "Original size". Save into `promo-video/clips/` with the keyframe's name (`c1-jeepney.mp4`).

- Flow defaults to **x2** (two clips per run) — always set x1 or the cost doubles.
- Veo 3.1 Fast and Quality are **leaving Flow on Dec 5 2026**; Omni 1.2 Flash is the newest model and held character consistency well in both tests.
- Free allowance: 50 credits a day = 2 clips a day at 720p.
- Quirk seen in C1: the model may cross-dissolve to a closer angle partway through instead of one smooth push-in. Usable; trim in the edit.

| Clip | Status | Notes |
|---|---|---|
| C1 | ✅ `clips/c1-jeepney.mp4` | Style and face consistent; dissolves to a close-up at ~2s |
| C8 | ✅ `clips/c8-key-handoff.mp4` | Key handed over, Mika holds it to her chest, both smile; faces consistent |
| C2 | ✅ `clips/c2-closeup.mp4` (picked B) | Frown deepens, face stable; alt: `c2-closeup-a.mp4` |
| C3 | ✅ `clips/c3-desk-mess.mp4` (picked A) | Rosa rubs temple, phone lit; alt: `-b` |
| C4 | ✅ `clips/c4-receipts-drawer.mp4` (picked B) | Hand pulls the drawer; alt: `-a` |
| C5 | ✅ `clips/c5-selfie-check.mp4` (picked B) | Adjusts glasses, turns, smiles (matches the prompt); alt: `-a` |
| C6 | ✅ `clips/c6-room-walkin.mp4` (picked A) | Curtain open, face stays in view; alt: `-b` ends in profile |
| C7 | ✅ `clips/c7-cafe-relief.mp4` (picked B) | Laughs and leans back; alt: `-a` |
| C9 | ✅ `clips/c9-desk-tidy.mp4` (picked A) | Sips coffee, steam; alt: `-b` |
| C10 | ✅ `clips/c10-doorway-wave.mp4` (picked A) | Waves, then looks around; alt: `-b` |
| C11 | ✅ `clips/c11-balcony.mp4` (picked A) | Waters plants, looks at the sea; alt: `-b` |
| S1 | ✅ `clips/s1-id-card.mp4` (picked A) | Plain card, no text or seals; steady hand. Fake-camera file: `fakecam/s1-id-card.y4m` |
| S2 | ✅ `clips/s2-rosa-selfie.mp4` (picked B) | Passes the app's face-api.js check offline: face found in 32/32 frames, yaw +79° then −91°, then looks up. Version A failed (both turns read the same sign). Fake-camera file: `fakecam/s2-rosa-selfie.y4m` |

**Recording S1/S2 with the fake camera:** quit Chrome fully, then start it with
`google-chrome --use-fake-device-for-media-stream --use-fake-ui-for-media-stream --use-file-for-fake-video-capture=/home/axci/Projects/AbangananHub/promo-video/fakecam/s2-rosa-selfie.y4m`
(swap the file to `s1-id-card.y4m` for the ID step). Chrome loops the file as the webcam for both front and back camera. Restart Chrome normally afterwards.

Run Nov 15 2026 on Google AI Pro (1,050 credits/month): x2 versions per clip, 48 credits each. Credits left after the batch: 618 (reset Dec 15). The `-a`/`-b` files are kept as alternates.

Every prompt ends with the same style lock so the clips match. Veo makes 8s clips; trim to the best 4–6s in the edit. Mute Veo's own audio — the soundtrack comes from ElevenLabs.

Style lock (already included at the end of each prompt below): *Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters.*

| ID | Keyframe | Prompt |
|---|---|---|
| C1 | `c1-jeepney.png` | Slow gentle push-in toward the young woman with round glasses seated in the jeepney. She scrolls her phone with her thumb, frowns slightly and lets out a small sigh. Her hair sways in the breeze from the open window, sunlight flickers across her face, the street and palm trees drift past outside. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C2 | `c2-closeup.png` | Static close-up with a very slight handheld drift. The young woman squints at her phone, tilts her head in doubt and her frown deepens, the glow of the screen flickering on her face and glasses. Background stays softly blurred and moving. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C3 | `c3-desk-mess.png` | Slow pan from left to right across the cluttered desk, ending on the older woman. Her phone lights up again with a new notification, she rubs her temple and glances at the open notebook, the ceiling fan turns slowly, papers flutter gently. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C4 | `c4-receipts-drawer.png` | Slow over-the-shoulder dolly in. The older woman pulls the drawer open further, a few loose paper slips spill and slide, she pauses and her shoulders drop in a tired sigh. Warm afternoon light, dust drifting in the sunbeam. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C5 | `c5-selfie-check.png` | Soft slow push-in. The older woman holds her phone steady at arm's length, adjusts her glasses, turns her head slightly left and then right as if following instructions, then breaks into a relieved warm smile. Leaves of the house plants move gently. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C6 | `c6-room-walkin.png` | The young woman with round glasses takes a step forward and draws the light curtain fully open, sunlight floods the room, she looks out at the city and the sea with a delighted smile. Camera slowly follows her toward the window. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C7 | `c7-cafe-relief.png` | Close shot with a gentle rack focus from the coffee cup in the foreground to the young woman. She taps her phone once, reads, then smiles wide with relief and leans back in her chair. Golden hour light shimmers, plants sway softly. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C8 | `c8-key-handoff.png` | Slow push-in on the two women at the gate. The older woman places the silver key into the young woman's open palm, the young woman closes her hand around it and both smile warmly at each other. Leaves rustle, soft morning sunlight. Hand-painted watercolor animation style, keep the exact character designs, faces and clothing of both women from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C9 | `c9-desk-tidy.png` | Slow pull-back revealing the tidy, calm office. The older woman scrolls her phone, nods contentedly and takes a sip of coffee, the ceiling fan turns slowly, steam rises from the cup. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C10 | `c10-doorway-wave.png` | Wide shot with a gentle drift. The young woman in the doorway waves happily toward the camera, then turns to look around her new room with a satisfied smile. Curtains move in the breeze, golden afternoon light. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |
| C11 | `c11-balcony.png` | Slow push-in on the balcony. The older woman tilts the watering can, water streams onto the plants, she pauses to look out at the sea and the rooftops with a peaceful smile. Palm trees sway, clouds drift slowly in the late-afternoon sky. Hand-painted watercolor animation style, keep the exact character design, face and clothing from the first frame. Subtle, natural motion. No text, no captions, no dialogue, no new characters. |

## 6. Audio plan

| Layer | Plan |
|---|---|
| Voiceover | ✅ `promo-video/audio/vo/vo-01.mp3` … `vo-17.mp3`, one file per §4 line. ElevenLabs `eleven_v4`, voice "Sarah" (`EXAVITQu4vr4xnSDxMaL`), stability 0.55. Re-run one line: `python3 -I make-vo.py eleven_v4 EXAVITQu4vr4xnSDxMaL 05`. Total ≈ 2:05 of speech; every line fits its slot. Made on a free plan, so credit ElevenLabs in the end credits; redo on a paid plan if the video is ever used commercially. |
| Music | ✅ Google Flow Music (Lyria 3.6, AI Pro account), instrumental: `audio/music/bgm-take1.wav` (2:17, sparse/uneasy opening → warm) and `bgm-take2.wav` (2:56, melancholic opening → hopeful). Plan: take 1 under 0:00–~2:00, crossfade into the hopeful second half of take 2 to the end. ElevenLabs music needs a paid plan. |
| SFX | ✅ `audio/sfx/`: phone-buzz, page-flip, paper-slide, key-jingle, ui-tap, ui-pop, success-chime, curtain-whoosh (`make-sfx.py`). |

## 7. Editing instructions

1. **Pacing:** painted scenes 4–6s per shot. UI scenes 3–5s per screen with one clear action each.
2. **Transitions:** match cuts on shared shapes (phone to phone, desk to desk). Soft cross-dissolve between painted and UI. No spinning or glitch effects.
3. **UI treatment:** screen recordings sit inside a clean phone mockup (tenant, landlord) or laptop mockup (admin) on a soft painted or navy background. Zoom to the action, then add a coral ring or underline for about 1s.
4. **Typography:** short on-screen lines only (max 6 words), white on navy or navy on white. Coral only for the key word.
5. **Logo reveal (M1):** navy full-frame, "Abanganan" in white, "Hub" slides in in coral `#FF8A66`.
6. **Color grade:** warm in the story scenes, neutral in the UI scenes. Keep navy true, never crush it.
7. **Ducking:** music drops about 10 dB under narration.
8. **End card (M2):** logo, tagline, no URL until the domain is live.

## 8. Accuracy guardrails (from PRD.md)

Do not show or say:
- Viewing scheduler or calendar for visits. **Not in the product.** Visits are arranged by chat only.
- Escrow, held money or online checkout as the main payment route. The default is direct QR/InstaPay; online is behind an admin switch that is off.
- Auto-generated or "legally binding" contracts. The app tracks the agreement stage and contracts, it doesn't generate them.
- That verification detects fake IDs. It does ID capture, OCR, a face check and admin review.
- A live website URL. The domain isn't deployed yet.

Demo data: ✅ `database/seeders/PromoSeeder.php`. Run `php artisan db:seed --class=PromoSeeder` before recording, and again to reset between takes.

| Login (password `password`) | Role | State |
|---|---|---|
| `rosa@abangananhub.com` | Landlord, approved | "Abellana Apartments, Minglanilla": Room 1A (Joanna, paid), 1B (Paolo, overdue since the 5th), 2A (Bea, due on the 20th), **2B free (Mika's)**, 3A free. Payout QR set. |
| `mika@abangananhub.com` | Tenant | Clean: no favorites, chats or reservations; tenant details filled so signing isn't blocked |
| `admin@abangananhub.com` | Admin | For S3 (approve the landlord signed up live in S1–S2) and listing approval |

## 9. Credit budget (Weave)

| Item | Count | Each | Total |
|---|---|---|---|
| Kling Video clips, 5s | 11 | ~35 | ~385 |
| With about 2.5 tries per clip | | | ~960 |
| Test: Veo 3.1 clip vs Kling clip | 2 | 90 + 35 | ~125 |

**Video models need a paid Weave plan** (confirmed Nov 14 2026: the free plan refuses every video model). Starter (1,500 credits) covers the whole video on Kling, including the Veo-vs-Kling test.

## 9b. Assets made so far

| File | What | Made with |
|---|---|---|
| `promo-video/characters/mika-sheet.png` | Mika character sheet (approved style) | Figma image generation |
| `promo-video/characters/rosa-sheet.png` | Aling Rosa character sheet | Figma image generation |
| `promo-video/keyframes/c1…c11-*.png` | Keyframes for clips C1–C11 (first frames for image-to-video) | Figma image generation |
| `promo-video/motion/m1.mp4` | Logo reveal, 6s, 1920×1080, 30fps | `logo.html` + `render.sh` (headless Chrome + ffmpeg) |
| `promo-video/motion/m2.mp4` | End card, 8s, ends on black | same |

To change the motion graphics: edit `promo-video/motion/logo.html`, preview with `logo.html?scene=m1&t=2`, re-render with `./render.sh m1 6` or `./render.sh m2 8`.

Figma file with all of them: https://www.figma.com/design/nP2SZW6L2VUHT9P4k09PL3
Canva image generation is disabled by the team admin, so stills come from Figma.

## 10. Open decisions

1. Approve this storyboard and script, or change scenes/lines.
2. Character names (Mika, Aling Rosa) fine?
3. Run the Kling vs Veo test, then decide on Starter?
4. Do you have an ElevenLabs key? Needed for voice, music, sound effects.
