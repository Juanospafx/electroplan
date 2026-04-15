<!-- Custom App Alert Modal -->
<div class="modal fade" id="customAppAlert" tabindex="-1" aria-hidden="true" style="z-index: 100000;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-secondary shadow-lg" style="background: var(--bg-card, #242a38); border-radius: var(--radius-box, 20px);">
            <div class="modal-body text-center p-4">
                <div id="appAlertIcon" class="mb-3" style="font-size: 3.5rem;"></div>
                <h5 id="appAlertTitle" class="fw-bold text-white mb-2">Notification</h5>
                <p id="appAlertMessage" class="text-gray mb-4" style="font-size: 0.95rem;"></p>
                <div class="d-flex justify-content-center gap-2" id="appAlertButtons">
                    <!-- Botones generados dinámicamente por JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let appConfirmCallback = null;

function appAlert(message, title = 'Alert', type = 'info') {
    document.getElementById('appAlertTitle').textContent = title;
    document.getElementById('appAlertMessage').textContent = message;
    
    const iconEl = document.getElementById('appAlertIcon');
    if (type === 'error') { iconEl.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i>'; }
    else if (type === 'warning') { iconEl.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>'; }
    else if (type === 'success') { iconEl.innerHTML = '<i class="fas fa-check-circle text-success"></i>'; }
    else { iconEl.innerHTML = '<i class="fas fa-info-circle text-info"></i>'; }

    document.getElementById('appAlertButtons').innerHTML = '<button type="button" class="btn btn-main px-4 rounded-pill" style="min-width: 100px;" data-bs-dismiss="modal">OK</button>';

    new bootstrap.Modal(document.getElementById('customAppAlert')).show();
}

function appConfirm(message, title = 'Confirm Action', callback) {
    document.getElementById('appAlertTitle').textContent = title;
    document.getElementById('appAlertMessage').textContent = message;
    
    document.getElementById('appAlertIcon').innerHTML = '<i class="fas fa-question-circle text-warning"></i>';
    appConfirmCallback = callback;

    document.getElementById('appAlertButtons').innerHTML = `
        <button type="button" class="btn btn-outline-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-main px-4 rounded-pill" onclick="executeAppConfirm()">Confirm</button>
    `;

    new bootstrap.Modal(document.getElementById('customAppAlert')).show();
}

function executeAppConfirm() {
    const modalEl = document.getElementById('customAppAlert');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if(modal) modal.hide();
    if(typeof appConfirmCallback === 'function') {
        appConfirmCallback();
        appConfirmCallback = null;
    }
}
</script>