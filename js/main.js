// ============================================================
//  RaktSetu — main.js
//  Event handling · DOM manipulation · DHTML
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── 1. Live ticker auto-scroll ─────────────────────────
    const ticker = document.getElementById('liveTicker');
    if (ticker) {
        const items = ticker.querySelectorAll('.ticker-item');
        let idx = 0;
        if (items.length > 1) {
            setInterval(() => {
                items[idx].classList.remove('ticker-visible');
                items[idx].classList.add('ticker-hidden');
                idx = (idx + 1) % items.length;
                items[idx].classList.remove('ticker-hidden');
                items[idx].classList.add('ticker-visible');
            }, 3500);
        }
    }

    // ── 2. Live board polling (every 30 sec) ───────────────
    if (document.getElementById('liveBoard')) {
        pollLiveBoard();
        setInterval(pollLiveBoard, 30000);
    }

    // ── 3. Inventory bar animations ────────────────────────
    document.querySelectorAll('.inv-bar[data-units]').forEach(bar => {
        const units = parseInt(bar.dataset.units);
        const max   = parseInt(bar.dataset.max || 60);
        const pct   = Math.min(100, Math.round((units / max) * 100));
        // Delayed to trigger CSS transition
        setTimeout(() => { bar.style.height = pct + '%'; }, 100);
        // Color coding
        if (units <= 5)       bar.style.background = '#ef4444';
        else if (units <= 15) bar.style.background = '#f97316';
        else                  bar.style.background = '#22c55e';
    });

    // ── 4. Progress bar animations ─────────────────────────
    document.querySelectorAll('.progress-fill[data-pct]').forEach(bar => {
        setTimeout(() => { bar.style.width = bar.dataset.pct + '%'; }, 150);
    });

    // ── 5. Blood type filter buttons ───────────────────────
    document.querySelectorAll('.filter-pill[data-bt]').forEach(btn => {
        btn.addEventListener('click', () => {
            // Toggle active state
            document.querySelectorAll('.filter-pill[data-bt]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const selectedBT = btn.dataset.bt;

            // Show/hide request cards
            document.querySelectorAll('[data-card-bt]').forEach(card => {
                const match = selectedBT === 'all' || card.dataset.cardBt === selectedBT;
                card.style.display = match ? '' : 'none';
            });

            // Show/hide donor cards
            document.querySelectorAll('[data-donor-bt]').forEach(card => {
                const match = selectedBT === 'all' || card.dataset.donorBt === selectedBT;
                card.style.display = match ? '' : 'none';
            });

            updateResultCount();
        });
    });

    // ── 6. Urgency filter ──────────────────────────────────
    document.querySelectorAll('.filter-pill[data-urg]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-pill[data-urg]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const selected = btn.dataset.urg;
            document.querySelectorAll('[data-card-urg]').forEach(card => {
                const match = selected === 'all' || card.dataset.cardUrg === selected;
                card.style.display = match ? '' : 'none';
            });
            updateResultCount();
        });
    });

    // ── 7. Donor search (real-time, client-side) ───────────
    const searchInput = document.getElementById('donorSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            const q = searchInput.value.toLowerCase().trim();
            document.querySelectorAll('[data-donor-name]').forEach(card => {
                const name = card.dataset.donorName.toLowerCase();
                const city = (card.dataset.donorCity || '').toLowerCase();
                card.style.display = (!q || name.includes(q) || city.includes(q)) ? '' : 'none';
            });
            updateResultCount();
        }, 250));
    }

    // ── 8. Character counter for textareas ─────────────────
    document.querySelectorAll('textarea[data-maxlen]').forEach(ta => {
        const maxLen  = parseInt(ta.dataset.maxlen);
        const counter = ta.nextElementSibling;
        if (!counter || !counter.classList.contains('char-counter')) return;

        ta.addEventListener('input', () => {
            const remaining = maxLen - ta.value.length;
            counter.textContent = `${ta.value.length} / ${maxLen}`;
            counter.classList.toggle('warn', remaining < 30);
            if (ta.value.length > maxLen) ta.value = ta.value.slice(0, maxLen);
        });
    });

    // ── 9. Sticky navbar shadow on scroll ──────────────────
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 10
                ? '0 2px 16px rgba(0,0,0,.12)'
                : '0 1px 3px rgba(0,0,0,.06)';
        }, { passive: true });
    }

    // ── 10. Eligibility countdown timer ────────────────────
    const cdEl = document.getElementById('countdown');
    if (cdEl) {
        const targetDate = new Date(cdEl.dataset.target);
        function tick() {
            const diff = targetDate - new Date();
            if (diff <= 0) { cdEl.textContent = 'Eligible now!'; return; }
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            cdEl.textContent = `${d}d ${h}h ${m}m`;
        }
        tick();
        setInterval(tick, 60000);
    }

    // ── 11. Respond button — AJAX donor response ───────────
    document.querySelectorAll('.btn-respond[data-req-id]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const reqId = btn.dataset.reqId;
            btn.disabled = true;
            btn.textContent = 'Sending…';
            try {
                const res  = await fetch('api/respond-donor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ request_id: reqId })
                });
                const data = await res.json();
                if (data.success) {
                    btn.textContent  = '✓ Response sent';
                    btn.style.background = '#16a34a';
                } else {
                    btn.disabled    = false;
                    btn.textContent = 'Respond';
                    alert(data.message || 'Could not send response.');
                }
            } catch {
                btn.disabled    = false;
                btn.textContent = 'Respond';
                alert('Network error. Please try again.');
            }
        });
    });

});

// ── Live board poll ──────────────────────────────────────────
async function pollLiveBoard() {
    try {
        const res  = await fetch('api/fetch-requests.php');
        const data = await res.json();
        if (!data.requests) return;

        const board = document.getElementById('liveBoard');
        const existingIds = new Set([...board.querySelectorAll('[data-req-id]')].map(el => el.dataset.reqId));

        data.requests.forEach(req => {
            if (!existingIds.has(String(req.id))) {
                // New request — inject card at top
                const card = buildRequestCard(req);
                board.insertAdjacentHTML('afterbegin', card);
                animateNew(board.querySelector(`[data-req-id="${req.id}"]`));
            }
            // Update progress for existing cards
            const existEl = board.querySelector(`[data-req-id="${req.id}"] .progress-fill`);
            if (existEl) {
                const pct = Math.min(100, Math.round((req.units_fulfilled / req.units_needed) * 100));
                existEl.style.width = pct + '%';
            }
        });

        // Update last-updated timestamp
        const ts = document.getElementById('lastUpdated');
        if (ts) ts.textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit' });

    } catch (e) { /* silent — user still sees last state */ }
}

function buildRequestCard(req) {
    const urgLabels = { critical: 'Critical', high: 'High', normal: 'Normal' };
    const pct = Math.min(100, Math.round((req.units_fulfilled / req.units_needed) * 100));
    return `
    <div class="request-card ${req.urgency} fade-in" data-req-id="${req.id}" data-card-bt="${req.blood_type}" data-card-urg="${req.urgency}">
        <div class="req-header">
            <div class="req-badges">
                <span class="badge badge-${req.urgency}">${urgLabels[req.urgency]}</span>
                <span class="bt-pill">${req.blood_type}</span>
                <span class="text-muted" style="font-size:12px">${req.time_ago}</span>
            </div>
            <div class="req-units">${req.units_fulfilled}/${req.units_needed} <span>units</span></div>
        </div>
        <p class="req-note">${req.notes || '—'}</p>
        <div class="req-meta">
            <span>📍 ${req.hospital_name || req.city}</span>
            <span>${req.donors_nearby} donors nearby</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill ${req.urgency}" data-pct="${pct}" style="width:${pct}%"></div>
        </div>
        <button class="btn btn-red btn-sm btn-full mt-2 btn-respond" data-req-id="${req.id}"
                style="margin-top:12px">I can donate — Respond</button>
    </div>`;
}

function animateNew(el) {
    if (!el) return;
    el.style.border = '2px solid var(--red)';
    setTimeout(() => { el.style.border = ''; }, 2500);
}

function updateResultCount() {
    const counter = document.getElementById('resultCount');
    if (!counter) return;
    const visible = document.querySelectorAll('[data-card-bt]:not([style*="none"]), [data-donor-bt]:not([style*="none"])').length;
    counter.textContent = visible;
}

// ── Utility: debounce ────────────────────────────────────────
function debounce(fn, delay) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// ── AJAX donor search (used on donor-search page) ────────────
const ajaxSearchInput = document.getElementById('donorSearchAjax');
if (ajaxSearchInput) {
    ajaxSearchInput.addEventListener('input', debounce(async () => {
        const q    = ajaxSearchInput.value.trim();
        const bt   = document.querySelector('.filter-pill[data-bt].active')?.dataset.bt || '';
        const grid = document.getElementById('donorGrid');
        if (!grid) return;

        try {
            const url  = `api/search-donors.php?q=${encodeURIComponent(q)}&bt=${encodeURIComponent(bt === 'all' ? '' : bt)}`;
            const res  = await fetch(url);
            const data = await res.json();
            if (!data.donors) return;

            const count = document.getElementById('resultCount');
            if (count) count.textContent = data.count;

            if (data.donors.length === 0) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-500)"><div style="font-size:36px;margin-bottom:10px">🔍</div><p>No donors found matching your search.</p></div>';
                return;
            }

            grid.innerHTML = data.donors.map(d => `
            <div class="donor-card fade-in" style="padding:16px 18px;flex-direction:column;align-items:flex-start;gap:12px">
                <div style="display:flex;align-items:center;gap:12px;width:100%">
                    <div class="donor-avatar" style="width:48px;height:48px;font-size:15px">${d.initials}</div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:6px">
                            <span class="donor-name">${d.name}</span>
                            ${d.is_eligible ? '<span style="color:var(--success);font-size:12px">✓</span>' : ''}
                        </div>
                        <div class="donor-sub">📍 ${d.city}${d.state ? ', ' + d.state : ''}</div>
                    </div>
                    <span class="bt-pill">${d.blood_type}</span>
                </div>
                <div style="display:flex;gap:8px;width:100%;font-size:12px">
                    <div style="flex:1;background:var(--gray-50);border-radius:6px;padding:8px;text-align:center">
                        <div style="font-weight:700;color:var(--dark)">${d.donations}</div>
                        <div style="color:var(--gray-500)">donations</div>
                    </div>
                    <div style="flex:1;background:${d.is_eligible ? '#f0fdf4' : '#fff7ed'};border-radius:6px;padding:8px;text-align:center">
                        <div style="font-weight:700;color:${d.is_eligible ? 'var(--success)' : 'var(--warning)'}">
                            ${d.is_eligible ? 'Ready' : 'Cooling'}
                        </div>
                        <div style="color:var(--gray-500)">status</div>
                    </div>
                    <div style="flex:1;background:#f3e8ff;border-radius:6px;padding:8px;text-align:center">
                        <div style="font-weight:700;color:#7e22ce">${d.badge}</div>
                        <div style="color:#9ca3af">rank</div>
                    </div>
                </div>
            </div>`).join('');
        } catch {
            // Silent fail — keep showing existing results
        }
    }, 300));
}

// ── Canvas roundRect polyfill (Chrome <99, Firefox <112) ────
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, r) {
        r = Math.min(r, w / 2, h / 2);
        this.beginPath();
        this.moveTo(x + r, y);
        this.arcTo(x + w, y,     x + w, y + h, r);
        this.arcTo(x + w, y + h, x,     y + h, r);
        this.arcTo(x,     y + h, x,     y,     r);
        this.arcTo(x,     y,     x + w, y,     r);
        this.closePath();
        return this;
    };
}
