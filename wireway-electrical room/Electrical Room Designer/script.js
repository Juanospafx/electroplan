/* --- CONSTANTES GLOBALES --- */
// Room Designer Constants
const RD_DISCONNECT_DATA = { "200": 20, "400": 26, "600": 30, "800": 32, "1000": 34, "1200": 36, "1600": 40, "2000": 44, "2500": 48 };
const RD_PANEL_DATA = { "200": 22, "225": 24, "250": 26, "400": 30, "600": 36, "800": 42 };
const RD_LV_DATA = { "PLYWOOD_4": { width: 48, label: "Ply 4'" }, "PLYWOOD_8": { width: 96, label: "Ply 8'" }, "TTB": { width: 20, label: "TTB" }, "CUSTOM_LV": { width: 24, label: "Custom LV" }, "TRANSFORMER": { width: 30, label: "Transformer" }, "WIREWAY": { width: 12, label: "Wireway" }, "SPACE": { width: 12, label: "Space" } };
const RD_DISCONNECT_AMPS_SORTED = Object.keys(RD_DISCONNECT_DATA).map(Number).sort((a,b) => a-b);
const RD_PANEL_AMPS_SORTED = Object.keys(RD_PANEL_DATA).map(Number).sort((a,b) => a-b);
const RD_AMP_OPTIONS = [200, 400, 600, 800, 1000, 1200, 1600, 2000, 2500];
const RD_CT_CABINET_OPTIONS = [600, 800, 1000, 1200];

// Wireway Calculator Constants
const WC_DISCONNECT_DATA = { "200": 20, "400": 26, "600": 30, "800": 32, "1000": 34, "1200": 36, "1600": 40, "2000": 44, "2500": 48 };
const WC_PANEL_DATA = { "200": 22, "225": 24, "250": 26, "400": 30, "600": 36, "800": 42 };
const WC_DISCONNECT_AMPS_SORTED = Object.keys(WC_DISCONNECT_DATA).map(Number).sort((a,b) => a-b);
const WC_PANEL_AMPS_SORTED = Object.keys(WC_PANEL_DATA).map(Number).sort((a,b) => a-b);
const WC_AMP_OPTIONS = [200, 400, 600, 800, 1000, 1200, 1600, 2000, 2500];
const WC_CT_CABINET_OPTIONS = [600, 800]; // Simplificado a 600A y 800A+
const WC_CONDUIT_SIZES = [2.0, 2.5, 3.0, 3.5, 4.0, 5.0, 6.0];
const WC_DEFAULT_SPACING = 3; 

/* --- ESTADO GLOBAL DE LA APLICACIÓN --- */
let appState = {
    currentView: 'ROOM_DESIGNER',
    theme: 'light',
    roomDesigner: {
        walls: [
            { id: 1, name: 'North', active: true, width: 120, height: 96, items: [] },
            { id: 2, name: 'East',  active: false, width: 120, height: 96, items: [] },
            { id: 3, name: 'South', active: false, width: 120, height: 96, items: [] },
            { id: 4, name: 'West',  active: false, width: 120, height: 96, items: [] }
        ],
        activeWallId: 1,
    },
    wirewayCalculator: {
        isFeederEnabled: true,
        feeder: { 
            type: 'DIRECT', 
            conduitSize: 4.0, 
            sets: 1, 
            amps: 200,
            startGap: WC_DEFAULT_SPACING,
            betweenGap: WC_DEFAULT_SPACING,
            gapAfter: WC_DEFAULT_SPACING
        },
        loads: [],
    }
};

/* --- INICIALIZACIÓN Y CONTROLADORES GLOBALES --- */
document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    // Initialize default view (Room Designer)
    rd_renderWallControls();
    rd_calculateAndRender();
    rd_renderExportButton();

    // Prepare other view (Wireway Calculator)
    wc_renderFeederInputs();
    wc_calculateAndRender();
    wc_renderExportButton();

    // Set initial view correctly
    switchView('ROOM_DESIGNER', true);

    // Close modal on outside click
    document.addEventListener('click', (e) => {
        const modal = document.getElementById('customModal');
        if(modal && e.target === modal) closeModal();
    });

    // Add horizontal scroll with mouse wheel for WC visualizer
    const wcSvgContainer = document.getElementById('wc-svgContainer');
    if (wcSvgContainer) {
        wcSvgContainer.addEventListener('wheel', (event) => {
            if (event.deltaY !== 0 && wcSvgContainer.scrollWidth > wcSvgContainer.clientWidth) {
                event.preventDefault();
                wcSvgContainer.scrollLeft += event.deltaY;
            }
        }, { passive: false });
    }
});

function switchView(viewName, isInitial = false) {
    if (!isInitial && appState.currentView === viewName) return;
    appState.currentView = viewName;

    const isRD = viewName === 'ROOM_DESIGNER';

    document.getElementById('rd-sidebar-content').style.display = isRD ? 'block' : 'none';
    document.getElementById('wc-sidebar-content').style.display = isRD ? 'none' : 'block';
    document.getElementById('rd-main-content').style.display = isRD ? 'block' : 'none';
    document.getElementById('wc-main-content').style.display = isRD ? 'none' : 'block';

    document.getElementById('btn-view-rd').classList.toggle('active', isRD);
    document.getElementById('btn-view-wc').classList.toggle('active', !isRD);

    if (!isInitial) {
        if (isRD) {
            rd_calculateAndRender();
        } else {
            wc_calculateAndRender();
        }
    }
}

function toggleTheme() {
    const newTheme = appState.theme === 'light' ? 'dark' : 'light';
    applyTheme(newTheme);
}

function applyTheme(themeName) {
    appState.theme = themeName;
    document.documentElement.setAttribute('data-theme', themeName);
    localStorage.setItem('theme', themeName);
    
    const btn = document.getElementById('btnThemeToggle');
    if(btn) {
        btn.innerHTML = themeName === 'light' 
            ? '<i class="fa-solid fa-moon"></i>' 
            : '<i class="fa-solid fa-sun"></i>';
    }

    // Redraw both SVGs because colors change
    rd_calculateAndRender();
    wc_calculateAndRender();
}

/* --- MODAL SYSTEM --- */
function showModal(title, message) {
    const modal = document.getElementById('customModal');
    const titleEl = document.getElementById('modalTitle');
    const msgEl = document.getElementById('modalMessage');
    
    if(modal && titleEl && msgEl) {
        titleEl.innerText = title;
        msgEl.innerText = message;
        modal.classList.add('show');
    }
}

function closeModal() {
    const modal = document.getElementById('customModal');
    if(modal) modal.classList.remove('show');
}


// ===================================================================================
// |                                                                                 |
// |                         ROOM DESIGNER LOGIC (rd_ prefix)                        |
// |                                                                                 |
// ===================================================================================

/* --- GESTIÓN DE PAREDES (RD) --- */
function rd_toggleWall(id) {
    const wall = appState.roomDesigner.walls.find(w => w.id === id);
    if (wall) {
        wall.active = !wall.active;
        if (!wall.active && appState.roomDesigner.activeWallId === id) {
            const firstActive = appState.roomDesigner.walls.find(w => w.active);
            appState.roomDesigner.activeWallId = firstActive ? firstActive.id : null;
        } else if (wall.active && !appState.roomDesigner.activeWallId) {
            appState.roomDesigner.activeWallId = id;
        }
        rd_renderWallControls();
        rd_calculateAndRender();
    }
}

function rd_setActiveWall(id) {
    const wall = appState.roomDesigner.walls.find(w => w.id === id);
    if (wall && wall.active) {
        appState.roomDesigner.activeWallId = id;
        rd_renderWallControls();
        rd_calculateAndRender();
    }
}

function rd_updateWallDim(id, field, value) {
    const wall = appState.roomDesigner.walls.find(w => w.id === id);
    if (wall) {
        wall[field] = parseFloat(value) || 0;
        rd_calculateAndRender();
    }
}

function rd_renderWallControls() {
    const activatorContainer = document.getElementById('rd-wallActivatorContainer');
    if (!activatorContainer) return;
    activatorContainer.innerHTML = appState.roomDesigner.walls.map(w => `
        <button class="wall-btn ${w.active ? 'active' : ''}" onclick="rd_toggleWall(${w.id})">
            ${w.active ? '<i class="fa-solid fa-check"></i>' : ''} ${w.name}
        </button>
    `).join('');

    const dimContainer = document.getElementById('rd-wallDimensionsContainer');
    dimContainer.innerHTML = appState.roomDesigner.walls.filter(w => w.active).map(w => `
        <div style="margin-top: 0.75rem; padding: 0.75rem; background: var(--bg-input); border-radius: 0.5rem; border: 1px solid ${appState.roomDesigner.activeWallId === w.id ? 'var(--accent-primary)' : 'var(--border-subtle)'}; cursor: pointer;" onclick="rd_setActiveWall(${w.id})">
            <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 800; color: ${appState.roomDesigner.activeWallId === w.id ? 'var(--accent-primary)' : 'var(--text-muted)'};">${w.name.toUpperCase()} WALL ${appState.roomDesigner.activeWallId === w.id ? '(SELECTED)' : ''}</span>
            </div>
            <div class="grid-2">
                <div>
                    <label class="input-label">Width (ft)</label>
                    <input type="number" step="0.5" min="0" class="input-std" value="${(w.width / 12).toFixed(2)}" onchange="rd_updateWallDim(${w.id}, 'width', this.value * 12)" onclick="event.stopPropagation()">
                </div>
                <div>
                    <label class="input-label">Height (ft)</label>
                    <input type="number" step="0.5" min="0" class="input-std" value="${(w.height / 12).toFixed(2)}" onchange="rd_updateWallDim(${w.id}, 'height', this.value * 12)" onclick="event.stopPropagation()">
                </div>
            </div>
        </div>
    `).join('');

    const activeLabel = document.getElementById('rd-activeWallLabel');
    if(activeLabel) {
        const activeWall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
        activeLabel.innerText = activeWall ? `${activeWall.name} Wall Selected` : 'No Wall Selected';
    }
}

/* --- GESTIÓN DE CARGAS (RD) --- */
function rd_addLoad(type) {
    if (!appState.roomDesigner.activeWallId) {
        showModal("Action Required", "Please select a wall first.");
        return;
    }
    const wall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
    if (!wall) return;

    if (wall.items.length >= 25) {
        showModal("Limit Reached", "Wall item limit reached (25 items).");
        return;
    }
    const totalItems = appState.roomDesigner.walls.reduce((acc, w) => acc + w.items.length, 0);
    if (totalItems >= 100) {
        showModal("System Limit", "Global item limit reached (100 items).");
        return;
    }
    if (wall.items.length > 0) {
        const calculatedItems = rd_calculateWallItems(wall);
        const totalUsed = calculatedItems.reduce((acc, item, idx) => acc + item.width + item.spacingAfter + (idx === 0 ? item.spacingBefore : 0), 0);
        if (totalUsed > wall.width) {
            showModal("Space Overflow", "Overflow limit reached. Cannot add more items to a full wall.");
            return;
        }
    }

    let initialWidth = 20;
    if (RD_LV_DATA[type]) initialWidth = RD_LV_DATA[type].width;
    else if (type === 'PANELBOARD') initialWidth = RD_PANEL_DATA[200];
    else if (type === 'DISCONNECT') initialWidth = RD_DISCONNECT_DATA[200];
    else if (type === 'METER') initialWidth = 9.6;
    else if (type === 'CT_CABINET') initialWidth = 32;

    wall.items.push({ 
        id: Date.now().toString() + Math.random().toString(36).substr(2, 5), 
        type, 
        amps: 200, 
        meterSize: 'SMALL',
        isCustom: (type === 'CUSTOM_LV' || type === 'TRANSFORMER' || type === 'WIREWAY' || type === 'SPACE'),
        customWidth: initialWidth,
        description: (type === 'SPACE' ? 'Reserved' : ''),
        spacingBefore: 0,
        spacingAfter: 3
    });
    rd_calculateAndRender();
}

function rd_removeLoad(id) {
    const wall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
    if (wall) {
        wall.items = wall.items.filter(l => l.id !== id);
        rd_calculateAndRender();
    }
}

function rd_updateLoad(id, field, value) {
    const wall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
    if (wall) {
        const item = wall.items.find(l => l.id === id);
        if (item) {
            if (field === 'meterSize') item.meterSize = value;
            else if (field === 'amps') item.amps = parseInt(value);
            else if (field === 'customWidth') item.customWidth = parseFloat(value);
            else if (field === 'spacingAfter') item.spacingAfter = parseFloat(value);
            else if (field === 'spacingBefore') item.spacingBefore = parseFloat(value);
            else if (field === 'description') item.description = value;
            rd_calculateAndRender();
        }
    }
}

function rd_toggleCustomMode(id) {
    const wall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
    if (wall) {
        const item = wall.items.find(l => l.id === id);
        if (item) {
            item.isCustom = !item.isCustom;
            rd_calculateAndRender();
        }
    }
}

function rd_renderLoadList() {
    const container = document.getElementById('rd-loadListContainer');
    if (!container) return;
    
    if (!appState.roomDesigner.activeWallId) {
        container.innerHTML = '<div style="padding:2rem; text-align:center; color:var(--text-muted);">No Wall Selected</div>';
        return;
    }

    const wall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
    if (!wall || wall.items.length === 0) {
        container.innerHTML = `
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                <i class="fa-solid fa-layer-group" style="font-size: 2.5rem; margin-bottom: 1rem; opacity:0.3;"></i>
                <span style="font-weight: 700; font-size: 0.9rem; display:block;">EMPTY WALL</span>
                <span style="font-size: 0.8rem;">Add items to ${wall.name} Wall</span>
            </div>`;
        return;
    }

    container.innerHTML = wall.items.map((l, index) => {
        let bgIcon = 'var(--bg-input)', colorIcon = 'var(--text-muted)';
        if (l.type === 'PANELBOARD') { bgIcon = 'var(--blue-100)'; colorIcon = 'var(--blue-600)'; }
        else if (l.type === 'DISCONNECT') { bgIcon = 'var(--emerald-100)'; colorIcon = 'var(--emerald-500)'; }
        else if (l.type === 'METER') { bgIcon = 'var(--amber-100)'; colorIcon = 'var(--amber-500)'; }
        else if (l.type === 'CT_CABINET') { bgIcon = '#cffafe'; colorIcon = '#0891b2'; }
        else if (l.type === 'TRANSFORMER') { bgIcon = '#e0e7ff'; colorIcon = '#6366f1'; }
        else if (l.type === 'WIREWAY') { bgIcon = '#f1f5f9'; colorIcon = '#64748b'; }
        else if (l.type === 'SPACE') { bgIcon = '#f8fafc'; colorIcon = '#94a3b8'; }
        else { bgIcon = '#f3e8ff'; colorIcon = '#9333ea'; }
        if (appState.theme === 'dark') {
            if (l.type === 'PANELBOARD') { bgIcon = '#1e3a8a'; colorIcon = '#93c5fd'; }
            else if (l.type === 'DISCONNECT') { bgIcon = '#064e3b'; colorIcon = '#6ee7b7'; }
            else if (l.type === 'METER') { bgIcon = '#78350f'; colorIcon = '#fcd34d'; }
            else if (l.type === 'CT_CABINET') { bgIcon = '#164e63'; colorIcon = '#22d3ee'; }
            else if (l.type === 'TRANSFORMER') { bgIcon = '#312e81'; colorIcon = '#818cf8'; }
            else if (l.type === 'WIREWAY') { bgIcon = '#334155'; colorIcon = '#94a3b8'; }
            else if (l.type === 'SPACE') { bgIcon = '#1e293b'; colorIcon = '#64748b'; }
            else { bgIcon = '#581c87'; colorIcon = '#d8b4fe'; }
        }

        let spacingBeforeHtml = '';
        if (index === 0) {
            spacingBeforeHtml = `
            <div class="control-group">
                <label class="input-label" style="color: var(--accent-primary);">Start Gap</label>
                <div style="position:relative;">
                    <input type="number" step="0.5" min="0" class="input-custom" style="width: 80px; text-align:center; padding-right: 1.4rem;" 
                    value="${l.spacingBefore}" onchange="rd_updateLoad('${l.id}', 'spacingBefore', this.value)">
                    <span style="position:absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-secondary); font-weight:800; pointer-events: none;">"</span>
                </div>
            </div>`;
        }

        const spacingAfterHtml = `
            <div class="control-group">
                <label class="input-label">Spacing After</label>
                <div style="position:relative;">
                    <input type="number" step="0.5" min="0" class="input-custom" style="width: 80px; text-align:center; padding-right: 1.4rem;" 
                    value="${l.spacingAfter}" onchange="rd_updateLoad('${l.id}', 'spacingAfter', this.value)">
                    <span style="position:absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-secondary); font-weight:800; pointer-events: none;">"</span>
                </div>
            </div>`;

        let mainControlLabel = 'Amps';
        if (l.type === 'METER') mainControlLabel = 'Size';
        if (l.isCustom || RD_LV_DATA[l.type]) mainControlLabel = 'Width';
        if (l.type === 'SPACE') mainControlLabel = 'Width / Label';

        let mainControlInputHtml = '';
        if (l.type === 'SPACE') {
            mainControlInputHtml = `<div style="display:flex; gap:0.5rem;"><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.4rem; width: 80px;" value="${l.customWidth}" onchange="rd_updateLoad('${l.id}', 'customWidth', this.value)"><span style="position:absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-secondary); font-weight:800; pointer-events: none;">"</span></div><input type="text" class="input-custom" style="width: 100px; font-size: 0.8rem;" value="${l.description || ''}" placeholder="Label" onchange="rd_updateLoad('${l.id}', 'description', this.value)"></div>`;
        } else if (l.isCustom || RD_LV_DATA[l.type]) {
            mainControlInputHtml = `<div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.4rem; width: 110px;" value="${l.customWidth}" onchange="rd_updateLoad('${l.id}', 'customWidth', this.value)"><span style="position:absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-secondary); font-weight:800; pointer-events: none;">"</span></div>`;
        } else {
            if (l.type === 'METER') {
                mainControlInputHtml = `<select class="input-custom" style="width: 100px;" onchange="rd_updateLoad('${l.id}', 'meterSize', this.value)"><option value="SMALL" ${l.meterSize === 'SMALL'?'selected':''}>SMALL</option><option value="LARGE" ${l.meterSize === 'LARGE'?'selected':''}>LARGE</option></select>`;
            } else if (l.type === 'CT_CABINET') {
                const options = RD_CT_CABINET_OPTIONS.map(a => `<option value="${a}" ${a === l.amps ? 'selected' : ''}>${a}A</option>`).join('');
                mainControlInputHtml = `<select class="input-custom" style="width: 100px;" onchange="rd_updateLoad('${l.id}', 'amps', this.value)">${options}</select>`;
            } else {
                const availableAmps = l.type === 'PANELBOARD' ? RD_PANEL_AMPS_SORTED : (l.type === 'DISCONNECT' ? RD_DISCONNECT_AMPS_SORTED : RD_AMP_OPTIONS);
                const options = availableAmps.map(a => `<option value="${a}" ${a === l.amps ? 'selected' : ''}>${a}A</option>`).join('');
                mainControlInputHtml = `<select class="input-custom" style="width: 100px;" onchange="rd_updateLoad('${l.id}', 'amps', this.value)">${options}</select>`;
            }
        }
        const mainControlHtml = `<div class="control-group"><label class="input-label">${mainControlLabel}</label>${mainControlInputHtml}</div>`;

        let buttonStyle = l.isCustom ? `background: #f3e8ff; border: 1px solid #d8b4fe;` : `background: var(--bg-input); border: 2px solid var(--border-subtle);`;
        if (appState.theme === 'dark' && l.isCustom) { buttonStyle = `background: #4c1d95; border: 1px solid #a78bfa;`; }
        let buttonIconColor = l.isCustom ? (appState.theme === 'dark' ? '#d8b4fe' : '#9333ea') : '#6366f1';
        const buttonsHtml = `<div class="control-group-buttons"><button onclick="rd_toggleCustomMode('${l.id}')" title="Manual Size Override" style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; cursor: pointer; display:flex; align-items:center; justify-content:center; ${buttonStyle} color: ${buttonIconColor}; transition: 0.2s;"><i class="fa-solid fa-ruler-horizontal"></i></button><button onclick="rd_removeLoad('${l.id}')" class="btn-icon-only" style="width: 2.75rem; height: 2.75rem; display:flex; align-items:center; justify-content:center; font-size: 1rem; border: 1px solid var(--border-subtle); border-radius: 0.75rem;"><i class="fa-solid fa-times"></i></button></div>`;

        return `
        <div class="load-item" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; padding: 1.25rem; border-bottom: 1px solid var(--border-subtle);">
            <div style="display: flex; gap: 1rem; align-items: center; min-width: 180px; margin-right: auto;">
                <div style="width: 3rem; height: 3rem; background: ${bgIcon}; color: ${colorIcon}; border-radius: 0.85rem; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.25rem;">
                    ${l.type === 'PANELBOARD' ? 'P' : (l.type === 'DISCONNECT' ? 'D' : (l.type === 'METER' ? 'M' : (l.type === 'CT_CABINET' ? 'CT' : (l.type === 'TRANSFORMER' ? 'T' : (l.type === 'WIREWAY' ? 'W' : 'L')))))}
                </div>
                <div>
                    <div style="font-weight: 800; font-size: 0.85rem; color: var(--text-primary);">${RD_LV_DATA[l.type] ? RD_LV_DATA[l.type].label : l.type}</div>
                    <div style="font-family: monospace; font-size: 0.7rem; color: var(--text-muted);">ID: ${l.id.slice(-3)}</div>
                </div>
            </div>
            <div class="load-item-controls">
                ${spacingBeforeHtml}
                ${spacingAfterHtml}
                ${mainControlHtml}
                ${buttonsHtml}
            </div>
        </div>`;
    }).join('');
}

/* --- EXPORTAR PDF (RD) --- */
function rd_renderExportButton() {
    const headerActions = document.getElementById('rd-headerActions');
    if (headerActions && headerActions.innerHTML === '') {
        const btn = document.createElement('button');
        btn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> PDF';
        btn.style.cssText = `padding: 0.75rem 1.5rem; background: var(--text-primary); color: var(--bg-panel); border: none; border-radius: 0.75rem; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.6rem; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0, 0.15);`;
        btn.onclick = rd_exportReport;
        headerActions.appendChild(btn);
    }
}

function rd_exportReport() {
    const printArea = document.getElementById('printArea');
    if (!printArea) return;

    const date = new Date().toLocaleDateString();
    let reportContent = '';

    appState.roomDesigner.walls.filter(w => w.active).forEach((wall, index) => {
        const calculatedItems = rd_calculateWallItems(wall);
        const totalUsed = calculatedItems.reduce((acc, item, idx) => acc + item.width + item.spacingAfter + (idx === 0 ? item.spacingBefore : 0), 0);
        const svgHtml = rd_generateWallSVG(wall, calculatedItems, true);

        let itemsRows = calculatedItems.map((l, index) => {
            let desc = l.isCustom ? 'Manual Size' : (l.type === 'METER' ? l.meterSize : (l.amps ? l.amps + 'A' : '-'));
            if (l.type === 'SPACE') desc = l.description || 'Reserved Space';
            if (RD_LV_DATA[l.type]) desc = RD_LV_DATA[l.type].label;
            return `<tr><td>${index + 1}. ${l.type}</td><td>${desc}</td><td>${parseFloat(l.width).toFixed(1)}"</td><td>${index === 0 ? `(Start: ${l.spacingBefore}") ` : ''}${parseFloat(l.spacingAfter).toFixed(1)}"</td></tr>`;
        }).join('');

        const pageBreakClass = index > 0 ? 'page-break' : '';
        reportContent += `
            <div class="${pageBreakClass}" style="margin-bottom: 3rem; display: block;">
                <h2 style="border-bottom: 2px solid #333; padding-bottom: 0.5rem;">${wall.name} Wall (${(wall.width / 12).toFixed(2)} ft x ${(wall.height / 12).toFixed(2)} ft)</h2>
                <div style="margin: 1rem 0; border: 1px solid #ccc; padding: 1rem;">${svgHtml}</div>
                <div style="margin-bottom: 1rem; font-weight:bold; color: #fb5a3a;">Usage: ${totalUsed.toFixed(1)}" / ${wall.width}"</div>
                <table class="print-table"><thead><tr><th>Item Type</th><th>Config</th><th>Width</th><th>Spacing After</th></tr></thead><tbody>${itemsRows}</tbody></table>
            </div>`;
    });

    const reportHTML = `
        <div style="padding: 2rem; max-width: 100%; margin: 0 auto; font-family: 'Inter', sans-serif;">
            <div style="margin-bottom: 2rem;"><h1 style="margin:0; font-size: 1.5rem; color: #1e293b;">Electrical Room Layout</h1><p style="margin:0.5rem 0 0; color: #64748b;">Generated on ${date}</p></div>
            ${reportContent}
            <div style="margin-top: 2rem; text-align: center; color: #cbd5e1; font-size: 0.8rem;"><p>Brightronix Electrical Room Designer</p></div>
        </div>`;

    printArea.innerHTML = reportHTML;
    window.print();
}

/* --- MOTOR DE CÁLCULO (RD) --- */
function rd_calculateAndRender() {
    rd_renderLoadList();

    const svgContainer = document.getElementById('rd-svgContainer');
    if (!svgContainer) return;
    svgContainer.innerHTML = '';
    
    const activeWalls = appState.roomDesigner.walls.filter(w => w.active);
    
    if (activeWalls.length === 0) {
        svgContainer.innerHTML = '<div style="color:var(--text-muted); text-align: center;">No active walls.</div>';
        return;
    }

    let currentWall = activeWalls.find(w => w.id === appState.roomDesigner.activeWallId);
    if (!currentWall) {
        currentWall = activeWalls[0];
        appState.roomDesigner.activeWallId = currentWall.id;
        rd_renderWallControls();
    }

    const calculatedItems = rd_calculateWallItems(currentWall);
    const totalUsed = calculatedItems.reduce((acc, item, idx) => acc + item.width + item.spacingAfter + (idx === 0 ? item.spacingBefore : 0), 0);
    
    const pct = currentWall.width > 0 ? Math.min(100, (totalUsed / currentWall.width) * 100).toFixed(0) : 0;
    const pctEl = document.getElementById('rd-wallUsagePct');
    const textEl = document.getElementById('rd-wallUsageText');
    if(pctEl) {
        pctEl.innerText = `${pct}%`;
        pctEl.style.color = totalUsed > currentWall.width ? '#ef4444' : 'var(--text-primary)';
    }
    if(textEl) textEl.innerText = `${totalUsed.toFixed(1)}" / ${currentWall.width}"`;

    const svgHtml = rd_generateWallSVG(currentWall, calculatedItems, false);

    svgContainer.innerHTML = `
        <button class="carousel-btn" onclick="rd_changeWall(-1)"><i class="fa-solid fa-chevron-left"></i></button>
        <div class="carousel-content" id="rd-carouselSwipeArea" onclick="rd_setActiveWall(${currentWall.id})">${svgHtml}</div>
        <button class="carousel-btn" onclick="rd_changeWall(1)"><i class="fa-solid fa-chevron-right"></i></button>
    `;

    const visualLayoutCard = document.getElementById('rd-svgContainer').parentElement;
    if (visualLayoutCard && !visualLayoutCard.querySelector('.btn-fullscreen-trigger')) {
        const fsBtn = document.createElement('button');
        fsBtn.className = 'btn-fullscreen-trigger';
        fsBtn.title = 'Enter Fullscreen';
        fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
        fsBtn.onclick = () => rd_toggleFullscreen(true);
        visualLayoutCard.appendChild(fsBtn);
    }

    const swipeArea = document.getElementById('rd-carouselSwipeArea');
    if (swipeArea) {
        let touchStartX = 0;
        swipeArea.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
        swipeArea.addEventListener('touchend', e => {
            const touchEndX = e.changedTouches[0].screenX;
            if (touchStartX - touchEndX > 50) rd_changeWall(1);
            if (touchEndX - touchStartX > 50) rd_changeWall(-1);
        }, { passive: true });
    }
}

function rd_changeWall(direction) {
    const activeWalls = appState.roomDesigner.walls.filter(w => w.active);
    if (activeWalls.length <= 1) return;

    const currentIndex = activeWalls.findIndex(w => w.id === appState.roomDesigner.activeWallId);
    let newIndex = currentIndex + direction;

    if (newIndex < 0) newIndex = activeWalls.length - 1;
    if (newIndex >= activeWalls.length) newIndex = 0;

    rd_setActiveWall(activeWalls[newIndex].id);
}

function rd_handleEscKey(event) {
    if (event.key === 'Escape') {
        rd_toggleFullscreen(false);
    }
}

function rd_toggleFullscreen(enter = true) {
    const existingOverlay = document.querySelector('.fullscreen-overlay');
    if (enter) {
        if (existingOverlay) return;
        const currentWall = appState.roomDesigner.walls.find(w => w.id === appState.roomDesigner.activeWallId);
        if (!currentWall) return;
        const calculatedItems = rd_calculateWallItems(currentWall);
        const overlay = document.createElement('div');
        overlay.className = 'fullscreen-overlay';
        const closeBtn = document.createElement('button');
        closeBtn.className = 'fullscreen-close-btn';
        closeBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
        closeBtn.title = 'Exit Fullscreen (Esc)';
        closeBtn.onclick = () => rd_toggleFullscreen(false);
        const svgContainer = document.createElement('div');
        svgContainer.className = 'fullscreen-svg-container';
        svgContainer.innerHTML = rd_generateWallSVG(currentWall, calculatedItems, false);
        overlay.append(closeBtn, svgContainer);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', rd_handleEscKey);
        overlay.addEventListener('click', (e) => {
            if (closeBtn.contains(e.target)) return;
            rd_toggleFullscreen(false);
        });
    } else {
        if (!existingOverlay) return;
        existingOverlay.remove();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', rd_handleEscKey);
    }
}

function rd_calculateWallItems(wall) {
    return wall.items.map(l => {
        let w = 0;
        if (l.isCustom) w = l.customWidth;
        else if (RD_LV_DATA[l.type]) w = RD_LV_DATA[l.type].width;
        else if (l.type === 'PANELBOARD') w = RD_PANEL_DATA[l.amps] || 0;
        else if (l.type === 'DISCONNECT') w = RD_DISCONNECT_DATA[l.amps] || 0;
        else w = (l.meterSize === 'LARGE' ? 12 : 9.6);
        if (l.type === 'CT_CABINET') {
            w = l.amps <= 600 ? 32 : (l.amps <= 1200 ? 36 : 48);
        }
        return { ...l, width: w, spacingBefore: l.spacingBefore || 0 };
    });
}

function rd_generateWallSVG(wall, items, forPrint) {
    const scale = 4;
    const svgWidth = wall.width * scale;
    let svgHeight = wall.height * scale;
    const visualHeight = Math.min(svgHeight, svgWidth * 2);
    const heightScaleFactor = svgHeight > 0 ? visualHeight / svgHeight : 1;
    const visualMultiplier = 1 + Math.max(0, (wall.width - 120) / 240);
    const padding = 60 * visualMultiplier, fsDim = 14 * visualMultiplier, fsHeader = 16 * visualMultiplier, fsItem = 12 * visualMultiplier, swWall = 4 * visualMultiplier, swItem = 2 * visualMultiplier;
    const totalUsed = items.reduce((acc, item, idx) => acc + item.width + item.spacingAfter + (idx===0 ? item.spacingBefore : 0), 0);
    const isOverflow = totalUsed > wall.width;
    let wallFill = forPrint ? '#f1f5f9' : (appState.theme === 'light' ? '#e2e8f0' : '#334155');
    let wallStroke = forPrint ? '#94a3b8' : (appState.roomDesigner.activeWallId === wall.id ? 'var(--accent-primary)' : 'var(--border-subtle)');
    const textFill = forPrint ? '#000' : (appState.theme === 'light' ? '#334155' : '#cbd5e1');
    const dimColor = forPrint ? '#000' : (appState.theme === 'light' ? '#475569' : '#64748b');
    if (isOverflow && !forPrint) {
        wallFill = appState.theme === 'light' ? '#fee2e2' : '#450a0a';
        wallStroke = '#ef4444';
    }
    let svg = `<svg width="100%" height="100%" viewBox="-${padding} -${padding} ${svgWidth + padding*2} ${visualHeight + padding*2}" xmlns="http://www.w3.org/2000/svg" style="cursor: pointer;">
        <line x1="0" y1="${-20 * visualMultiplier}" x2="${svgWidth}" y2="${-20 * visualMultiplier}" stroke="${dimColor}" stroke-width="${2 * visualMultiplier}" /><line x1="0" y1="${-25 * visualMultiplier}" x2="0" y2="${-15 * visualMultiplier}" stroke="${dimColor}" stroke-width="${2 * visualMultiplier}" /><line x1="${svgWidth}" y1="${-25 * visualMultiplier}" x2="${svgWidth}" y2="${-15 * visualMultiplier}" stroke="${dimColor}" stroke-width="${2 * visualMultiplier}" /><text x="${svgWidth/2}" y="${-30 * visualMultiplier}" text-anchor="middle" fill="${dimColor}" font-size="${fsDim}" font-weight="bold">${wall.width}" (${(wall.width/12).toFixed(1)}')</text>
        <line x1="${-20 * visualMultiplier}" y1="0" x2="${-20 * visualMultiplier}" y2="${visualHeight}" stroke="${dimColor}" stroke-width="${2 * visualMultiplier}" /><line x1="${-25 * visualMultiplier}" y1="0" x2="${-15 * visualMultiplier}" y2="0" stroke="${dimColor}" stroke-width="${2 * visualMultiplier}" /><line x1="${-25 * visualMultiplier}" y1="${visualHeight}" x2="${-15 * visualMultiplier}" y2="${visualHeight}" stroke="${dimColor}" stroke-width="${2 * visualMultiplier}" /><text x="${-30 * visualMultiplier}" y="${visualHeight/2}" text-anchor="middle" transform="rotate(-90, ${-30 * visualMultiplier}, ${visualHeight/2})" fill="${dimColor}" font-size="${fsDim}" font-weight="bold">${wall.height}" (${(wall.height/12).toFixed(1)}')</text>
        <rect x="0" y="0" width="${svgWidth}" height="${visualHeight}" fill="${wallFill}" stroke="${wallStroke}" stroke-width="${swWall}" />
        <text x="${10 * visualMultiplier}" y="${25 * visualMultiplier}" fill="${textFill}" font-weight="900" font-family="sans-serif" font-size="${fsHeader}" opacity="0.5">${wall.name.toUpperCase()} WALL</text>`;
    let currentX = 0;
    const itemHeight = Math.min(visualHeight * 0.6, 200 * visualMultiplier);
    const itemY = (visualHeight - itemHeight) / 2;
    items.forEach((item, idx) => {
        const itemW = item.width * scale, spacingW = item.spacingAfter * scale, spacingBeforeW = (idx === 0) ? item.spacingBefore * scale : 0;
        currentX += spacingBeforeW;
        if (idx === 0 && item.spacingBefore > 0) {
             svg += `<g transform="translate(0, ${itemY + itemHeight/2})"><line x1="0" y1="0" x2="${spacingBeforeW}" y2="0" stroke="${dimColor}" stroke-width="${1 * visualMultiplier}" stroke-dasharray="${4 * visualMultiplier}" /><text x="${spacingBeforeW/2}" y="${-5 * visualMultiplier}" text-anchor="middle" fill="${dimColor}" font-size="${10 * visualMultiplier}">${item.spacingBefore}"</text></g>`;
        }
        let fill = '#64748b';
        if (item.type === 'PANELBOARD') fill = '#3b82f6'; else if (item.type === 'DISCONNECT') fill = '#10b981'; else if (item.type === 'METER') fill = '#f59e0b'; else if (item.type === 'CT_CABINET') fill = '#06b6d4'; else if (item.type === 'TRANSFORMER') fill = '#6366f1'; else if (item.type === 'WIREWAY') fill = '#94a3b8'; else if (item.type === 'SPACE') fill = 'transparent'; else fill = '#8b5cf6';
        if (forPrint) fill = '#cbd5e1';
        let label = item.type.substring(0,3), subLabel = '';
        if (['PANELBOARD', 'DISCONNECT', 'CT_CABINET'].includes(item.type)) subLabel = `${item.amps}A`;
        if (item.type === 'SPACE') { label = item.description || 'SPACE'; subLabel = ''; }
        const dimensionText = `<text x="${itemW/2}" y="${itemHeight - (fsItem * 1.5)}" text-anchor="middle" fill="${item.type === 'SPACE' ? dimColor : 'white'}" font-size="${fsItem}" font-weight="bold" font-family="sans-serif" opacity="0.8">${item.width}"</text>`;
        let mainText = '';
        if (subLabel) {
            mainText = `<text x="${itemW/2}" y="${itemHeight/2 - (7 * visualMultiplier)}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="${10 * visualMultiplier}" font-weight="bold" font-family="sans-serif">${label}</text><text x="${itemW/2}" y="${itemHeight/2 + (7 * visualMultiplier)}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="${9 * visualMultiplier}" font-weight="bold" font-family="sans-serif">${subLabel}</text>`;
        } else {
            const labelFill = item.type === 'SPACE' ? dimColor : 'white', labelSize = (item.type === 'SPACE' ? 10 : 12) * visualMultiplier;
            mainText = `<text x="${itemW/2}" y="${itemHeight/2}" text-anchor="middle" dominant-baseline="central" fill="${labelFill}" font-size="${labelSize}" font-weight="bold" font-family="sans-serif">${label}</text>`;
        }
        let strokeStyle = item.type === 'SPACE' ? `stroke-dasharray="${5 * visualMultiplier},${5 * visualMultiplier}"` : '', rectStroke = item.type === 'SPACE' ? dimColor : (forPrint ? '#666' : '#fff');
        svg += `<g transform="translate(${currentX}, ${itemY})"><rect width="${itemW}" height="${itemHeight}" fill="${fill}" stroke="${rectStroke}" stroke-width="${swItem}" rx="${4 * visualMultiplier}" ${strokeStyle} />${mainText}${dimensionText}</g>`;
        if (item.spacingAfter > 0) {
            svg += `<g transform="translate(${currentX + itemW}, ${itemY + itemHeight + (5 * visualMultiplier)})"><line x1="0" y1="0" x2="${spacingW}" y2="0" stroke="${dimColor}" stroke-width="${1 * visualMultiplier}" stroke-dasharray="${4 * visualMultiplier}" /><text x="${spacingW/2}" y="${15 * visualMultiplier}" text-anchor="middle" fill="${dimColor}" font-size="${10 * visualMultiplier}">${item.spacingAfter}"</text></g>`;
        }
        currentX += itemW + spacingW;
    });
    if (isOverflow && !forPrint) {
        svg += `<text x="${svgWidth/2}" y="${svgHeight/2}" text-anchor="middle" fill="red" font-size="${24 * visualMultiplier}" font-weight="900" opacity="0.5">OVERFLOW</text>`;
    }
    svg += `</g></svg>`;
    return svg;
}


// ===================================================================================
// |                                                                                 |
// |                     WIREWAY CALCULATOR LOGIC (wc_ prefix)                       |
// |                                                                                 |
// ===================================================================================

/* --- LÓGICA DEL FEEDER (WC) --- */
function wc_toggleFeeder() {
    appState.wirewayCalculator.isFeederEnabled = !appState.wirewayCalculator.isFeederEnabled;
    const isActive = appState.wirewayCalculator.isFeederEnabled;
    
    const btn = document.getElementById('wc-btnFeederToggle');
    if(btn) {
        btn.className = `btn-toggle ${isActive ? 'active' : 'inactive'}`;
        btn.innerText = isActive ? "ON" : "OFF";
    }
    
    const content = document.getElementById('wc-feederContent');
    if(content) content.classList.toggle('opacity-40', !isActive);
    
    const box = document.getElementById('wc-feederBox');
    if(box) {
        box.style.borderLeftColor = isActive ? "var(--blue-500)" : "var(--border-subtle)";
    }

    wc_calculateAndRender();
}

function wc_updateFeederType() {
    const el = document.getElementById('wc-feederType');
    if(!el) return;
    const type = el.value;
    appState.wirewayCalculator.feeder.type = type;

    if (type === 'CT_CABINET' && !WC_CT_CABINET_OPTIONS.includes(appState.wirewayCalculator.feeder.amps)) {
        appState.wirewayCalculator.feeder.amps = 600;
    }

    wc_renderFeederInputs();
    wc_calculateAndRender();
}

function wc_updateFeederValue(field, value) {
    let val;
    if (field === 'sets') {
        val = Math.max(1, parseInt(value) || 1);
    } else {
        val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;
    }
    appState.wirewayCalculator.feeder[field] = val;
    wc_calculateAndRender();
}

function wc_renderFeederInputs() {
    const container = document.getElementById('wc-feederDynamicFields');
    if(!container) return;
    
    let html = '';
    const { feeder } = appState.wirewayCalculator;
    const isDirect = feeder.type === 'DIRECT';

    if (isDirect) {
        const conduitOptions = WC_CONDUIT_SIZES.map(s => `<option value="${s}" ${s === feeder.conduitSize ? 'selected' : ''}>${s}"</option>`).join('');
        html = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div class="input-group"><label class="input-label">DIAM (IN)</label><select class="input-custom" onchange="wc_updateFeederValue('conduitSize', this.value)">${conduitOptions}</select></div>
            <div class="input-group"><label class="input-label">SETS</label><input type="number" min="1" class="input-custom" value="${feeder.sets}" onchange="wc_updateFeederValue('sets', this.value)"></div>
        </div>`;
    } else {
        const currentOptions = feeder.type === 'CT_CABINET' ? WC_CT_CABINET_OPTIONS : WC_AMP_OPTIONS;
        const ampOptionsHtml = currentOptions.map(a => {
            let label = `${a}A`;
            if (feeder.type === 'CT_CABINET' && a === 800) label = '800A+';
            return `<option value="${a}" ${a === feeder.amps ? 'selected' : ''}>${label}</option>`;
        }).join('');
        html = `<div class="input-group" style="margin-bottom: 1.25rem;"><label class="input-label">AMPERAGE</label><select class="input-custom" onchange="wc_updateFeederValue('amps', this.value)">${ampOptionsHtml}</select></div>`;
    }

    html += `
    <div style="display: grid; grid-template-columns: ${isDirect ? '1fr 1fr 1fr' : '1fr 1fr'}; gap: 0.75rem;">
        <div class="input-group"><label class="input-label">START GAP</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.5rem;" value="${feeder.startGap}" onchange="wc_updateFeederValue('startGap', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div></div>
        ${isDirect ? `<div class="input-group"><label class="input-label">CONDUIT GAP</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.5rem;" value="${feeder.betweenGap}" onchange="wc_updateFeederValue('betweenGap', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div></div>` : ''}
        <div class="input-group"><label class="input-label">GAP AFTER</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.5rem;" value="${feeder.gapAfter}" onchange="wc_updateFeederValue('gapAfter', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div></div>
    </div>`;
    container.innerHTML = html;
}

/* --- GESTIÓN DE CARGAS (WC) --- */
function wc_addLoad(type) {
    const defaultAmps = type === 'CT_CABINET' ? 600 : 200;
    appState.wirewayCalculator.loads.push({ 
        id: Date.now().toString() + Math.random().toString(36).substr(2, 5), 
        type, 
        amps: defaultAmps, 
        meterSize: 'SMALL',
        isCustom: false,
        customWidth: 10,
        gapAfter: WC_DEFAULT_SPACING
    });
    wc_calculateAndRender();
}

function wc_removeLoad(id) {
    appState.wirewayCalculator.loads = appState.wirewayCalculator.loads.filter(l => l.id !== id);
    wc_calculateAndRender();
}

function wc_updateLoad(id, field, value) {
    const load = appState.wirewayCalculator.loads.find(l => l.id === id);
    if (load) {
        if (field === 'meterSize') load[field] = value;
        else if (field === 'gapAfter') {
            let val = parseFloat(value);
            load[field] = (isNaN(val) || val < 0) ? 0 : val;
        } else {
            load[field] = parseInt(value);
        }
        wc_calculateAndRender();
    }
}

function wc_toggleCustomMode(id) {
    const load = appState.wirewayCalculator.loads.find(l => l.id === id);
    if (load) {
        load.isCustom = !load.isCustom;
        wc_calculateAndRender();
    }
}

function wc_updateCustomWidth(id, value) {
    const load = appState.wirewayCalculator.loads.find(l => l.id === id);
    if (load) {
        let val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;
        load.customWidth = val;
        wc_calculateAndRender();
    }
}

function wc_renderLoadList() {
    const container = document.getElementById('wc-loadListContainer');
    if (!container) return;
    document.getElementById('wc-itemsCount').innerText = `${appState.wirewayCalculator.loads.length} Active Loads`;

    if (appState.wirewayCalculator.loads.length === 0) {
        container.innerHTML = `<div style="padding: 3rem; text-align: center; color: var(--text-muted);"><i class="fa-solid fa-layer-group" style="font-size: 2.5rem; margin-bottom: 1rem; opacity:0.3;"></i><span style="font-weight: 700; font-size: 0.9rem; display:block;">NO LOADS ADDED</span><span style="font-size: 0.8rem;">Use the sidebar to add items</span></div>`;
        return;
    }

    container.innerHTML = appState.wirewayCalculator.loads.map(l => {
        let bgIcon, colorIcon;
        if (appState.theme === 'dark') {
            switch(l.type) {
                case 'PANELBOARD': bgIcon = '#1e3a8a'; colorIcon = '#93c5fd'; break;
                case 'DISCONNECT': bgIcon = '#064e3b'; colorIcon = '#6ee7b7'; break;
                case 'METER': bgIcon = '#78350f'; colorIcon = '#fcd34d'; break;
                case 'CT_CABINET': bgIcon = '#5b21b6'; colorIcon = '#c4b5fd'; break;
            }
        } else {
            switch(l.type) {
                case 'PANELBOARD': bgIcon = 'var(--blue-100)'; colorIcon = 'var(--blue-600)'; break;
                case 'DISCONNECT': bgIcon = 'var(--emerald-100)'; colorIcon = 'var(--emerald-500)'; break;
                case 'METER': bgIcon = 'var(--amber-100)'; colorIcon = 'var(--amber-500)'; break;
                case 'CT_CABINET': bgIcon = '#f3e8ff'; colorIcon = '#9333ea'; break;
            }
        }

        let mainControlLabel = 'Amps';
        if (l.type === 'METER') mainControlLabel = 'Size';
        if (l.isCustom) mainControlLabel = 'Width';

        let mainControlInputHtml = '';
        if (l.isCustom) {
            mainControlInputHtml = `<div style="position:relative;"><input type="number" step="0.5" class="input-custom" style="padding-right: 1.5rem; width: 100px;" value="${l.customWidth}" onchange="wc_updateCustomWidth('${l.id}', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div>`;
        } else {
            if (l.type === 'METER') {
                mainControlInputHtml = `<select class="input-custom" style="width: 100px; padding: 0 0.5rem;" onchange="wc_updateLoad('${l.id}', 'meterSize', this.value)"><option value="SMALL" ${l.meterSize === 'SMALL'?'selected':''}>SMALL</option><option value="LARGE" ${l.meterSize === 'LARGE'?'selected':''}>LARGE</option></select>`;
            } else {
                const availableAmps = l.type === 'PANELBOARD' ? WC_PANEL_AMPS_SORTED : l.type === 'DISCONNECT' ? WC_DISCONNECT_AMPS_SORTED : l.type === 'CT_CABINET' ? WC_CT_CABINET_OPTIONS : WC_AMP_OPTIONS;
                const options = availableAmps.map(a => {
                    let label = `${a}A`;
                    if (l.type === 'CT_CABINET' && a === 800) label = '800A+';
                    return `<option value="${a}" ${a === l.amps ? 'selected' : ''}>${label}</option>`;
                }).join('');
                mainControlInputHtml = `<select class="input-custom" style="width: 100px; padding: 0 0.5rem;" onchange="wc_updateLoad('${l.id}', 'amps', this.value)">${options}</select>`;
            }
        }
        const mainControlHtml = `<div class="control-group"><label class="input-label">${mainControlLabel}</label>${mainControlInputHtml}</div>`;

        const spacingAfterHtml = `
            <div class="control-group">
                <label class="input-label">Gap After</label>
                <div style="position:relative;">
                    <input type="number" step="0.5" min="0" class="input-custom" style="width: 80px; text-align:center; padding-right: 1.4rem;" 
                    value="${l.gapAfter}" onchange="wc_updateLoad('${l.id}', 'gapAfter', this.value)">
                    <span style="position:absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-secondary); font-weight:800; pointer-events: none;">"</span>
                </div>
            </div>`;

        let buttonStyle = l.isCustom ? `background: #f3e8ff; border: 1px solid #d8b4fe;` : `background: var(--bg-input); border: 2px solid var(--border-subtle);`;
        if (appState.theme === 'dark' && l.isCustom) { buttonStyle = `background: #4c1d95; border: 1px solid #a78bfa;`; }
        let buttonIconColor = l.isCustom ? (appState.theme === 'dark' ? '#d8b4fe' : '#9333ea') : '#6366f1';
        const buttonsHtml = `<div class="control-group-buttons"><button onclick="wc_toggleCustomMode('${l.id}')" title="Manual Size Override" style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; cursor: pointer; display:flex; align-items:center; justify-content:center; ${buttonStyle} color: ${buttonIconColor}; transition: 0.2s;"><i class="fa-solid fa-ruler-horizontal"></i></button><button onclick="wc_removeLoad('${l.id}')" class="btn-icon-only" style="width: 2.75rem; height: 2.75rem; display:flex; align-items:center; justify-content:center; font-size: 1rem; border: 1px solid var(--border-subtle); border-radius: 0.75rem;"><i class="fa-solid fa-times"></i></button></div>`;

        return `
        <div class="load-item" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; padding: 1.25rem; border-bottom: 1px solid var(--border-subtle);">
            <div style="display: flex; gap: 1rem; align-items: center; min-width: 140px; margin-right: auto;"><div style="width: 3rem; height: 3rem; background: ${bgIcon}; color: ${colorIcon}; border-radius: 0.85rem; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.25rem;">${l.type.charAt(0)}</div><div><div style="font-weight: 800; font-size: 0.85rem; color: var(--text-primary);">${l.type}</div><div style="font-family: monospace; font-size: 0.7rem; color: var(--text-muted);">ID: ${l.id.slice(-3)}</div></div></div>
            <div class="load-item-controls">
                ${spacingAfterHtml}
                ${mainControlHtml}
                ${buttonsHtml}
            </div>
        </div>`;
    }).join('');
}

/* --- EXPORTAR PDF (WC) --- */
function wc_renderExportButton() {
    const headerActions = document.getElementById('wc-headerActions');
    if (headerActions && headerActions.innerHTML === '') {
        const btn = document.createElement('button');
        btn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> PDF';
        btn.style.cssText = `padding: 0.75rem 1.5rem; background: var(--text-primary); color: var(--bg-panel); border: none; border-radius: 0.75rem; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.6rem; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0, 0.15);`;
        btn.onclick = wc_exportReport;
        headerActions.appendChild(btn);
    }
}

function wc_exportReport() {
    const printArea = document.getElementById('printArea');
    if (!printArea) return;

    const totalVal = document.getElementById('wc-totalIn').innerText;
    const totalFt = document.getElementById('wc-totalFt').innerText;
    
    let svgElement = document.getElementById('wc-svgContainer').querySelector('svg');
    let svgContent = '';
    if (svgElement) {
        let clonedSvg = svgElement.cloneNode(true);
        clonedSvg.removeAttribute('width');
        clonedSvg.removeAttribute('height');
        clonedSvg.style.width = '100%'; 
        const bgRect = clonedSvg.querySelector('rect[fill="#1e293b"], rect[fill="#334155"], rect[fill="#e2e8f0"]');
        if(bgRect) bgRect.setAttribute('fill', '#e2e8f0');
        svgContent = clonedSvg.outerHTML;
    }

    const date = new Date().toLocaleDateString();
    let itemsRows = '';
    const { feeder, loads, isFeederEnabled } = appState.wirewayCalculator;
    
    if (isFeederEnabled) {
        let desc = (feeder.type === 'DIRECT') ? `Conduit: ${feeder.conduitSize}" (${feeder.sets} sets)` : `${feeder.amps} Amps`;
        if (feeder.type === 'CT_CABINET' && feeder.amps === 800) desc = '800A+';
        let fW = (feeder.type === 'DIRECT') ? (feeder.conduitSize * feeder.sets) + (feeder.betweenGap * (Math.max(0, feeder.sets - 1))) : (feeder.type === 'DISCONNECT' ? (WC_DISCONNECT_DATA[feeder.amps] || 0) : (feeder.amps <= 600 ? 32 : 48));
        let gapCellContent = `${feeder.gapAfter.toFixed(1)}"`;
        if (feeder.type === 'DIRECT' && feeder.sets > 1) {
            gapCellContent += `<br><small style="color:#64748b; font-size: 9pt;">Conduit Gap: ${feeder.betweenGap.toFixed(1)}"</small>`;
        }
        itemsRows += `<tr><td><strong>MAIN FEEDER</strong></td><td>${feeder.type}</td><td>${desc}</td><td>${fW.toFixed(1)}"</td><td>${gapCellContent}</td></tr>`;
    }

    loads.forEach((l, index) => {
        let desc = l.isCustom ? 'Manual Size' : (l.type === 'METER' ? l.meterSize : l.amps + 'A');
        if (l.type === 'CT_CABINET' && l.amps === 800) desc = '800A+';
        let widthDisplay;
        if (l.isCustom) widthDisplay = l.customWidth;
        else if (l.type === 'PANELBOARD') widthDisplay = WC_PANEL_DATA[l.amps] || 0;
        else if (l.type === 'DISCONNECT') widthDisplay = WC_DISCONNECT_DATA[l.amps] || 0;
        else if (l.type === 'CT_CABINET') widthDisplay = l.amps <= 600 ? 32 : 48;
        else widthDisplay = (l.meterSize === 'LARGE' ? 12 : 9.6);
        itemsRows += `<tr><td>${index + 1}. ${l.type}</td><td>${l.isCustom ? 'Custom' : 'Standard'}</td><td>${desc}</td><td>${parseFloat(widthDisplay).toFixed(1)}"</td><td>${l.gapAfter.toFixed(1)}"</td></tr>`;
    });

    const reportHTML = `
        <div style="padding: 2rem; max-width: 100%; margin: 0 auto; font-family: 'Inter', sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #333; padding-bottom: 1rem; margin-bottom: 2rem;"><div><h1 style="margin:0; font-size: 1.5rem; color: #1e293b;">Wireway Calculation Report</h1><p style="margin:0.5rem 0 0; color: #64748b;">Generated on ${date}</p></div><div style="text-align:right;"><div style="font-size: 0.8rem; text-transform:uppercase; color: #64748b; font-weight:700;">Total Length</div><div style="font-size: 2.5rem; font-weight: 900; color: #fb5a3a;">${totalVal}"</div><div style="font-size: 1rem; color: #64748b; font-weight:600;">${totalFt}</div></div></div>
            <div style="margin-bottom: 2rem;"><h3 style="color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Visual Layout</h3><div style="margin-top: 1rem; padding: 1rem;">${svgContent}</div></div>
            <div><h3 style="color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Load Details & Dimensions</h3><table class="print-table"><thead><tr><th>Item Type</th><th>Config</th><th>Rating/Size</th><th>Width</th><th>Gap After</th></tr></thead><tbody>${itemsRows}</tbody></table></div>
            <div style="margin-top: 4rem; text-align: center; color: #cbd5e1; font-size: 0.8rem;"><p>Brightronix Wireway Calculator</p></div>
        </div>`;
    printArea.innerHTML = reportHTML;
    window.print();
}

/* --- MOTOR DE CÁLCULO (WC) --- */
function wc_calculateAndRender() {
    wc_renderLoadList();
    const { feeder, loads, isFeederEnabled } = appState.wirewayCalculator;

    let fW = 0;
    if (isFeederEnabled) {
        if (feeder.type === 'DIRECT') fW = (feeder.conduitSize * feeder.sets) + (feeder.betweenGap * (Math.max(0, feeder.sets - 1)));
        else if (feeder.type === 'DISCONNECT') fW = WC_DISCONNECT_DATA[feeder.amps] || 0;
        else fW = feeder.amps <= 600 ? 32 : 48;
    }
    
    const calculatedLoads = loads.map(l => {
        let w = 0;
        if (l.isCustom) w = l.customWidth;
        else if (l.type === 'PANELBOARD') w = WC_PANEL_DATA[l.amps] || 0;
        else if (l.type === 'DISCONNECT') w = WC_DISCONNECT_DATA[l.amps] || 0;
        else if (l.type === 'CT_CABINET') w = l.amps <= 600 ? 32 : 48;
        else w = (l.meterSize === 'LARGE' ? 12 : 9.6);
        return { ...l, width: w };
    });

    let total = 0;
    if (isFeederEnabled) {
        total += feeder.startGap + fW + feeder.gapAfter;
    } else if (calculatedLoads.length > 0) {
        total += WC_DEFAULT_SPACING;
    }

    calculatedLoads.forEach(l => {
        total += l.width + l.gapAfter;
    });

    if (total === 0 && !isFeederEnabled) total = WC_DEFAULT_SPACING * 2;

    const elIn = document.getElementById('wc-totalIn');
    const elFt = document.getElementById('wc-totalFt');
    if(elIn) elIn.innerText = total.toFixed(1);
    if(elFt) elFt.innerText = (total / 12).toFixed(2) + " FT";

    wc_drawVisualizer(fW, calculatedLoads, total);
}

function wc_drawVisualizer(feederWidth, calcLoads, totalWidth) {
    const container = document.getElementById('wc-svgContainer');
    if(!container) return;

    const scale = 5, rectHeight = 60, svgWidth = (totalWidth * scale) + 50;
    const wirewayFill = appState.theme === 'light' ? '#e2e8f0' : '#334155';
    const wirewayStroke = 'var(--accent-primary)';
    
    let svgContent = `<svg width="${svgWidth}" height="150" viewBox="0 0 ${svgWidth} 150" xmlns="http://www.w3.org/2000/svg" style="max-height: 150px; flex-shrink: 0; margin: auto;"><rect x="0" y="30" width="${Math.max(totalWidth * scale, svgWidth - 50)}" height="80" fill="${wirewayFill}" stroke="${wirewayStroke}" stroke-width="2" rx="8" />`;
    let currentX = 0;
    const { feeder, isFeederEnabled } = appState.wirewayCalculator;

    // --- MAIN FEEDER ---
    if (isFeederEnabled && feederWidth > 0) {
        currentX = feeder.startGap;
        let feederFill = '#2563eb'; // Default blue for DIRECT
        let feederLabel = 'MAIN';
        let feederSubLabel = '';

        if (feeder.type === 'DISCONNECT') {
            feederFill = '#10b981'; // Green
            feederLabel = 'DISC';
            feederSubLabel = `${feeder.amps}A`;
        } else if (feeder.type === 'CT_CABINET') {
            feederFill = '#8b5cf6'; // Purple
            feederLabel = 'CT';
            feederSubLabel = `${feeder.amps}A`;
            if (feeder.amps === 800) feederSubLabel = '800A+';
        } else { // DIRECT
            feederLabel = 'FEED';
            feederSubLabel = `${feeder.sets}x ${feeder.conduitSize}"`;
        }
        
        const feederText = `<text x="${feederWidth * scale / 2}" y="${rectHeight/2 - 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="10" font-weight="bold" font-family="sans-serif">${feederLabel}</text>
                            <text x="${feederWidth * scale / 2}" y="${rectHeight/2 + 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="9" font-weight="bold" font-family="sans-serif">${feederSubLabel}</text>`;
        
        svgContent += `<g transform="translate(${currentX * scale}, 40)">
                        <rect width="${feederWidth * scale}" height="${rectHeight}" fill="${feederFill}" rx="4" />
                        ${feederText}
                        <text x="${(feederWidth * scale) - 5}" y="${rectHeight - 5}" text-anchor="end" fill="white" font-size="11" font-weight="900" font-family="sans-serif" opacity="0.8">${parseFloat(feederWidth).toFixed(1)}"</text>
                       </g>`;
        currentX += feederWidth;
    } else if (calcLoads.length > 0) {
        currentX = WC_DEFAULT_SPACING;
    }

    // --- LOADS ---
    calcLoads.forEach((l, index) => {
        let gapBefore = 0;
        if (index === 0 && isFeederEnabled) gapBefore = feeder.gapAfter;
        else if (index > 0) gapBefore = calcLoads[index - 1].gapAfter;
        
        currentX += gapBefore;

        let fill = '#64748b';
        let label = l.type.substring(0,5);
        let subLabel = '';

        switch(l.type) {
            case 'PANELBOARD': 
                fill = '#3b82f6'; 
                label = 'PANEL';
                subLabel = `${l.amps}A`;
                break;
            case 'DISCONNECT': 
                fill = '#10b981'; 
                label = 'DISC';
                subLabel = `${l.amps}A`;
                break;
            case 'METER': 
                fill = '#f59e0b'; 
                label = 'METER';
                subLabel = l.meterSize.substring(0,1);
                break;
            case 'CT_CABINET': 
                fill = '#8b5cf6'; // Purple, consistent with WC list
                label = 'CT';
                subLabel = `${l.amps}A`;
                if (l.amps === 800) subLabel = '800A+';
                break;
        }

        const itemText = `<text x="${l.width * scale / 2}" y="${rectHeight/2 - 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="10" font-weight="bold" font-family="sans-serif">${label}</text>
                          <text x="${l.width * scale / 2}" y="${rectHeight/2 + 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="9" font-weight="bold" font-family="sans-serif">${subLabel}</text>`;

        let stroke = l.isCustom ? (appState.theme === 'light' ? 'stroke="#9333ea" stroke-width="2"' : 'stroke="#d8b4fe" stroke-width="2"') : '';
        
        svgContent += `<g transform="translate(${currentX * scale}, 40)">
                        <rect width="${l.width * scale}" height="${rectHeight}" fill="${fill}" rx="4" ${stroke} />
                        ${itemText}
                        <text x="${(l.width * scale) - 5}" y="${rectHeight - 5}" text-anchor="end" fill="white" font-size="11" font-weight="900" font-family="sans-serif" opacity="0.8">${l.width.toFixed(1)}"</text>
                       </g>`;
        currentX += l.width;
    });

    svgContent += '</svg>';
    container.innerHTML = svgContent;
}
