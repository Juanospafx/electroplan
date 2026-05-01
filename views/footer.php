</div>
<?php include_once __DIR__ . '/../funciones/alerts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/electroplan/assets/js/pwa-register.js"></script>
<script>
(function() {
  if (!window.Capacitor || !window.Capacitor.isNativePlatform || !window.Capacitor.isNativePlatform()) return;

  import('https://cdn.jsdelivr.net/npm/@capacitor/network@6/dist/index.js')
    .then(({ Network }) => {
      Network.addListener('networkStatusChange', ({ connected }) => {
        const banner = document.getElementById('offline-banner');
        if (!connected) {
          if (!banner) {
            const b = document.createElement('div');
            b.id = 'offline-banner';
            b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;background:rgba(239,68,68,0.9);color:white;text-align:center;padding:10px;font-size:0.85rem;font-weight:600;';
            b.textContent = '⚠ Offline — Some features unavailable';
            document.body.prepend(b);
          }
        } else {
          if (banner) banner.remove();
        }
      });

      Network.getStatus().then(({ connected }) => {
        if (connected) {
          fetch('/api/api.php', {
            method: 'POST',
            credentials: 'include',
            body: new URLSearchParams({ action: 'sync_offline_data' })
          }).catch(() => {});
        }
      });
    })
    .catch(() => {});
})();
</script>
</body>
</html>
