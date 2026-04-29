<?php
// index.php - Bulk Upload UI with Dropzone.js (files + folders)
// PHP 8.1+
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bulk Upload (Dropzone)</title>
  <link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css">
  <style>
    body { font-family: Arial, sans-serif; background:#0f172a; color:#e2e8f0; margin:0; padding:24px; }
    .wrap { max-width: 1000px; margin: 0 auto; }
    h1 { margin-top:0; }
    .card { background:#111827; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:16px; }
    .btn { background:#f97316; border:none; color:#fff; padding:10px 14px; border-radius:8px; cursor:pointer; }
    .btn:hover { background:#ea580c; }
    #folderInput { display:none; }
    .muted { color:#94a3b8; font-size:13px; }
    .log { max-height:220px; overflow:auto; background:#020617; border:1px solid #334155; border-radius:8px; padding:10px; font-size:13px; }
    .ok { color:#22c55e; }
    .err { color:#ef4444; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Bulk Upload (Dropzone.js)</h1>
  <div class="card">
    <button class="btn" id="pickFolderBtn">Select Folder</button>
    <input type="file" id="folderInput" multiple webkitdirectory directory>
    <p class="muted">You can drag files/folders into the drop area or select a folder manually.</p>
  </div>

  <div class="card">
    <form action="upload.php" class="dropzone" id="bulkDropzone">
      <div class="dz-message">Drop files or folders here</div>
    </form>
  </div>

  <div class="card">
    <strong>Status log</strong>
    <div id="statusLog" class="log"></div>
  </div>
</div>

<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
<script>
Dropzone.autoDiscover = false;

const logEl = document.getElementById('statusLog');
function addLog(msg, cls='') {
  const line = document.createElement('div');
  if (cls) line.className = cls;
  line.textContent = msg;
  logEl.appendChild(line);
  logEl.scrollTop = logEl.scrollHeight;
}

const dz = new Dropzone('#bulkDropzone', {
  url: 'upload.php',
  paramName: 'file',
  uploadMultiple: false, // send each file individually
  parallelUploads: 2,
  timeout: 0,
  createImageThumbnails: false,
  addRemoveLinks: true,
  acceptedFiles: '.pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff,.tif,.heic,.doc,.docx,.xls,.xlsx,.xlsm,.csv,.ppt,.pptx,.dwg,.dxf,.rvt,.ifc,.zip,.rar',
  init: function () {
    this.on('addedfile', function(file) {
      addLog('Queued: ' + (file.fullPath || file.name));
    });
    this.on('sending', function(file, xhr, formData) {
      const relPath = file.fullPath || file.webkitRelativePath || file.name;
      formData.append('relative_path', relPath);
    });
    this.on('uploadprogress', function(file, progress) {
      addLog(`Uploading ${file.name}: ${Math.round(progress)}%`);
    });
    this.on('success', function(file, response) {
      if (response && response.status === 'success') {
        addLog('OK: ' + (response.saved_as || file.name), 'ok');
      } else {
        addLog('Error: ' + (response?.message || 'unknown error') + ' (' + file.name + ')', 'err');
      }
    });
    this.on('error', function(file, errorMessage) {
      const msg = typeof errorMessage === 'string' ? errorMessage : (errorMessage?.message || 'upload failed');
      addLog('Failed: ' + file.name + ' -> ' + msg, 'err');
    });
  }
});

// Folder picker using webkitdirectory, then enqueue each file with full relative path
const folderInput = document.getElementById('folderInput');
document.getElementById('pickFolderBtn').addEventListener('click', () => folderInput.click());

folderInput.addEventListener('change', (e) => {
  const files = Array.from(e.target.files || []);
  if (!files.length) return;

  addLog(`Detected ${files.length} file(s) from folder selection.`);
  files.forEach((f) => {
    // Important: preserve relative path
    f.fullPath = f.webkitRelativePath || f.name;
    dz.addFile(f);
  });

  folderInput.value = '';
});
</script>
</body>
</html>
