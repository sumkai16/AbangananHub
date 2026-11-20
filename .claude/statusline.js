// Claude Code status line: model + effort, 5h usage, context, weekly usage.
let raw = '';
process.stdin.on('data', (c) => (raw += c));
process.stdin.on('end', () => {
  let d = {};
  try { d = JSON.parse(raw); } catch {}

  const ACCENT = '\x1b[38;2;255;0;127m'; // #ff007f
  const DIM = '\x1b[2m';
  const RESET = '\x1b[0m';
  const BOLD = '\x1b[1m';

  const num = (v) => (typeof v === 'number' && !Number.isNaN(v) ? Math.round(v) : null);
  const bar = (v) => {
    const filled = Math.max(0, Math.min(8, Math.round((v / 100) * 8)));
    return ACCENT + '█'.repeat(filled) + RESET + DIM + '░'.repeat(8 - filled) + RESET;
  };
  const left = (ts) => {
    if (typeof ts !== 'number') return '';
    const s = ts > 1e12 ? ts / 1000 : ts;
    const m = Math.max(0, Math.round((s - Date.now() / 1000) / 60));
    const t = m >= 1440 ? `${Math.floor(m / 1440)}d${Math.floor((m % 1440) / 60)}h`
      : m >= 60 ? `${Math.floor(m / 60)}h${String(m % 60).padStart(2, '0')}m` : `${m}m`;
    return ` ${DIM}↻${t}${RESET}`;
  };
  const stat = (label, v, ts) =>
    v === null ? null : `${DIM}${label}${RESET} ${bar(v)} ${ACCENT}${v}%${RESET}${left(ts)}`;

  const model = d.model?.display_name || d.model?.id || 'Claude';
  const effort = d.effort?.level || d.effort_level || d.effortLevel;
  const head = `${BOLD}${ACCENT}${model}${RESET}` + (effort ? ` ${DIM}${effort}${RESET}` : '');

  const parts = [
    head,
    stat('5h', num(d.rate_limits?.five_hour?.used_percentage), d.rate_limits?.five_hour?.resets_at),
    stat('ctx', num(d.context_window?.used_percentage)),
    stat('week', num(d.rate_limits?.seven_day?.used_percentage), d.rate_limits?.seven_day?.resets_at),
  ].filter(Boolean);

  process.stdout.write(parts.join(` ${DIM}·${RESET} `));
});
