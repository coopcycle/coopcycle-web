import { Controller } from '@hotwired/stimulus';

/**
 * Multi-cell numeric OTP/TOTP input. Drives a series of single-character
 * inputs that focus-advance, accept a paste, and auto-submit the parent
 * form once the code is complete. A `submitting` guard ensures a second
 * ENTER press (or repeat paste) during the navigation can never trigger
 * a duplicate POST.
 *
 * Targets:
 *   - digit  : one <input> per code character (required)
 *   - hidden : the hidden input that receives the joined code on submit
 *   - submit : (optional) submit button, disabled while a submit is in flight
 *
 * Values:
 *   - length : number of digits (default 6)
 *
 * Usage in Twig:
 *   <form {{ stimulus_controller('otp-input') }}
 *         data-action="submit->otp-input#onSubmit">
 *     <input {{ stimulus_target('otp-input', 'hidden') }} name="_auth_code" type="hidden" />
 *     {% for i in 1..6 %}
 *     <input {{ stimulus_target('otp-input', 'digit') }}
 *            data-action="input->otp-input#onInput keydown->otp-input#onKeydown paste->otp-input#onPaste"
 *            type="text" />
 *     {% endfor %}
 *     <button {{ stimulus_target('otp-input', 'submit') }} type="submit">Verify</button>
 *   </form>
 */
export default class extends Controller {
    static targets = ['digit', 'hidden', 'submit'];
    static values  = { length: { type: Number, default: 6 } };

    connect() {
        this.submitting = false;
        if (this.hasDigitTarget && this.digitTargets.length > 0) {
            this.digitTargets[0].focus();
        }
    }

    // ---------------------------------------------------------------------
    // Read-only state
    // ---------------------------------------------------------------------

    get value() {
        return this.digitTargets.map(i => i.value).join('');
    }

    get isComplete() {
        return this.value.length === this.lengthValue;
    }

    // ---------------------------------------------------------------------
    // Actions wired via data-action in the template
    // ---------------------------------------------------------------------

    onInput(event) {
        const input = event.target;
        // Keep only the last typed digit, drop everything else.
        input.value = input.value.replace(/\D/g, '').slice(-1);
        if (input.value === '') return;

        this._focusNextOf(input);
        if (this.isComplete) this._autoSubmit();
    }

    onKeydown(event) {
        const input = event.target;
        const index = this.digitTargets.indexOf(input);

        if (event.key === 'Backspace' && input.value === '' && index > 0) {
            const previous = this.digitTargets[index - 1];
            previous.focus();
            previous.value = '';
            event.preventDefault();
            return;
        }

        if (event.key === 'Enter') {
            // Swallow ENTER so the browser default-form-submit cannot race
            // with our programmatic submit. Route through the same gate.
            event.preventDefault();
            if (this.isComplete) this._autoSubmit();
        }
    }

    onPaste(event) {
        event.preventDefault();

        const start = this.digitTargets.indexOf(event.target);
        if (start < 0) return;

        const pasted = (event.clipboardData || window.clipboardData)
            .getData('text')
            .replace(/\D/g, '')
            .slice(0, this.lengthValue);

        [...pasted].forEach((char, i) => {
            const target = this.digitTargets[start + i];
            if (target) target.value = char;
        });

        const next = this.digitTargets[start + pasted.length]
                  ?? this.digitTargets[this.lengthValue - 1];
        next.focus();

        if (this.isComplete) this._autoSubmit();
    }

    onSubmit(event) {
        // Triggered when the user clicks the submit button or presses Enter
        // and our onKeydown didn't preventDefault (e.g. partial code).
        if (this.submitting) {
            event.preventDefault();
            return;
        }
        this.submitting = true;
        this._sync();
        // No preventDefault → the browser performs the submission normally,
        // with `hiddenTarget.value` already populated by _sync().
    }

    // ---------------------------------------------------------------------
    // Public helpers (callable from outside via this.element.controllers)
    // ---------------------------------------------------------------------

    /** Imperative submit, e.g. from another controller or the devtools. */
    submit() {
        this._autoSubmit();
    }

    /** Reset all digits and re-arm the form. */
    reset() {
        this.submitting = false;
        this.digitTargets.forEach(i => { i.value = ''; i.readOnly = false; });
        if (this.hasSubmitTarget) this.submitTarget.disabled = false;
        if (this.hasHiddenTarget) this.hiddenTarget.value = '';
        if (this.hasDigitTarget && this.digitTargets.length > 0) {
            this.digitTargets[0].focus();
        }
    }

    // ---------------------------------------------------------------------
    // Private
    // ---------------------------------------------------------------------

    _focusNextOf(input) {
        const index = this.digitTargets.indexOf(input);
        const next = this.digitTargets[index + 1];
        if (next) next.focus();
    }

    /** Copy current digit values into the hidden input and lock the UI. */
    _sync() {
        if (this.hasHiddenTarget) {
            this.hiddenTarget.value = this.value;
        }
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = this.submitting;
            this.submitTarget.dataset.submitting = this.submitting;
        }
        this.digitTargets.forEach(i => { i.readOnly = this.submitting; });
    }

    /**
     * Single chokepoint for programmatic form submission. Idempotent;
     * subsequent calls while a submit is in flight are a no-op, which is
     * what prevents the duplicate POST when the user presses ENTER while
     * the previous submission's navigation is still pending.
     */
    _autoSubmit() {
        if (this.submitting) return;
        this.submitting = true;
        this._sync();
        this.element.submit();
    }
}
