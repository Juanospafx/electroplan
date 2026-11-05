<?php
// upload.php - Página dedicada V3
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';

// Obtener Proyectos para el dropdown
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY created_at DESC")->fetchAll();
$project_id_pre = $_GET['project_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Plan | Brightronix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .upload-card { background: #1e293b; border: 1px solid #475569; padding: 36px; border-radius: 12px; width: 100%; max-width: 540px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .form-control, .form-select { background: #0f172a; border: 1px solid #475569; color: white; }
        .form-control:focus, .form-select:focus { background: #0f172a; color: white; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.25); }
        .progress-box { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; margin-bottom: 20px; }
        .text-accent { color: #38bdf8; }
    </style>
</head>
<body>

<div class="upload-card">
    <h3 class="fw-bold mb-4 text-center">Upload Files</h3>
    
    <form id="uploadForm">
        <input type="hidden" name="action" value="upload_file">
        
        <div class="mb-3">
            <label class="form-label text-muted small">Project</label>
            <select name="project_id" id="projectSelect" class="form-select" required>
                <option value="">Select Project...</option>
                <?php foreach($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $project_id_pre == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label text-muted small">Files (PDF, Images, etc. · max 10GB each)</label>
            <input type="file" id="fileInput" name="files[]" class="form-control" multiple required>
        </div>

        <div id="uploadProgressSection" class="progress-box d-none">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small text-white fw-bold" id="aggregateCount">0 / 0 files</span>
                <span class="small text-muted" id="overallPercent">0%</span>
            </div>
            <div class="progress mb-2" style="height: 6px;">
                <div id="overallProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-1 mt-3">
                <span class="small text-accent text-truncate me-2" id="activeFilename" style="max-width: 320px;">Preparing...</span>
                <span class="small text-muted" id="filePercent">0%</span>
            </div>
            <div class="progress mb-1" style="height: 6px;">
                <div id="fileProgressBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div id="uploadResults" class="mb-3 d-none"></div>

        <div class="d-grid gap-2">
            <button type="submit" id="submitBtn" class="btn btn-primary fw-bold">Upload Files</button>
            <a href="../pages/index.php" class="btn btn-outline-light border-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const MAX_FILE_SIZE = 10737418240; // 10 GB in bytes (10 * 1024 * 1024 * 1024)

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function uploadSingleFile(file, projectId, onProgress) {
    return new Promise((resolve, reject) => {
        const fd = new FormData();
        fd.append('action', 'upload_file');
        fd.append('project_id', projectId);
        fd.append('file', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php', true);

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable && typeof onProgress === 'function') {
                const pct = Math.round((e.loaded / e.total) * 100);
                onProgress(pct);
            }
        });

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText || '{}');
                    resolve(data);
                } catch (e) {
                    reject(new Error('Invalid server response'));
                }
            } else {
                reject(new Error(`Server returned HTTP ${xhr.status}`));
            }
        };

        xhr.onerror = () => reject(new Error('Network error during upload'));
        xhr.ontimeout = () => reject(new Error('Upload timed out'));

        xhr.send(fd);
    });
}

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('fileInput');
    const projectSelect = document.getElementById('projectSelect');
    const projectId = projectSelect.value;
    const files = Array.from(fileInput.files || []);

    if (!projectId) {
        alert('Please select a project.');
        return;
    }

    if (files.length === 0) {
        alert('Please select at least one file.');
        return;
    }

    const submitBtn = document.getElementById('submitBtn');
    const progressSection = document.getElementById('uploadProgressSection');
    const activeFilename = document.getElementById('activeFilename');
    const filePercent = document.getElementById('filePercent');
    const fileProgressBar = document.getElementById('fileProgressBar');
    const aggregateCount = document.getElementById('aggregateCount');
    const overallPercent = document.getElementById('overallPercent');
    const overallProgressBar = document.getElementById('overallProgressBar');
    const resultsContainer = document.getElementById('uploadResults');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading...';
    progressSection.classList.remove('d-none');
    resultsContainer.classList.add('d-none');
    resultsContainer.innerHTML = '';

    const total = files.length;
    let completed = 0;
    let successCount = 0;
    const errors = [];

    const updateAggregateUI = (doneCount) => {
        const pct = Math.round((doneCount / total) * 100);
        aggregateCount.textContent = `${doneCount} / ${total} files`;
        overallPercent.textContent = `${pct}%`;
        overallProgressBar.style.width = `${pct}%`;
    };

    updateAggregateUI(0);

    for (let i = 0; i < files.length; i++) {
        const file = files[i];

        activeFilename.textContent = file.name;
        filePercent.textContent = '0%';
        fileProgressBar.style.width = '0%';

        if (file.size > MAX_FILE_SIZE) {
            errors.push({ filename: file.name, message: 'File exceeds 10GB limit' });
            completed++;
            updateAggregateUI(completed);
            continue;
        }

        try {
            const res = await uploadSingleFile(file, projectId, (pct) => {
                filePercent.textContent = `${pct}%`;
                fileProgressBar.style.width = `${pct}%`;
            });

            if (res && res.status === 'success') {
                successCount++;
                filePercent.textContent = '100%';
                fileProgressBar.style.width = '100%';
            } else {
                errors.push({ filename: file.name, message: (res && res.msg) ? res.msg : 'Upload failed' });
            }
        } catch (err) {
            errors.push({ filename: file.name, message: err.message || 'Upload failed' });
        }

        completed++;
        updateAggregateUI(completed);
    }

    activeFilename.textContent = 'Batch completed';
    filePercent.textContent = '100%';
    fileProgressBar.style.width = '100%';

    submitBtn.disabled = false;
    submitBtn.textContent = 'Upload Files';

    if (errors.length === 0) {
        resultsContainer.className = 'alert alert-success small mb-3';
        resultsContainer.textContent = `${successCount} file(s) uploaded successfully! Redirecting...`;
        resultsContainer.classList.remove('d-none');
        setTimeout(() => {
            window.location.href = '../pages/index.php?project_id=' + encodeURIComponent(projectId);
        }, 1200);
    } else {
        resultsContainer.className = 'alert alert-warning small mb-3';
        let html = `<strong>Completed with ${errors.length} error(s):</strong><ul class="mb-0 mt-1 ps-3">`;
        errors.forEach(e => {
            html += `<li><strong>${escapeHtml(e.filename)}</strong>: ${escapeHtml(e.message)}</li>`;
        });
        html += `</ul>`;
        if (successCount > 0) {
            html += `<div class="mt-2 text-success">✓ ${successCount} file(s) uploaded successfully.</div>`;
        }
        resultsContainer.innerHTML = html;
        resultsContainer.classList.remove('d-none');
    }
});
</script>
</body>
</html>
