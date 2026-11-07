// Sign-up forms: step handling and a live "is this email taken?" check (Alpine component `registerForm`).
//
//   x-data="registerForm({ emailUrl: '...', steps: 2, step: 1 })"  on a wrapper around the <form>
//   <form @submit="onSubmit($event)">
//
// Field rules (required, email, length, password match) stay with resources/js/field-validation.js, which already
// runs on submit and stops it while a visible field is bad. This file only decides what a *valid* submit does:
// go to the next step, or send the form. The server still validates everything, so a failed check never blocks.
document.addEventListener('alpine:init', () => {
    Alpine.data('registerForm', (cfg = {}) => ({
        step: cfg.step || 1,
        steps: cfg.steps || 1,
        emailState: 'idle', // idle | checking | ok | taken
        _timer: null,
        _last: '',

        init() {
            const email = this.emailField();
            if (email && email.value) this.checkEmail();
        },

        form() { return this.$root.querySelector('form'); },
        emailField() { return this.$root.querySelector('[name="email"]'); },

        onSubmit(event) {
            // field-validation.js ran first (capture phase) and already stopped the event if a visible field is bad.
            if (this.step < this.steps) {
                event.preventDefault();
                this.step += 1;
                this.focusFirst();
                return;
            }

            if (this.emailState === 'taken') {
                event.preventDefault();
                this.flagTaken();
                this.emailField()?.focus();
            }
        },

        back() {
            if (this.step > 1) {
                this.step -= 1;
                this.focusFirst();
            }
        },

        focusFirst() {
            this.$nextTick(() => {
                const panel = this.$root.querySelector(`[data-step="${this.step}"]`);
                panel?.querySelector('input:not([type="hidden"])')?.focus({ preventScroll: true });
                this.$root.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            });
        },

        onEmailInput() {
            clearTimeout(this._timer);
            this.emailState = 'idle';
            this._last = '';
            this._timer = setTimeout(() => this.checkEmail(), 450);
        },

        onEmailBlur() {
            clearTimeout(this._timer);
            this.checkEmail();
        },

        flagTaken() {
            window.fieldValidation?.show(this.form(), 'email', 'This email already exists. Log in instead.');
        },

        async checkEmail() {
            const field = this.emailField();
            if (!field || !cfg.emailUrl) return;

            const value = field.value.trim().toLowerCase();
            // Say nothing until the address is a complete, valid email.
            if (value === '' || !field.validity.valid) {
                this.emailState = 'idle';
                return;
            }
            if (value === this._last && this.emailState !== 'idle') return;

            this._last = value;
            this.emailState = 'checking';

            try {
                const res = await fetch(`${cfg.emailUrl}?email=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();

                // The user kept typing while this was in flight: that answer is about an older address.
                if (field.value.trim().toLowerCase() !== value) return;

                if (data.available === true) {
                    this.emailState = 'ok';
                } else if (data.available === false) {
                    this.emailState = 'taken';
                    this.flagTaken();
                } else {
                    this.emailState = 'idle';
                }
            } catch (e) {
                // Offline, throttled or a server error: say nothing. The server checks again on submit.
                this.emailState = 'idle';
            }
        },
    }));
});
