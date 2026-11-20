# Conversation scam detection (planned, not built)

Status: **option B is built** (Nov 19 2026, see CHANGELOG): a thread is private to admins until a participant asks for a review. The scanner (steps 3-5 below) is still only planned.

## Why
Admins could open any conversation with no reason and no record, and the list showed message previews. Reading private messages should be rare, justified and logged. The same records can find scams automatically, so the admin only reads what the system flags.

## Decision
The system scans; admins read flagged threads only. A flag is a signal for the admin, never an automatic ban (same rule as the face match).

## Steps
1. **First, small.** Audit every open of a thread (`AuditLog` `conversation.view`, no message text in the row).
2. **Next, small.** Remove message previews from the admin list; an admin opens a thread only if it has a flag, a concern or a report; a visible "An administrator reviewed this conversation" line in the thread.
3. **Core.** `ConversationScanner` runs on message send. Rule signals: payment asked outside the platform (GCash/bank number, "send the deposit to"); moving chat off-platform (Telegram, Viber, WhatsApp, phone number); pressure and deposit-before-viewing; outside links; cross-thread patterns (one landlord sending the same payment ask to many tenants, new landlord with a burst of inquiries, identical messages to many landlords). Hits go to `conversation_flags` (message, rule, score, open/dismissed/confirmed, reviewer). Admin queue "Suspicious conversations" shows the flagged message with a few around it, Dismiss and Confirm.
4. **Later.** Second pass with a Claude model on rule-flagged messages only, measured against the confirmed/dismissed flags from step 3.
5. **Prevention.** Composer warning when someone types a payment or contact-sharing phrase: "Pay only through AbanganHub."

## Must do with it
Update Terms and Privacy Policy: messages are scanned for fraud, and who can read flagged ones. Have the adviser check the wording (Data Privacy Act).

## Test checklist (when built)
- Each rule fires on a made-up scam message and stays quiet on a normal one.
- A flag never blocks or bans by itself.
- An admin cannot open an unflagged thread; every open is audited.
- Dismiss and Confirm change the flag and are audited.
