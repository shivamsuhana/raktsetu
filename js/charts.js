// ============================================================
//  RaktSetu — charts.js
//  Lightweight canvas-based charts for admin analytics
//  No external dependencies
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Bar Chart ────────────────────────────────────────────
    function drawBarChart(canvasId, labels, data, color = '#dc2626') {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx    = canvas.getContext('2d');
        const W      = canvas.width  = canvas.offsetWidth;
        const H      = canvas.height = 180;
        const pad    = { top: 20, right: 16, bottom: 36, left: 36 };
        const maxVal = Math.max(...data, 1);
        const barW   = Math.floor((W - pad.left - pad.right) / labels.length) - 6;
        const chartH = H - pad.top - pad.bottom;

        ctx.clearRect(0, 0, W, H);

        // Grid lines
        ctx.strokeStyle = getComputedStyle(document.documentElement)
            .getPropertyValue('--color-border-tertiary') || 'rgba(0,0,0,.08)';
        ctx.lineWidth = 0.5;
        [0.25, 0.5, 0.75, 1].forEach(pct => {
            const y = pad.top + chartH * (1 - pct);
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(W - pad.right, y);
            ctx.stroke();
            ctx.fillStyle = '#9ca3af';
            ctx.font = '10px Inter, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(Math.round(maxVal * pct), pad.left - 4, y + 3);
        });

        // Bars
        labels.forEach((label, i) => {
            const val    = data[i] || 0;
            const barH   = (val / maxVal) * chartH;
            const x      = pad.left + i * (barW + 6);
            const y      = pad.top + chartH - barH;

            // Bar fill
            ctx.fillStyle = color;
            ctx.globalAlpha = 0.85;
            // Rounded top corners
            const r = Math.min(4, barH / 2);
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.lineTo(x + barW - r, y);
            ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
            ctx.lineTo(x + barW, y + barH);
            ctx.lineTo(x, y + barH);
            ctx.lineTo(x, y + r);
            ctx.quadraticCurveTo(x, y, x + r, y);
            ctx.closePath();
            ctx.fill();
            ctx.globalAlpha = 1;

            // Value on top
            if (val > 0) {
                ctx.fillStyle = '#374151';
                ctx.font = '10px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(val, x + barW / 2, y - 4);
            }

            // Label
            ctx.fillStyle = '#9ca3af';
            ctx.font = '10px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(label, x + barW / 2, H - 8);
        });
    }

    // ── Horizontal Bar Chart ─────────────────────────────────
    function drawHorizBarChart(canvasId, labels, data, color = '#dc2626') {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx    = canvas.getContext('2d');
        const W      = canvas.width  = canvas.offsetWidth;
        const rowH   = 26;
        const H      = canvas.height = labels.length * rowH + 20;
        const maxVal = Math.max(...data, 1);
        const labelW = 36;
        const barArea = W - labelW - 40;

        ctx.clearRect(0, 0, W, H);

        labels.forEach((label, i) => {
            const val  = data[i] || 0;
            const barW = (val / maxVal) * barArea;
            const y    = 10 + i * rowH;

            // Label
            ctx.fillStyle = '#111827';
            ctx.font = '11px Inter, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(label, labelW, y + rowH / 2 + 4);

            // Background track
            ctx.fillStyle = '#f3f4f6';
            ctx.beginPath();
            ctx.roundRect(labelW + 6, y + 4, barArea, rowH - 10, 4);
            ctx.fill();

            // Bar
            ctx.fillStyle = color;
            ctx.globalAlpha = 0.85;
            ctx.beginPath();
            ctx.roundRect(labelW + 6, y + 4, Math.max(barW, 4), rowH - 10, 4);
            ctx.fill();
            ctx.globalAlpha = 1;

            // Value
            ctx.fillStyle = '#374151';
            ctx.font = '10px Inter, sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(val, labelW + 6 + barW + 6, y + rowH / 2 + 4);
        });
    }

    // ── Line Chart ───────────────────────────────────────────
    function drawLineChart(canvasId, labels, datasets) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx    = canvas.getContext('2d');
        const W      = canvas.width  = canvas.offsetWidth;
        const H      = canvas.height = 200;
        const pad    = { top: 24, right: 16, bottom: 36, left: 36 };
        const chartW = W - pad.left - pad.right;
        const chartH = H - pad.top - pad.bottom;

        const allVals = datasets.flatMap(d => d.data);
        const maxVal  = Math.max(...allVals, 1);

        ctx.clearRect(0, 0, W, H);

        // Grid
        ctx.strokeStyle = 'rgba(0,0,0,.06)';
        ctx.lineWidth = 0.5;
        [0, 0.25, 0.5, 0.75, 1].forEach(pct => {
            const y = pad.top + chartH * (1 - pct);
            ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W - pad.right, y); ctx.stroke();
            ctx.fillStyle = '#9ca3af'; ctx.font = '10px Inter,sans-serif'; ctx.textAlign = 'right';
            ctx.fillText(Math.round(maxVal * pct), pad.left - 4, y + 3);
        });

        datasets.forEach(({ data, color, label }) => {
            const step = chartW / Math.max(labels.length - 1, 1);

            // Area fill
            ctx.beginPath();
            data.forEach((val, i) => {
                const x = pad.left + i * step;
                const y = pad.top + chartH - (val / maxVal) * chartH;
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            ctx.lineTo(pad.left + (data.length - 1) * step, pad.top + chartH);
            ctx.lineTo(pad.left, pad.top + chartH);
            ctx.closePath();
            ctx.fillStyle = color;
            ctx.globalAlpha = 0.12;
            ctx.fill();
            ctx.globalAlpha = 1;

            // Line
            ctx.beginPath();
            data.forEach((val, i) => {
                const x = pad.left + i * step;
                const y = pad.top + chartH - (val / maxVal) * chartH;
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.lineJoin = 'round';
            ctx.stroke();

            // Dots
            data.forEach((val, i) => {
                const x = pad.left + i * step;
                const y = pad.top + chartH - (val / maxVal) * chartH;
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fillStyle = 'white';
                ctx.fill();
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.stroke();
            });
        });

        // X-axis labels
        labels.forEach((label, i) => {
            const x = pad.left + i * (chartW / Math.max(labels.length - 1, 1));
            ctx.fillStyle = '#9ca3af';
            ctx.font = '10px Inter,sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(label, x, H - 8);
        });
    }

    // ── Donut Chart ──────────────────────────────────────────
    function drawDonutChart(canvasId, labels, data, colors) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx   = canvas.getContext('2d');
        const W     = canvas.width  = canvas.offsetWidth;
        const H     = canvas.height = 200;
        const cx    = W / 2, cy = H / 2;
        const r     = Math.min(W, H) / 2 - 20;
        const inner = r * 0.55;
        const total = data.reduce((a, b) => a + b, 0) || 1;

        ctx.clearRect(0, 0, W, H);

        let startAngle = -Math.PI / 2;
        data.forEach((val, i) => {
            const sweep = (val / total) * 2 * Math.PI;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, startAngle, startAngle + sweep);
            ctx.closePath();
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();
            startAngle += sweep;
        });

        // Donut hole
        ctx.beginPath();
        ctx.arc(cx, cy, inner, 0, Math.PI * 2);
        ctx.fillStyle = 'white';
        ctx.fill();

        // Center text
        ctx.fillStyle = '#111827';
        ctx.font = '700 22px Inter,sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(total, cx, cy + 7);
        ctx.fillStyle = '#9ca3af';
        ctx.font = '11px Inter,sans-serif';
        ctx.fillText('total', cx, cy + 22);
    }

    // ── Init all charts if their containers exist ─────────────
    // These are picked up by admin.php

    // Weekly bar chart
    const wkLabels = window.chartData?.weeklyLabels || [];
    const wkReqs   = window.chartData?.weeklyReqs   || [];
    const wkFulf   = window.chartData?.weeklyFulfilled || [];
    if (wkLabels.length) {
        drawBarChart('chartWeeklyReqs',  wkLabels, wkReqs,  '#dc2626');
        drawBarChart('chartWeeklyFulf',  wkLabels, wkFulf,  '#16a34a');
        drawLineChart('chartWeeklyLine', wkLabels, [
            { data: wkReqs,  color: '#dc2626', label: 'Requests' },
            { data: wkFulf,  color: '#16a34a', label: 'Fulfilled' },
        ]);
    }

    // Blood type horizontal bar
    const btLabels = window.chartData?.btLabels || [];
    const btCounts = window.chartData?.btCounts || [];
    if (btLabels.length) {
        drawHorizBarChart('chartByType', btLabels, btCounts, '#dc2626');
    }

    // Fulfillment donut
    if (window.chartData?.fulfillmentData) {
        const fd = window.chartData.fulfillmentData;
        drawDonutChart('chartFulfillment', ['Fulfilled','Open','Closed'], fd, ['#16a34a','#dc2626','#9ca3af']);
    }

    // ── Redraw on resize (responsive) ────────────────────────
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            document.dispatchEvent(new Event('DOMContentLoaded'));
        }, 200);
    });

    // Export for use in inline scripts
    window.RaktCharts = { drawBarChart, drawHorizBarChart, drawLineChart, drawDonutChart };
});
