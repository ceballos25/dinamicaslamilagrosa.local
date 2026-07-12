(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const fd = new FormData();
        fd.append('action', 'config_publica');
        fetch('/front/ajax/web.ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data?.images) return;
                const img = data.data.images;
                const pick = (k) => img[k]?.url || img[k]?.fallback || '';
                const logoUrl = pick('logo') || (window.location.origin + '/assets/images/logo-transparent.png');
                document.querySelectorAll('[data-site-logo], [data-site-logo-white], [data-site-logo-nav], [data-site-logo-footer]').forEach((el) => {
                    el.src = logoUrl;
                });
                const fav = pick('favicon') || logoUrl;
                document.querySelectorAll('link[data-site-favicon]').forEach((l) => { l.href = fav; });
            })
            .catch(() => {});
    });
})();
