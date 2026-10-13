// Replaces the browser's native "Please fill out this field." bubble with an inline
// tag on the field's border (design D). The full sentence goes to screen readers only. Runs on every <form>, so individual forms need no markup change.
// Opt out per form with `data-native-validation`. Hidden controls are skipped, so the
// server still decides on anything the user can't see.

const FIELD_SELECTOR = 'input, select, textarea';

const labelFor = (el) => {
    const fromLabel = el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
    const text = el.dataset.label || fromLabel?.textContent || el.getAttribute('aria-label') || el.placeholder || 'This field';
    return text.replace(/[*:]/g, '').trim();
};

// `tag` is the short pill on the border; `text` is the full sentence (screen readers, checkboxes).
const describe = (el) => {
    const v = el.validity;
    const label = labelFor(el);
    if (v.valueMissing) {
        if (el.type === 'checkbox') return { tag: 'Required', text: `Tick "${label}" to continue.` };
        if (el.tagName === 'SELECT' || el.type === 'radio' || el.type === 'file') return { tag: 'Required', text: `Choose ${label.toLowerCase()}.` };
        return { tag: 'Required', text: `${label} is required.` };
    }
    if (v.typeMismatch) {
        const kind = { email: 'email', url: 'web address' }[el.type] || 'value';
        return { tag: `Invalid ${kind}`, text: `Enter a valid ${kind}.` };
    }
    if (v.customError) return { tag: el.dataset.mismatchTag || "Doesn't match", text: el.validationMessage };
    if (v.tooShort) return { tag: `Min ${el.minLength} characters`, text: `${label} must be at least ${el.minLength} characters.` };
    if (v.tooLong) return { tag: `Max ${el.maxLength} characters`, text: `${label} must be at most ${el.maxLength} characters.` };
    if (v.rangeUnderflow) return { tag: `Min ${el.min}`, text: `${label} must be ${el.min} or more.` };
    if (v.rangeOverflow) return { tag: `Max ${el.max}`, text: `${label} must be ${el.max} or less.` };
    if (v.patternMismatch) return { tag: el.dataset.errorTag || 'Invalid format', text: el.title || `${label} is not in the right format.` };
    return { tag: 'Invalid', text: el.validationMessage || `${label} is not valid.` };
};

// Turn a Laravel 422 message into a short tag ("The password field must be at least 8 characters." -> "Min 8 characters").
const tagFromServer = (message) => {
    const m = String(message);
    const min = m.match(/at least (\d+) characters/i);
    if (min) return `Min ${min[1]} characters`;
    if (/required/i.test(m)) return 'Required';
    if (/already been taken|already exists/i.test(m)) return 'Already in use';
    if (/confirmation|do(es)? not match/i.test(m)) return "Doesn't match";
    if (/valid email/i.test(m)) return 'Invalid email';
    return 'Invalid';
};

// `foo_confirmation` must equal `foo` in the same form. Done with setCustomValidity so it behaves like any other rule.
const syncConfirm = (form) => {
    form.querySelectorAll('input[name$="_confirmation"]').forEach((c) => {
        const base = form.querySelector(`[name="${CSS.escape(c.name.replace(/_confirmation$/, ''))}"]`);
        if (!base) return;
        c.setCustomValidity(c.value !== '' && c.value !== base.value ? `${labelFor(c)} does not match.` : '');
    });
};

const isVisible = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

function clearError(el) {
    el.classList.remove('fv-bad');
    el.removeAttribute('aria-invalid');
    el.removeAttribute('aria-describedby');
    el._fv?.forEach((n) => n.remove());
    el._fv = null;
}

function showError(el, override) {
    clearError(el);
    const { tag, text } = override ?? describe(el);
    const host = el.parentElement;
    if (getComputedStyle(host).position === 'static') host.style.position = 'relative';

    const msg = document.createElement('p');
    const tagged = !['checkbox', 'radio'].includes(el.type);
    msg.className = tagged ? 'fv-sr' : 'fv-msg';
    msg.id = `fv-${el.id || el.name || Math.random().toString(36).slice(2)}`;
    msg.setAttribute('role', 'alert');
    msg.innerHTML = '<span class="fv-ico" aria-hidden="true">!</span><span></span>';
    msg.lastChild.textContent = text;

    const badge = document.createElement('span');
    badge.className = 'fv-tag';
    badge.textContent = tag;
    badge.setAttribute('aria-hidden', 'true');
    badge.title = text;
    // Anchor to the control's own box (it may sit beside an icon or inside a padded wrapper).
    badge.style.top = `${el.offsetTop}px`;
    badge.style.left = `${el.offsetLeft + el.offsetWidth}px`;

    el.after(msg);
    // A tag on the border only suits full-width text-like controls; checkboxes/radios keep a visible line.
    if (tagged) host.append(badge);
    el.classList.add('fv-bad');
    el.setAttribute('aria-invalid', 'true');
    el.setAttribute('aria-describedby', msg.id);
    el._fv = tagged ? [msg, badge] : [msg];
}

function validate(form) {
    syncConfirm(form);
    const bad = [...form.querySelectorAll(FIELD_SELECTOR)].filter(
        (el) => !el.disabled && el.type !== 'hidden' && !el.validity.valid && isVisible(el),
    );
    form.querySelectorAll('.fv-bad').forEach((el) => { if (el.validity.valid) clearError(el); });
    bad.forEach((el) => showError(el));
    return bad;
}

// The browser runs its native check *before* firing `submit`, so noValidate must already be
// set by then: apply it to every form up front, including forms added later (modals, Alpine).
const arm = (root) => {
    const forms = root.matches?.('form') ? [root] : [];
    forms.push(...(root.querySelectorAll?.('form') ?? []));
    forms.forEach((f) => { if (!('nativeValidation' in f.dataset)) f.noValidate = true; });
};
arm(document);
new MutationObserver((list) => list.forEach((m) => m.addedNodes.forEach((n) => n.nodeType === 1 && arm(n))))
    .observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || 'nativeValidation' in form.dataset) return;
    if (e.submitter?.formNoValidate) return;
    const bad = validate(form);
    if (!bad.length) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    bad[0].focus({ preventScroll: true });
    bad[0].scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}, true);

// Clear (or re-check) a field as soon as the user corrects it.
const recheck = (e) => {
    const el = e.target;
    if (el.form) {
        syncConfirm(el.form);
        // Editing the password also re-judges an already-flagged confirmation field.
        el.form.querySelectorAll('.fv-bad').forEach((f) => { if (f !== el && f.validity.valid) clearError(f); });
    }
    if (el.classList?.contains('fv-bad')) el.validity.valid ? clearError(el) : showError(el);
};
document.addEventListener('input', recheck);
document.addEventListener('change', recheck);

// Phone fields accept digits and the usual separators only; letters are dropped as they are typed.
document.addEventListener('input', (e) => {
    const el = e.target;
    if (el.type !== 'tel') return;
    const clean = el.value.replace(/[^0-9+()\s-]/g, '');
    if (clean !== el.value) el.value = clean;
});

// For forms that submit with fetch: place a server (422) error on its field. Returns false if no field matches.
window.fieldValidation = {
    show(form, name, message) {
        const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
        if (!el || !isVisible(el)) return false;
        showError(el, { tag: tagFromServer(message), text: String(message) });
        return true;
    },
    clear(form) { form.querySelectorAll('.fv-bad').forEach(clearError); },
};

// Password strength meter: any <input data-strength>. Advisory only; the real rules (min length) stay on the input.
const LEVELS = [['Too short', '#DC2626'], ['Weak', '#DC2626'], ['Fair', '#F59E0B'], ['Good', '#2AA89F'], ['Strong', '#16A34A']];
const score = (pw) => {
    if (pw.length < 8) return 0;
    let n = 1;
    if (pw.length >= 12) n++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) n++;
    if (/\d/.test(pw) && /[^A-Za-z0-9]/.test(pw)) n++;
    else if (/\d|[^A-Za-z0-9]/.test(pw)) n += 0.5;
    return Math.min(4, Math.floor(n));
};
document.addEventListener('input', (e) => {
    const el = e.target;
    if (!el.matches?.('input[data-strength]')) return;
    let meter = el._meter;
    if (!meter) {
        meter = document.createElement('div');
        meter.className = 'fv-strength';
        meter.setAttribute('aria-live', 'polite');
        meter.innerHTML = '<span class="fv-bars"><i></i><i></i><i></i><i></i></span><span class="fv-strength-label"></span>';
        (el.parentElement.children.length > 1 ? el.parentElement : el).after(meter);
        el._meter = meter;
    }
    meter.hidden = el.value === '';
    const n = score(el.value);
    const [label, color] = LEVELS[n];
    meter.style.setProperty('--fv-c', color);
    meter.querySelectorAll('i').forEach((b, i) => b.classList.toggle('on', i < Math.max(n, el.value ? 1 : 0)));
    meter.querySelector('.fv-strength-label').textContent = `Password strength: ${label}`;
});
