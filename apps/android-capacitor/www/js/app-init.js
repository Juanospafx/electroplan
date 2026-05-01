import { initOfflineDB, syncWithServer, isOnline, getLastSyncTime, getOfflineProjects } from './offline-db.js';
import { StatusBar, Style } from '@capacitor/status-bar';
import { SplashScreen } from '@capacitor/splash-screen';
import { Network } from '@capacitor/network';

const SERVER = 'https://androidelectro.brightronix.net/electroplan';

async function bootstrap() {
  try {
    await StatusBar.setStyle({ style: Style.Dark });
    await StatusBar.setBackgroundColor({ color: '#0b1120' });
  } catch {}

  // CRÍTICO: esperar a que el DB esté listo antes de todo
  await initOfflineDB();

  const online = await isOnline();
  if (online) {
    // Sincronizar en background, no bloquear navegación
    syncWithServer()
      .then(() => console.log('[Sync] completado'))
      .catch(e => console.warn('[Sync] falló:', e));

    // Ir al servidor
    window.location.href = SERVER;
  } else {
    // DB ya está lista, mostrar datos cacheados
    await showOfflineMode();
  }

  // Monitor de red nativo
  Network.addListener('networkStatusChange', async (status) => {
    if (!status.connected) {
      window.location.href = 'https://localhost/index.html';
    } else {
      await initOfflineDB();
      await syncWithServer();
      window.location.href = SERVER;
    }
  });

  try { await SplashScreen.hide(); } catch {}
}

// Hacer showOfflineMode async
async function showOfflineMode() {
  const offline = document.getElementById('offline-screen');
  const loading = document.getElementById('loading-screen');
  if (offline) offline.style.display = 'flex';
  if (loading) loading.style.display = 'none';
  await loadOfflineProjects(); // await aquí es crítico
}

async function loadOfflineProjects() {
  const container = document.getElementById('offline-projects');
  const syncEl = document.getElementById('last-sync-time');

  try {
    const projects = await getOfflineProjects();
    const lastSync = await getLastSyncTime();
    console.log('[Offline] Proyectos cargados:', projects.length);

    if (syncEl && lastSync) {
      syncEl.textContent = 'Last synced: ' + new Date(lastSync).toLocaleString();
    }

    if (!container) return;

    if (!projects.length) {
      container.innerHTML = '<p style="color:#94a3b8;text-align:center;">' +
        'No cached data. Connect to internet first.</p>';
      return;
    }

    container.innerHTML = projects.map(p => `
      <div class="offline-project-card">
        <i class="fas fa-folder-open" style="color:#fb5a3a;font-size:1.4rem;"></i>
        <div>
          <div style="font-weight:600;color:#fff;">${p.name}</div>
          <div style="font-size:0.75rem;color:#94a3b8;">
            ${p.status || 'Active'}
          </div>
        </div>
      </div>
    `).join('');
  } catch(e) {
    console.error('[Offline] Error cargando proyectos:', e);
    if (container) {
      container.innerHTML = '<p style="color:#ef4444;text-align:center;">' +
        'Error loading cached data.</p>';
    }
  }
}

document.addEventListener('deviceready', bootstrap, false);
if (document.readyState === 'complete') bootstrap(); else window.addEventListener('load', bootstrap);
