/* --- CONSTANTES --- */
const DISCONNECT_DATA = { "200": 20, "400": 26, "600": 30, "800": 32, "1000": 34, "1200": 36, "1600": 40, "2000": 44, "2500": 48 };
const PANEL_DATA = { "200": 22, "225": 24, "250": 26, "400": 30, "600": 36, "800": 42 };

const DISCONNECT_AMPS_SORTED = Object.keys(DISCONNECT_DATA).map(Number).sort((a, b) => a - b);
const PANEL_AMPS_SORTED = Object.keys(PANEL_DATA).map(Number).sort((a, b) => a - b);

const AMP_OPTIONS = [200, 400, 600, 800, 1000, 1200, 1600, 2000, 2500];
const CT_CABINET_OPTIONS = [600, 800];
const CONDUIT_SIZES = [2.0, 2.5, 3.0, 3.5, 4.0, 5.0, 6.0];
const DEFAULT_SPACING = 3;

/* --- ESTADO --- */
let state = {
    isFeederEnabled: true,
    feeder: {
        type: 'DIRECT',
        conduitSize: 4.0,
        sets: 1,
        amps: 200,
        startGap: DEFAULT_SPACING,
        betweenGap: DEFAULT_SPACING,
        gapAfter: DEFAULT_SPACING
    },
    loads: [],
    theme: 'light'
};

/* --- INICIALIZACION --- */
document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    renderFeederInputs();
    calculateAndRender();
    renderExportButton();

    const svgContainer = document.getElementById('svgContainer');
    if (svgContainer) {
        svgContainer.addEventListener('wheel', (event) => {
            if (event.deltaY !== 0 && svgContainer.scrollWidth > svgContainer.clientWidth) {
                event.preventDefault();
                svgContainer.scrollLeft += event.deltaY;
            }
        }, { passive: false });
    }
});

/* --- TEMA --- */
function toggleTheme() {
    const newTheme = state.theme === 'light' ? 'dark' : 'light';
    applyTheme(newTheme);
}

function applyTheme(themeName) {
    state.theme = themeName;
    document.documentElement.setAttribute('data-theme', themeName);
    localStorage.setItem('theme', themeName);

    const btn = document.getElementById('btnThemeToggle');
    if (btn) {
        btn.innerHTML = themeName === 'light'
            ? '<i class="fa-solid fa-moon"></i>'
            : '<i class="fa-solid fa-sun"></i>';
    }

    calculateAndRender();
}

/* --- FEEDER --- */
function toggleFeeder() {
    state.isFeederEnabled = !state.isFeederEnabled;
    const isActive = state.isFeederEnabled;

    const btn = document.getElementById('btnFeederToggle');
    if (btn) {
        btn.className = `btn-toggle ${isActive ? 'active' : 'inactive'}`;
        btn.innerText = isActive ? 'ON' : 'OFF';
    }

    const content = document.getElementById('feederContent');
    if (content) content.classList.toggle('opacity-40', !isActive);

    const box = document.getElementById('feederBox');
    if (box) {
        box.style.borderLeftColor = isActive ? 'var(--blue-500)' : 'var(--border-subtle)';
    }

    calculateAndRender();
}

function updateFeederType() {
    const el = document.getElementById('feederType');
    if (!el) return;

    state.feeder.type = el.value;
    if (state.feeder.type === 'CT_CABINET' && !CT_CABINET_OPTIONS.includes(state.feeder.amps)) {
        state.feeder.amps = 600;
    }

    renderFeederInputs();
    calculateAndRender();
}

function updateFeederValue(field, value) {
    let val;
    if (field === 'sets') {
        val = Math.max(1, parseInt(value, 10) || 1);
    } else {
        val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;
    }

    state.feeder[field] = val;
    calculateAndRender();
}

function renderFeederInputs() {
    const container = document.getElementById('feederDynamicFields');
    if (!container) return;

    const feeder = state.feeder;
    const isDirect = feeder.type === 'DIRECT';
    let html = '';

    if (isDirect) {
        const conduitOptions = CONDUIT_SIZES
            .map((s) => `<option value="${s}" ${s === feeder.conduitSize ? 'selected' : ''}>${s}"</option>`)
            .join('');

        html = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div class="input-group"><label class="input-label">DIAM (IN)</label><select class="input-custom" onchange="updateFeederValue('conduitSize', this.value)">${conduitOptions}</select></div>
            <div class="input-group"><label class="input-label">SETS</label><input type="number" min="1" class="input-custom" value="${feeder.sets}" onchange="updateFeederValue('sets', this.value)"></div>
        </div>`;
    } else {
        const currentOptions = feeder.type === 'CT_CABINET' ? CT_CABINET_OPTIONS : AMP_OPTIONS;
        const ampOptionsHtml = currentOptions.map((a) => {
            let label = `${a}A`;
            if (feeder.type === 'CT_CABINET' && a === 800) label = '800A+';
            return `<option value="${a}" ${a === feeder.amps ? 'selected' : ''}>${label}</option>`;
        }).join('');

        html = `<div class="input-group" style="margin-bottom: 1.25rem;"><label class="input-label">AMPERAGE</label><select class="input-custom" onchange="updateFeederValue('amps', this.value)">${ampOptionsHtml}</select></div>`;
    }

    html += `
    <div style="display: grid; grid-template-columns: ${isDirect ? '1fr 1fr 1fr' : '1fr 1fr'}; gap: 0.75rem;">
        <div class="input-group"><label class="input-label">START GAP</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.5rem;" value="${feeder.startGap}" onchange="updateFeederValue('startGap', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div></div>
        ${isDirect ? `<div class="input-group"><label class="input-label">CONDUIT GAP</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.5rem;" value="${feeder.betweenGap}" onchange="updateFeederValue('betweenGap', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div></div>` : ''}
        <div class="input-group"><label class="input-label">GAP AFTER</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="padding-right: 1.5rem;" value="${feeder.gapAfter}" onchange="updateFeederValue('gapAfter', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div></div>
    </div>`;

    container.innerHTML = html;
}

/* --- CARGAS --- */
function addLoad(type) {
    const defaultAmps = type === 'CT_CABINET' ? 600 : 200;
    state.loads.push({
        id: Date.now().toString() + Math.random().toString(36).substr(2, 5),
        type,
        amps: defaultAmps,
        meterSize: 'SMALL',
        isCustom: false,
        customWidth: 10,
        gapAfter: DEFAULT_SPACING
    });
    calculateAndRender();
}

function removeLoad(id) {
    state.loads = state.loads.filter((l) => l.id !== id);
    calculateAndRender();
}

function updateLoad(id, field, value) {
    const load = state.loads.find((l) => l.id === id);
    if (!load) return;

    if (field === 'meterSize') {
        load[field] = value;
    } else if (field === 'gapAfter') {
        let val = parseFloat(value);
        load[field] = (isNaN(val) || val < 0) ? 0 : val;
    } else {
        load[field] = parseInt(value, 10);
    }

    calculateAndRender();
}

function toggleCustomMode(id) {
    const load = state.loads.find((l) => l.id === id);
    if (!load) return;

    load.isCustom = !load.isCustom;
    calculateAndRender();
}

function updateCustomWidth(id, value) {
    const load = state.loads.find((l) => l.id === id);
    if (!load) return;

    let val = parseFloat(value);
    if (isNaN(val) || val < 0) val = 0;
    load.customWidth = val;
    calculateAndRender();
}

function renderLoadList() {
    const container = document.getElementById('loadListContainer');
    if (!container) return;

    const itemsCount = document.getElementById('itemsCount');
    if (itemsCount) itemsCount.innerText = `${state.loads.length} Active Loads`;

    if (state.loads.length === 0) {
        container.innerHTML = `<div style="padding: 3rem; text-align: center; color: var(--text-muted);"><i class="fa-solid fa-layer-group" style="font-size: 2.5rem; margin-bottom: 1rem; opacity:0.3;"></i><span style="font-weight: 700; font-size: 0.9rem; display:block;">NO LOADS ADDED</span><span style="font-size: 0.8rem;">Use the sidebar to add items</span></div>`;
        return;
    }

    container.innerHTML = state.loads.map((l) => {
        let bgIcon;
        let colorIcon;

        if (state.theme === 'dark') {
            switch (l.type) {
                case 'PANELBOARD': bgIcon = '#1e3a8a'; colorIcon = '#93c5fd'; break;
                case 'DISCONNECT': bgIcon = '#064e3b'; colorIcon = '#6ee7b7'; break;
                case 'METER': bgIcon = '#78350f'; colorIcon = '#fcd34d'; break;
                case 'CT_CABINET': bgIcon = '#5b21b6'; colorIcon = '#c4b5fd'; break;
                default: bgIcon = '#334155'; colorIcon = '#e2e8f0'; break;
            }
        } else {
            switch (l.type) {
                case 'PANELBOARD': bgIcon = '#dbeafe'; colorIcon = '#3b82f6'; break;
                case 'DISCONNECT': bgIcon = '#d1fae5'; colorIcon = '#10b981'; break;
                case 'METER': bgIcon = '#fef3c7'; colorIcon = '#f59e0b'; break;
                case 'CT_CABINET': bgIcon = '#f3e8ff'; colorIcon = '#9333ea'; break;
                default: bgIcon = '#e2e8f0'; colorIcon = '#0f172a'; break;
            }
        }

        let mainControlLabel = 'Amps';
        if (l.type === 'METER') mainControlLabel = 'Size';
        if (l.isCustom) mainControlLabel = 'Width';

        let mainControlInputHtml = '';
        if (l.isCustom) {
            mainControlInputHtml = `<div style="position:relative;"><input type="number" step="0.5" class="input-custom" style="padding-right: 1.5rem; width: 100px;" value="${l.customWidth}" onchange="updateCustomWidth('${l.id}', this.value)"><span style="position:absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--text-muted); font-weight:700;">"</span></div>`;
        } else if (l.type === 'METER') {
            mainControlInputHtml = `<select class="input-custom" style="width: 100px; padding: 0 0.5rem;" onchange="updateLoad('${l.id}', 'meterSize', this.value)"><option value="SMALL" ${l.meterSize === 'SMALL' ? 'selected' : ''}>SMALL</option><option value="LARGE" ${l.meterSize === 'LARGE' ? 'selected' : ''}>LARGE</option></select>`;
        } else {
            const availableAmps = l.type === 'PANELBOARD' ? PANEL_AMPS_SORTED : l.type === 'DISCONNECT' ? DISCONNECT_AMPS_SORTED : l.type === 'CT_CABINET' ? CT_CABINET_OPTIONS : AMP_OPTIONS;
            const options = availableAmps.map((a) => {
                let label = `${a}A`;
                if (l.type === 'CT_CABINET' && a === 800) label = '800A+';
                return `<option value="${a}" ${a === l.amps ? 'selected' : ''}>${label}</option>`;
            }).join('');
            mainControlInputHtml = `<select class="input-custom" style="width: 100px; padding: 0 0.5rem;" onchange="updateLoad('${l.id}', 'amps', this.value)">${options}</select>`;
        }

        const mainControlHtml = `<div class="control-group"><label class="input-label">${mainControlLabel}</label>${mainControlInputHtml}</div>`;
        const spacingAfterHtml = `<div class="control-group"><label class="input-label">Gap After</label><div style="position:relative;"><input type="number" step="0.5" min="0" class="input-custom" style="width: 80px; text-align:center; padding-right: 1.4rem;" value="${l.gapAfter}" onchange="updateLoad('${l.id}', 'gapAfter', this.value)"><span style="position:absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-secondary); font-weight:800; pointer-events: none;">"</span></div></div>`;

        let buttonStyle = l.isCustom ? 'background: #f3e8ff; border: 1px solid #d8b4fe;' : 'background: var(--bg-input); border: 2px solid var(--border-subtle);';
        if (state.theme === 'dark' && l.isCustom) buttonStyle = 'background: #4c1d95; border: 1px solid #a78bfa;';
        const buttonIconColor = l.isCustom ? (state.theme === 'dark' ? '#d8b4fe' : '#9333ea') : '#6366f1';
        const buttonsHtml = `<div class="control-group-buttons"><button onclick="toggleCustomMode('${l.id}')" title="Manual Size Override" style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; cursor: pointer; display:flex; align-items:center; justify-content:center; ${buttonStyle} color: ${buttonIconColor}; transition: 0.2s;"><i class="fa-solid fa-ruler-horizontal"></i></button><button onclick="removeLoad('${l.id}')" class="btn-icon-only" style="width: 2.75rem; height: 2.75rem; display:flex; align-items:center; justify-content:center; font-size: 1rem; border: 1px solid var(--border-subtle); border-radius: 0.75rem;"><i class="fa-solid fa-times"></i></button></div>`;

        return `<div class="load-item" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; padding: 1.25rem; border-bottom: 1px solid var(--border-subtle);"><div style="display: flex; gap: 1rem; align-items: center; min-width: 140px; margin-right: auto;"><div style="width: 3rem; height: 3rem; background: ${bgIcon}; color: ${colorIcon}; border-radius: 0.85rem; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.25rem;">${l.type.charAt(0)}</div><div><div style="font-weight: 800; font-size: 0.85rem; color: var(--text-primary);">${l.type}</div><div style="font-family: monospace; font-size: 0.7rem; color: var(--text-muted);">ID: ${l.id.slice(-3)}</div></div></div><div class="load-item-controls">${spacingAfterHtml}${mainControlHtml}${buttonsHtml}</div></div>`;
    }).join('');
}

/* --- EXPORTAR PDF --- */
function renderExportButton() {
    const headerActions = document.getElementById('headerActions');
    if (headerActions && headerActions.innerHTML === '') {
        const btn = document.createElement('button');
        btn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> PDF';
        btn.style.cssText = 'padding: 0.75rem 1.5rem; background: var(--text-primary); color: var(--bg-panel); border: none; border-radius: 0.75rem; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.6rem; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0, 0.15);';
        btn.onclick = exportReport;
        headerActions.appendChild(btn);
    }
}

function exportReport() {
    const printArea = document.getElementById('printArea');
    if (!printArea) return;

    const totalVal = document.getElementById('totalIn').innerText;
    const totalFt = document.getElementById('totalFt').innerText;

    let svgElement = document.getElementById('svgContainer').querySelector('svg');
    let svgContent = '';
    if (svgElement) {
        const clonedSvg = svgElement.cloneNode(true);
        clonedSvg.removeAttribute('width');
        clonedSvg.removeAttribute('height');
        clonedSvg.style.width = '100%';

        const bgRect = clonedSvg.querySelector('rect[fill="#1e293b"], rect[fill="#334155"], rect[fill="#e2e8f0"]');
        if (bgRect) bgRect.setAttribute('fill', '#e2e8f0');
        svgContent = clonedSvg.outerHTML;
    }

    const date = new Date().toLocaleDateString();
    let itemsRows = '';

    if (state.isFeederEnabled) {
        let desc = state.feeder.type === 'DIRECT' ? `Conduit: ${state.feeder.conduitSize}" (${state.feeder.sets} sets)` : `${state.feeder.amps} Amps`;
        if (state.feeder.type === 'CT_CABINET' && state.feeder.amps === 800) desc = '800A+';

        const feederWidth = state.feeder.type === 'DIRECT'
            ? (state.feeder.conduitSize * state.feeder.sets) + (state.feeder.betweenGap * Math.max(0, state.feeder.sets - 1))
            : (state.feeder.type === 'DISCONNECT' ? (DISCONNECT_DATA[state.feeder.amps] || 0) : (state.feeder.amps <= 600 ? 32 : 48));

        let gapCellContent = `${state.feeder.gapAfter.toFixed(1)}"`;
        if (state.feeder.type === 'DIRECT' && state.feeder.sets > 1) {
            gapCellContent += `<br><small style="color:#64748b; font-size: 9pt;">Conduit Gap: ${state.feeder.betweenGap.toFixed(1)}"</small>`;
        }

        itemsRows += `<tr><td><strong>MAIN FEEDER</strong></td><td>${state.feeder.type}</td><td>${desc}</td><td>${feederWidth.toFixed(1)}"</td><td>${gapCellContent}</td></tr>`;
    }

    state.loads.forEach((l, index) => {
        let desc = l.isCustom ? 'Manual Size' : (l.type === 'METER' ? l.meterSize : `${l.amps}A`);
        if (l.type === 'CT_CABINET' && l.amps === 800) desc = '800A+';

        let widthDisplay;
        if (l.isCustom) widthDisplay = l.customWidth;
        else if (l.type === 'PANELBOARD') widthDisplay = PANEL_DATA[l.amps] || 0;
        else if (l.type === 'DISCONNECT') widthDisplay = DISCONNECT_DATA[l.amps] || 0;
        else if (l.type === 'CT_CABINET') widthDisplay = l.amps <= 600 ? 32 : 48;
        else widthDisplay = l.meterSize === 'LARGE' ? 12 : 9.6;

        itemsRows += `<tr><td>${index + 1}. ${l.type}</td><td>${l.isCustom ? 'Custom' : 'Standard'}</td><td>${desc}</td><td>${parseFloat(widthDisplay).toFixed(1)}"</td><td>${l.gapAfter.toFixed(1)}"</td></tr>`;
    });

    printArea.innerHTML = `<div style="padding: 2rem; max-width: 100%; margin: 0 auto; font-family: 'Inter', sans-serif;"><div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #333; padding-bottom: 1rem; margin-bottom: 2rem;"><div><h1 style="margin:0; font-size: 1.5rem; color: #1e293b;">Wireway Calculation Report</h1><p style="margin:0.5rem 0 0; color: #64748b;">Generated on ${date}</p></div><div style="text-align:right;"><div style="font-size: 0.8rem; text-transform:uppercase; color: #64748b; font-weight:700;">Total Length</div><div style="font-size: 2.5rem; font-weight: 900; color: #fb5a3a;">${totalVal}"</div><div style="font-size: 1rem; color: #64748b; font-weight:600;">${totalFt}</div></div></div><div style="margin-bottom: 2rem;"><h3 style="color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Visual Layout</h3><div style="margin-top: 1rem; padding: 1rem;">${svgContent}</div></div><div><h3 style="color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Load Details & Dimensions</h3><table class="print-table"><thead><tr><th>Item Type</th><th>Config</th><th>Rating/Size</th><th>Width</th><th>Gap After</th></tr></thead><tbody>${itemsRows}</tbody></table></div><div style="margin-top: 4rem; text-align: center; color: #cbd5e1; font-size: 0.8rem;"><p>Brightronix Wireway Calculator</p></div></div>`;

    window.print();
}

/* --- CALCULO --- */
function calculateAndRender() {
    renderLoadList();

    let feederWidth = 0;
    if (state.isFeederEnabled) {
        if (state.feeder.type === 'DIRECT') feederWidth = (state.feeder.conduitSize * state.feeder.sets) + (state.feeder.betweenGap * Math.max(0, state.feeder.sets - 1));
        else if (state.feeder.type === 'DISCONNECT') feederWidth = DISCONNECT_DATA[state.feeder.amps] || 0;
        else feederWidth = state.feeder.amps <= 600 ? 32 : 48;
    }

    const calculatedLoads = state.loads.map((l) => {
        let width = 0;
        if (l.isCustom) width = l.customWidth;
        else if (l.type === 'PANELBOARD') width = PANEL_DATA[l.amps] || 0;
        else if (l.type === 'DISCONNECT') width = DISCONNECT_DATA[l.amps] || 0;
        else if (l.type === 'CT_CABINET') width = l.amps <= 600 ? 32 : 48;
        else width = l.meterSize === 'LARGE' ? 12 : 9.6;

        return { ...l, width };
    });

    let total = 0;
    if (state.isFeederEnabled) {
        total += state.feeder.startGap + feederWidth + state.feeder.gapAfter;
    } else if (calculatedLoads.length > 0) {
        total += DEFAULT_SPACING;
    }

    calculatedLoads.forEach((l) => {
        total += l.width + l.gapAfter;
    });

    if (total === 0 && !state.isFeederEnabled) total = DEFAULT_SPACING * 2;

    const elIn = document.getElementById('totalIn');
    const elFt = document.getElementById('totalFt');
    if (elIn) elIn.innerText = total.toFixed(1);
    if (elFt) elFt.innerText = `${(total / 12).toFixed(2)} FT`;

    drawVisualizer(feederWidth, calculatedLoads, total);
}

function drawVisualizer(feederWidth, calculatedLoads, totalWidth) {
    const container = document.getElementById('svgContainer');
    if (!container) return;

    const scale = 5;
    const rectHeight = 60;
    const svgWidth = (totalWidth * scale) + 50;
    const wirewayFill = state.theme === 'light' ? '#e2e8f0' : '#334155';
    const wirewayStroke = 'var(--accent-primary)';

    let svgContent = `<svg width="${svgWidth}" height="150" viewBox="0 0 ${svgWidth} 150" xmlns="http://www.w3.org/2000/svg" style="max-height: 150px; flex-shrink: 0; margin: auto;"><rect x="0" y="30" width="${Math.max(totalWidth * scale, svgWidth - 50)}" height="80" fill="${wirewayFill}" stroke="${wirewayStroke}" stroke-width="2" rx="8" />`;
    let currentX = 0;

    if (state.isFeederEnabled && feederWidth > 0) {
        currentX = state.feeder.startGap;

        let feederFill = '#2563eb';
        let feederLabel = 'MAIN';
        let feederSubLabel = '';

        if (state.feeder.type === 'DISCONNECT') {
            feederFill = '#10b981';
            feederLabel = 'DISC';
            feederSubLabel = `${state.feeder.amps}A`;
        } else if (state.feeder.type === 'CT_CABINET') {
            feederFill = '#8b5cf6';
            feederLabel = 'CT';
            feederSubLabel = state.feeder.amps === 800 ? '800A+' : `${state.feeder.amps}A`;
        } else {
            feederLabel = 'FEED';
            feederSubLabel = `${state.feeder.sets}x ${state.feeder.conduitSize}"`;
        }

        const feederText = `<text x="${feederWidth * scale / 2}" y="${rectHeight / 2 - 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="10" font-weight="bold" font-family="sans-serif">${feederLabel}</text><text x="${feederWidth * scale / 2}" y="${rectHeight / 2 + 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="9" font-weight="bold" font-family="sans-serif">${feederSubLabel}</text>`;

        svgContent += `<g transform="translate(${currentX * scale}, 40)"><rect width="${feederWidth * scale}" height="${rectHeight}" fill="${feederFill}" rx="4" />${feederText}<text x="${(feederWidth * scale) - 5}" y="${rectHeight - 5}" text-anchor="end" fill="white" font-size="11" font-weight="900" font-family="sans-serif" opacity="0.8">${parseFloat(feederWidth).toFixed(1)}"</text></g>`;
        currentX += feederWidth;
    } else if (calculatedLoads.length > 0) {
        currentX = DEFAULT_SPACING;
    }

    calculatedLoads.forEach((l, index) => {
        let gapBefore = 0;
        if (index === 0 && state.isFeederEnabled) gapBefore = state.feeder.gapAfter;
        else if (index > 0) gapBefore = calculatedLoads[index - 1].gapAfter;

        currentX += gapBefore;

        let fill = '#64748b';
        let label = l.type.substring(0, 5);
        let subLabel = '';

        switch (l.type) {
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
                subLabel = l.meterSize.substring(0, 1);
                break;
            case 'CT_CABINET':
                fill = '#8b5cf6';
                label = 'CT';
                subLabel = l.amps === 800 ? '800A+' : `${l.amps}A`;
                break;
            default:
                break;
        }

        const itemText = `<text x="${l.width * scale / 2}" y="${rectHeight / 2 - 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="10" font-weight="bold" font-family="sans-serif">${label}</text><text x="${l.width * scale / 2}" y="${rectHeight / 2 + 7}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="9" font-weight="bold" font-family="sans-serif">${subLabel}</text>`;
        const stroke = l.isCustom ? (state.theme === 'light' ? 'stroke="#9333ea" stroke-width="2"' : 'stroke="#d8b4fe" stroke-width="2"') : '';

        svgContent += `<g transform="translate(${currentX * scale}, 40)"><rect width="${l.width * scale}" height="${rectHeight}" fill="${fill}" rx="4" ${stroke} />${itemText}<text x="${(l.width * scale) - 5}" y="${rectHeight - 5}" text-anchor="end" fill="white" font-size="11" font-weight="900" font-family="sans-serif" opacity="0.8">${l.width.toFixed(1)}"</text></g>`;
        currentX += l.width;
    });

    svgContent += '</svg>';
    container.innerHTML = svgContent;
}

