(function () {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-checkin-form], [data-refill-form]');
        if (!form || !window.ObatTepat) {
            return;
        }

        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Menyimpan...';
        }

        try {
            const result = await window.ObatTepat.postForm(form);
            window.ObatTepat.toast(result.message || 'Perubahan tersimpan.');
            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            window.ObatTepat.toast(error.message || 'Gagal menyimpan perubahan.', true);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
})();
