import { CapacitorSQLite, SQLiteConnection } from '@capacitor-community/sqlite';
import { Network } from '@capacitor/network';
import { Filesystem, Directory } from '@capacitor/filesystem';

const sqlite = new SQLiteConnection(CapacitorSQLite);
const SERVER = (window.APP_BASE_URL || `${window.location.origin}/electroplan`).replace(/\/$/, '');
let db = null;

export async function initOfflineDB() {
  db = await sqlite.createConnection('electroplan_android', false, 'no-encryption', 1, false);
  await db.open();
  await db.execute(`
    CREATE TABLE IF NOT EXISTS projects (
      id INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      status TEXT,
      address TEXT,
      notes TEXT,
      company_name TEXT,
      contact_name TEXT,
      date_started TEXT,
      date_finished TEXT,
      updated_at TEXT
    );
    CREATE TABLE IF NOT EXISTS folders (
      id INTEGER PRIMARY KEY,
      project_id INTEGER NOT NULL,
      parent_id INTEGER,
      name TEXT NOT NULL,
      depth INTEGER DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS files (
      id INTEGER PRIMARY KEY,
      project_id INTEGER NOT NULL,
      folder_id INTEGER,
      filename TEXT NOT NULL,
      filepath TEXT,
      file_type TEXT,
      uploaded_at TEXT,
      local_path TEXT
    );
    CREATE TABLE IF NOT EXISTS sync_meta (
      key TEXT PRIMARY KEY,
      value TEXT
    );
  `);
}

export async function syncWithServer() {
  const { connected } = await Network.getStatus();
  if (!connected) return false;
  try {
    const response = await fetch(`${SERVER}/api/api.php`, {
      method: 'POST',
      credentials: 'include',
      body: new URLSearchParams({ action: 'sync_offline_data' })
    });
    const data = await response.json();
    if (data.status !== 'success') {
      console.warn('[OfflineDB Android] sync_offline_data failed:', data.msg || data);
      return false;
    }

    await db.run('DELETE FROM projects');
    await db.run('DELETE FROM folders');
    await db.run('DELETE FROM files');

    for (const p of data.projects || []) {
      await db.run(
        'INSERT OR REPLACE INTO projects (id,name,status,address,notes,company_name,contact_name,date_started,date_finished,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [p.id, p.name, p.status || '', p.address || '', p.notes || '', p.company_name || '', p.contact_name || '', p.date_started || '', p.date_finished || '', p.updated_at || '']
      );
    }
    for (const f of data.folders || []) {
      await db.run('INSERT OR REPLACE INTO folders (id,project_id,parent_id,name,depth) VALUES (?,?,?,?,?)', [f.id, f.project_id, f.parent_id || null, f.name, f.depth || 0]);
    }
    for (const fi of data.files || []) {
      await db.run('INSERT OR REPLACE INTO files (id,project_id,folder_id,filename,filepath,file_type,uploaded_at,local_path) VALUES (?,?,?,?,?,?,?,COALESCE((SELECT local_path FROM files WHERE id=?),NULL))',
        [fi.id, fi.project_id, fi.folder_id || null, fi.filename, fi.filepath || '', fi.file_type || '', fi.uploaded_at || '', fi.id]);
    }

    await db.run('INSERT OR REPLACE INTO sync_meta (key, value) VALUES (?,?)', ['last_sync', data.synced_at || new Date().toISOString()]);
    return true;
  } catch (e) {
    console.warn('[OfflineDB Android] Sync error:', e?.message || e);
    return false;
  }
}

export async function getOfflineProjects() {
  const r = await db.query('SELECT * FROM projects ORDER BY name ASC');
  return r.values || [];
}
export async function getOfflineFolders(projectId) {
  const r = await db.query('SELECT * FROM folders WHERE project_id = ? ORDER BY depth ASC, name ASC', [projectId]);
  return r.values || [];
}
export async function getOfflineFiles(projectId, folderId = null) {
  const sql = folderId ? 'SELECT * FROM files WHERE project_id = ? AND folder_id = ? ORDER BY filename ASC' : 'SELECT * FROM files WHERE project_id = ? ORDER BY uploaded_at DESC';
  const params = folderId ? [projectId, folderId] : [projectId];
  const r = await db.query(sql, params);
  return r.values || [];
}
export async function getLastSyncTime() {
  const r = await db.query('SELECT value FROM sync_meta WHERE key = ?', ['last_sync']);
  return r.values?.[0]?.value || null;
}

export async function cachePdfLocally(fileId, filepath) {
  const { connected } = await Network.getStatus();
  if (!connected) return null;
  try {
    const response = await fetch(`${SERVER}/${filepath}`, { credentials: 'include' });
    if (!response.ok) return null;
    const blob = await response.blob();
    const base64 = await new Promise((resolve) => {
      const reader = new FileReader();
      reader.onloadend = () => resolve(reader.result.split(',')[1]);
      reader.readAsDataURL(blob);
    });
    const fileName = `pdf_${fileId}.pdf`;
    await Filesystem.writeFile({ path: fileName, data: base64, directory: Directory.Cache });
    await db.run('UPDATE files SET local_path = ? WHERE id = ?', [fileName, fileId]);
    return fileName;
  } catch {
    return null;
  }
}

export async function getLocalPdfUrl(fileId) {
  const r = await db.query('SELECT local_path FROM files WHERE id = ?', [fileId]);
  const localPath = r.values?.[0]?.local_path;
  if (!localPath) return null;
  try {
    const { uri } = await Filesystem.getUri({ path: localPath, directory: Directory.Cache });
    return uri;
  } catch {
    return null;
  }
}

export async function isOnline() {
  const { connected } = await Network.getStatus();
  return connected;
}

export function watchNetwork(onOnline, onOffline) {
  Network.addListener('networkStatusChange', ({ connected }) => {
    if (connected) onOnline(); else onOffline();
  });
}

// TODO(next phase): Add UI trigger/button "Descargar para offline" for PDFs
// that calls cachePdfLocally(fileId, filepath) and shows local cache status.
