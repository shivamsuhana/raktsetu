// ============================================================
//  RaktSetu — validate.js
//  Client-side form validation · DOM manipulation
// ============================================================

// ── Registration validation ──────────────────────────────────
function validateRegister() {
    let valid = true;

    const fields = [
        { id: 'r_name',    msg: 'Name must be at least 2 characters.',   test: v => v.trim().length >= 2 },
        { id: 'r_email',   msg: 'Enter a valid email address.',           test: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) },
        { id: 'r_city',    msg: 'City is required.',                      test: v => v.trim().length > 0 },
        { id: 'r_pass',    msg: 'Password must be at least 8 characters.',test: v => v.length >= 8 },
        { id: 'r_confirm', msg: 'Passwords do not match.',                test: v => v === document.getElementById('r_pass')?.value },
    ];

    fields.forEach(({ id, msg, test }) => {
        const el  = document.getElementById(id);
        const err = el?.nextElementSibling;
        if (!el) return;

        const ok = test(el.value);
        el.classList.toggle('error', !ok);
        if (err && err.classList.contains('form-error')) {
            err.textContent = ok ? '' : msg;
            err.classList.toggle('show', !ok);
        }
        if (!ok) valid = false;
    });

    // Blood type required for donors
    const role = document.getElementById('r_role')?.value;
    if (role === 'donor') {
        const bt  = document.getElementById('r_bt');
        const err = bt?.nextElementSibling;
        const ok  = bt?.value !== '';
        if (bt) bt.classList.toggle('error', !ok);
        if (err && err.classList.contains('form-error')) {
            err.textContent = ok ? '' : 'Blood type is required for donors.';
            err.classList.toggle('show', !ok);
        }
        if (!ok) valid = false;
    }

    return valid;
}

// ── Contact form validation ──────────────────────────────────
function validateContact() {
    let valid = true;
    const fields = [
        { name: 'name',    msg: 'Your name is required.',           test: v => v.trim().length >= 2 },
        { name: 'email',   msg: 'Enter a valid email address.',     test: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) },
        { name: 'message', msg: 'Message must be at least 20 characters.', test: v => v.trim().length >= 20 },
    ];

    fields.forEach(({ name, msg, test }) => {
        const el  = document.querySelector(`[name="${name}"]`);
        const err = document.getElementById(`err_${name}`);
        if (!el) return;
        const ok  = test(el.value);
        el.classList.toggle('error', !ok);
        if (err) { err.textContent = ok ? '' : msg; err.classList.toggle('show', !ok); }
        if (!ok) valid = false;
    });

    return valid;
}

// ── Post-request form validation ─────────────────────────────
function validateRequest() {
    let valid = true;
    const fields = [
        { name: 'blood_type',   msg: 'Select a blood type.',      test: v => v !== '' },
        { name: 'units_needed', msg: 'Enter number of units.',    test: v => parseInt(v) >= 1 && parseInt(v) <= 20 },
        { name: 'hospital_id',  msg: 'Select a hospital.',        test: v => v !== '' },
    ];

    fields.forEach(({ name, msg, test }) => {
        const el  = document.querySelector(`[name="${name}"]`);
        const err = document.getElementById(`err_${name}`);
        if (!el) return;
        const ok  = test(el.value);
        el.classList.toggle('error', !ok);
        if (err) { err.textContent = ok ? '' : msg; err.classList.toggle('show', !ok); }
        if (!ok) valid = false;
    });
    return valid;
}

// ── Real-time field validation on blur ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.form-control').forEach(el => {
        el.addEventListener('blur', () => {
            if (el.value.trim() && el.classList.contains('error')) {
                el.classList.remove('error');
                const err = el.nextElementSibling;
                if (err && err.classList.contains('form-error')) {
                    err.classList.remove('show');
                }
            }
        });
    });
});
