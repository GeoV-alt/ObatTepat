(function () {
    const host = document.querySelector('[data-adherence-chart]');
    if (!host) {
        return;
    }

    const endpoint = host.getAttribute('data-endpoint') || '';
    const detailBox = document.querySelector('[data-chart-detail]');
    let lastSeries = [];

    function themeColors() {
        const styles = getComputedStyle(document.documentElement);
        return {
            primary: styles.getPropertyValue('--primary').trim() || '#0e5a4c',
            accent: styles.getPropertyValue('--accent').trim() || '#a34a06',
            danger: styles.getPropertyValue('--danger').trim() || '#b3261e',
            border: styles.getPropertyValue('--border').trim() || '#d6d2c7',
            muted: styles.getPropertyValue('--muted').trim() || '#55605b',
            surfaceAlt: styles.getPropertyValue('--surface-alt').trim() || '#efece3',
        };
    }

    function render(series) {
        lastSeries = series;
        const hasAnyDue = series.some((item) => item.total_due > 0);
        if (!hasAnyDue) {
            host.innerHTML = '<div class="empty-state"><p class="empty-title">Belum ada jadwal jatuh tempo pada periode ini</p><p>Grafik kepatuhan harian akan muncul otomatis setelah ada jadwal minum obat yang jatuh tempo.</p></div>';
            return;
        }

        const colors = themeColors();
        const width = 680;
        const height = 220;
        const padLeft = 38;
        const padRight = 14;
        const padTop = 18;
        const padBottom = 34;
        const chartW = width - padLeft - padRight;
        const chartH = height - padTop - padBottom;
        const count = series.length;
        const slotW = chartW / count;
        const barW = Math.max(8, Math.min(34, slotW * 0.62));
        const labelStep = count > 16 ? 4 : count > 10 ? 2 : 1;

        const lines = [0, 50, 100].map((pct) => {
            const y = padTop + chartH - (pct / 100) * chartH;
            return '<line x1="' + padLeft + '" y1="' + y + '" x2="' + (width - padRight) + '" y2="' + y + '" stroke="' + colors.border + '" stroke-dasharray="3 3" />' +
                '<text x="' + (padLeft - 6) + '" y="' + (y + 4) + '" text-anchor="end" font-size="11" fill="' + colors.muted + '">' + pct + '%</text>';
        }).join('');

        const bars = series.map((item, index) => {
            const cx = padLeft + (index + 0.5) * slotW;
            const x = cx - barW / 2;
            const rate = item.rate === null ? 0 : item.rate;
            const h = item.total_due > 0 ? Math.max(4, (rate / 100) * chartH) : 4;
            const y = padTop + chartH - h;
            let fill = colors.surfaceAlt;
            if (item.total_due > 0) {
                fill = rate >= 80 ? colors.primary : rate >= 50 ? colors.accent : colors.danger;
            }
            const showLabel = index % labelStep === 0 || index === count - 1;
            const labelSvg = showLabel
                ? '<text x="' + cx + '" y="' + (height - 10) + '" text-anchor="middle" font-size="11" fill="' + colors.muted + '">' + item.label + '</text>'
                : '';
            const summary = item.total_due > 0
                ? item.label + ': ' + item.rate + '% (' + item.taken + ' dari ' + item.total_due + ' jadwal diminum)'
                : item.label + ': tidak ada jadwal jatuh tempo';

            return '<g tabindex="0" role="img" aria-label="' + summary + '" data-bar-summary="' + summary + '">' +
                '<rect x="' + x.toFixed(1) + '" y="' + y.toFixed(1) + '" width="' + barW.toFixed(1) + '" height="' + h.toFixed(1) + '" rx="3" fill="' + fill + '"><title>' + summary + '</title></rect>' +
                labelSvg +
                '</g>';
        }).join('');

        host.innerHTML = '<svg class="chart-svg" viewBox="0 0 ' + width + ' ' + height + '" role="img" aria-label="Grafik persentase kepatuhan minum obat harian">' + lines + bars + '</svg>';

        host.querySelectorAll('[data-bar-summary]').forEach((node) => {
            const updateDetail = () => {
                if (detailBox) {
                    detailBox.textContent = node.getAttribute('data-bar-summary') || '';
                }
            };
            node.addEventListener('mouseenter', updateDetail);
            node.addEventListener('focus', updateDetail);
            node.addEventListener('click', updateDetail);
        });
    }

    async function load() {
        host.innerHTML = '<p class="muted">Memuat grafik kepatuhan...</p>';
        try {
            const res = await fetch(endpoint, { headers: { 'X-Requested-With': 'fetch' } });
            const payload = await res.json();
            if (!res.ok || !payload.ok) {
                throw new Error(payload.message || 'Gagal memuat data grafik.');
            }
            render(payload.series || []);
        } catch (error) {
            host.innerHTML = '<div class="notice-box notice-danger"><p class="notice-title">Grafik gagal dimuat</p><p>' + (error.message || 'Coba muat ulang halaman.') + '</p></div>';
        }
    }

    document.addEventListener('obattepat:themechange', () => {
        if (lastSeries.length > 0) {
            render(lastSeries);
        }
    });

    load();
})();
