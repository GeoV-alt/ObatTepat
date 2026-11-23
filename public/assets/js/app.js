(function () {
    const root = document.documentElement;
    const toggleButtons = document.querySelectorAll('[data-theme-toggle]');

    function syncThemeLabels() {
        const dark = root.getAttribute('data-theme') === 'dark';
        toggleButtons.forEach((btn) => {
            btn.textContent = dark ? 'Tema terang' : 'Tema gelap';
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        });
    }

    syncThemeLabels();

    toggleButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('obattepat-theme', next);
            syncThemeLabels();
            document.dispatchEvent(new CustomEvent('obattepat:themechange', { detail: { theme: next } }));
        });
    });

    const shell = document.querySelector('[data-shell]');
    const sidebar = document.querySelector('[data-sidebar]');
    const openBtn = document.querySelector('[data-drawer-open]');
    const closeBtn = document.querySelector('[data-drawer-close]');
    const backdrop = document.querySelector('[data-drawer-backdrop]');
    const mobileQuery = window.matchMedia('(max-width: 1024px)');

    function syncNavAria() {
        if (!shell || !sidebar || !openBtn) {
            return;
        }
        const expanded = mobileQuery.matches
            ? sidebar.classList.contains('is-open')
            : !shell.classList.contains('is-collapsed');
        openBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function openNavigation() {
        if (!shell || !sidebar || !openBtn || !backdrop) {
            return;
        }
        if (mobileQuery.matches) {
            sidebar.classList.add('is-open');
            backdrop.hidden = false;
        } else {
            shell.classList.remove('is-collapsed');
            backdrop.hidden = true;
        }
        syncNavAria();
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeNavigation(returnFocus) {
        if (!shell || !sidebar || !openBtn || !backdrop) {
            return;
        }
        if (mobileQuery.matches) {
            sidebar.classList.remove('is-open');
            backdrop.hidden = true;
        } else {
            shell.classList.add('is-collapsed');
            backdrop.hidden = true;
        }
        syncNavAria();
        if (returnFocus) {
            openBtn.focus();
        }
    }

    if (shell && sidebar && openBtn && backdrop) {
        syncNavAria();

        openBtn.addEventListener('click', openNavigation);

        if (closeBtn) {
            closeBtn.addEventListener('click', () => closeNavigation(true));
        }

        backdrop.addEventListener('click', () => closeNavigation(true));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobileQuery.matches && sidebar.classList.contains('is-open')) {
                closeNavigation(true);
            }
        });

        mobileQuery.addEventListener('change', () => {
            sidebar.classList.remove('is-open');
            backdrop.hidden = true;
            syncNavAria();
        });
    }

    document.addEventListener('click', (event) => {
        const dismiss = event.target.closest('[data-dismiss-flash]');
        if (dismiss) {
            const box = dismiss.closest('.flash');
            if (box) {
                box.remove();
            }
        }

        const copyBtn = event.target.closest('[data-copy-text]');
        if (copyBtn && navigator.clipboard) {
            const text = copyBtn.getAttribute('data-copy-text') || '';
            navigator.clipboard.writeText(text).then(() => {
                window.ObatTepat.toast('Kode undangan disalin.');
            });
        }

        const pwToggle = event.target.closest('[data-password-toggle]');
        if (pwToggle) {
            const targetId = pwToggle.getAttribute('data-password-toggle');
            const input = targetId ? document.getElementById(targetId) : null;
            if (input) {
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                pwToggle.textContent = showing ? 'Lihat' : 'Sembunyikan';
                pwToggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
            }
        }

        const clickedDetails = event.target.closest('details.skip-disclosure');
        document.querySelectorAll('details.skip-disclosure[open]').forEach((details) => {
            if (details !== clickedDetails) {
                details.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('details[open]').forEach((details) => {
                details.removeAttribute('open');
            });
        }
    });

    const freqInput = document.querySelector('[data-freq-input]');
    const intervalInput = document.querySelector('[data-interval-input]');
    if (freqInput && intervalInput) {
        freqInput.addEventListener('change', () => {
            const freq = parseInt(freqInput.value, 10);
            if (freq >= 1 && freq <= 12) {
                intervalInput.value = String(Math.max(1, Math.floor(24 / freq)));
            }
        });
    }

    document.querySelectorAll('[data-symptom-pick]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById('symptom_name');
            if (!input) {
                return;
            }
            input.value = btn.getAttribute('data-symptom-pick') || '';
            document.querySelectorAll('[data-symptom-pick]').forEach((other) => other.classList.remove('is-picked'));
            btn.classList.add('is-picked');
            input.focus();
        });
    });

    const logDateInput = document.querySelector('[data-symptom-date]');
    const scheduleSelect = document.querySelector('[data-symptom-schedules]');
    const medSelect = document.querySelector('[data-symptom-medication]');
    if (logDateInput && scheduleSelect) {
        const base = document.body.getAttribute('data-base-url') || '';
        logDateInput.addEventListener('change', () => {
            const date = logDateInput.value;
            if (!date) {
                return;
            }
            scheduleSelect.disabled = true;
            fetch(base + '/api/patient_meds_by_date.php?date=' + encodeURIComponent(date), {
                headers: { 'X-Requested-With': 'fetch' },
            })
                .then((res) => res.json())
                .then((payload) => {
                    scheduleSelect.innerHTML = '';
                    const emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.textContent = payload.items && payload.items.length > 0
                        ? 'Pilih jadwal konsumsi pada tanggal ini (opsional)'
                        : 'Tidak ada jadwal obat pada tanggal ini';
                    scheduleSelect.appendChild(emptyOpt);

                    (payload.items || []).forEach((item) => {
                        const opt = document.createElement('option');
                        opt.value = String(item.id);
                        opt.setAttribute('data-med-id', String(item.medication_id));
                        opt.textContent = item.label;
                        scheduleSelect.appendChild(opt);
                    });
                })
                .catch(() => {
                    window.ObatTepat.toast('Gagal memuat jadwal untuk tanggal tersebut.', true);
                })
                .finally(() => {
                    scheduleSelect.disabled = false;
                });
        });

        scheduleSelect.addEventListener('change', () => {
            const selected = scheduleSelect.options[scheduleSelect.selectedIndex];
            const medId = selected ? selected.getAttribute('data-med-id') : '';
            if (medSelect && medId) {
                medSelect.value = medId;
            }
        });
    }

    window.ObatTepat = {
        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') || '' : '';
        },
        toast(message, isError) {
            const region = document.querySelector('[data-toast-region]');
            if (!region) {
                return;
            }
            const item = document.createElement('div');
            item.className = isError ? 'toast toast-error' : 'toast';
            item.textContent = message;
            region.appendChild(item);
            window.setTimeout(() => item.remove(), 4200);
        },
        async postForm(form) {
            const endpoint = form.getAttribute('action') || window.location.href;
            const body = new FormData(form);
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'fetch',
                    'X-CSRF-Token': this.csrfToken(),
                },
                body,
            });
            const raw = await response.text();
            let data = null;
            try {
                data = JSON.parse(raw);
            } catch (parseError) {
                throw new Error('Respons server tidak valid (HTTP ' + response.status + ').');
            }
            if (!response.ok || !data || !data.ok) {
                throw new Error((data && data.message) || 'Permintaan gagal diproses.');
            }
            return data;
        },
    };
})();
