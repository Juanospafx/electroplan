import { initOfflineDB, syncWithServer, watchNetwork, isOnline, getLastSyncTime, getOfflineProjects } from './offline-db.js';
import { StatusBar, Style } from '@capacitor/status-bar';
import { SplashScreen } from '@capacitor/splash-screen';

const SERVER = 'https://androidelectro.brightronix.net/electroplan';

async function bootstrap() {
  try {
    await StatusBar.setStyle({ style: Style.Dark });
    await StatusBar.setBackgroundColor({ color: '#0b1120' });
  } catch {}

  await initOfflineDB();

  const online = await isOnline();
  if (online) {
    syncWithServer().catch(() => {});
    window.location.href = SERVER;
  } else {
    showOfflineMode();
  }

  watchNetwork(async () => {
    await syncWithServer();
    hideOfflineBanner();
  }, () => {
    showOfflineBanner();
  });

  try { await SplashScreen.hide(); } catch {}
}

function showOfflineMode() {
  const offline = document.getElementById('offline-screen');
  const loading = document.getElementById('loading-screen');
  if (offline) offline.style.display = 'flex';
  if (loading) loading.style.display = 'none';
  loadOfflineProjects();
}

async function loadOfflineProjects() {
  const projects = await getOfflineProjects();
  const lastSync = await getLastSyncTime();
  const container = document.getElementById('offline-projects');
  const syncEl = document.getElementById('last-sync-time');

  if (syncEl && lastSync) syncEl.textContent = `Last synced: ${new Date(lastSync).toLocaleString()}`;
  if (!container) return;

  if (!projects.length) {
    container.innerHTML = '<p style="color:#94a3b8; text-align:center;">No cached data. Connect to internet first.</p>';
    return;
  }

  container.innerHTML = projects.map(p => `
    <div class="offline-project-card">
      <i class="fas fa-folder-open" style="color:#fb5a3a; font-size:1.4rem;"></i>
      <div>
        <div style="font-weight:600; color:#fff;">${p.name}</div>
        <div style="font-size:0.75rem; color:#94a3b8;">${p.status || 'Active'}</div>
      </div>
    </div>
  `).join('');
}

function showOfflineBanner() {
  let banner = document.getElementById('offline-banner');
  if (!banner) {
    banner = document.createElement('div');
    banner.id = 'offline-banner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;background:rgba(239,68,68,0.9);color:white;text-align:center;padding:10px;font-size:0.85rem;font-weight:600;';
    banner.textContent = '⚠ Offline — Showing cached data. Some features unavailable.';
    document.body.prepend(banner);
  }
  banner.style.display = 'block';
}

function hideOfflineBanner() {
  const banner = document.getElementById('offline-banner');
  if (banner) banner.style.display = 'none';
}

document.addEventListener('deviceready', bootstrap, false);
if (document.readyState === 'complete') bootstrap(); else window.addEventListener('load', bootstrap);
