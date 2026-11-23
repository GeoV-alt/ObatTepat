(function () {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-reminder-form]');
        if (!form || !window.ObatTepat) {
            return;
        }

        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Mengirim...';
        }

        try {
            const result = await window.ObatTepat.postForm(form);
            window.ObatTepat.toast(result.message || 'Pengingat terkirim.');
            window.setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            window.ObatTepat.toast(error.message || 'Pengingat gagal dikirim.', true);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
})();
