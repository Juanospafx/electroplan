const scheduleTbody = document.getElementById('scheduleTbody');
const saveScheduleBtn = document.getElementById('saveScheduleBtn');
const recalcBtn = document.getElementById('recalcBtn');

const panelId = window.panelScheduleConfig?.panelId;
const polesConfig = window.panelScheduleConfig?.polesConfig || '42';
const balanceBadge = document.getElementById('balanceBadge');
const balanceMessage = document.getElementById('balanceMessage');
const phaseA = document.getElementById('phaseA');
const phaseB = document.getElementById('phaseB');
const phaseC = document.getElementById('phaseC');
const addRowBtn = document.getElementById('addRowBtn');
const removeRowBtn = document.getElementById('removeRowBtn');

function currentVoltage() {
  const input = document.querySelector('input[name="voltage"], select[name="voltage"]');
  return input ? input.value : '';
}

function currentPhaseWire() {
  const input = document.querySelector('select[name="phase_wire"]');
  return input ? input.value : '';
}

function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

function apiUrl(path) {
  const base = window.panelScheduleConfig?.baseUrl ?? '';
  return `${base}${path}`;
}

let engine;
let lastCalculatedAmps = { A: 0, B: 0, C: 0 };

function spanSelect(value) {
  let options = window.scheduleConfig?.spanOptions || ['1', '2', '3', '4'];

  const pw = currentPhaseWire();
  const is1PH = pw && String(pw).includes('1PH');

  if (is1PH) {
    options = options.filter((o) => parseInt(o) <= 2);
  } else {
    options = options.filter((o) => parseInt(o) <= 3);
  }

  const select = document.createElement('select');
  select.className = 'form-select form-select-sm span-select';
  options.forEach((opt) => {
    const option = document.createElement('option');
    option.value = opt;
    option.textContent = opt;
    if (opt === value) option.selected = true;
    select.appendChild(option);
  });
  return select;
}

function categorySelect(value) {
  const options = window.scheduleConfig?.categories || [];
  const select = document.createElement('select');
  select.className = 'form-select form-select-sm category-select';
  options.forEach((opt) => {
    const option = document.createElement('option');
    option.value = opt;
    option.textContent = opt;
    if (opt === value) option.selected = true;
    select.appendChild(option);
  });
  return select;
}

function unitSelect(value) {
  const options = window.scheduleConfig?.units || ['VA', 'KVA'];
  const select = document.createElement('select');
  select.className = 'form-select form-select-sm unit-select';
  options.forEach((opt) => {
    const option = document.createElement('option');
    option.value = opt;
    option.textContent = opt;
    if (opt === value) option.selected = true;
    select.appendChild(option);
  });
  return select;
}

function textInput(value, cls) {
  const input = document.createElement('input');
  input.className = `form-control form-control-sm ${cls}`;
  input.value = value || '';
  return input;
}

function numberInput(value, cls) {
  const input = document.createElement('input');
  input.type = 'number';
  input.step = '0.01';
  input.className = `form-control form-control-sm ${cls}`;
  input.value = value ?? '';
  return input;
}

function renderRow(index) {
  const tr = document.createElement('tr');
  const left = engine.left[index];
  const right = engine.right[index];

  // Left Ckt Number (Odd)
  const tdLeftCkt = document.createElement('td');
  tdLeftCkt.className = 'text-center fw-bold';
  tdLeftCkt.textContent = (index * 2) + 1;

  // Right Ckt Number (Even)
  const tdRightCkt = document.createElement('td');
  tdRightCkt.className = 'text-center fw-bold';
  tdRightCkt.textContent = (index * 2) + 2;

  // Phase Label
  const tdPhase = document.createElement('td');
  tdPhase.className = 'text-center fw-bold';
  const phaseVal = assignPhase(null, index);
  tdPhase.textContent = phaseVal;

  if (phaseVal === 'A') {
    tdPhase.style.backgroundColor = '#ffcdd2';
  } else if (phaseVal === 'B') {
    tdPhase.style.backgroundColor = '#fff9c4';
  } else if (phaseVal === 'C') {
    tdPhase.style.backgroundColor = '#bbdefb';
  } else {
    tdPhase.classList.add('bg-light');
  }

  const leftSpan = spanSelect(left.breaker_span || '1');
  const leftDesc = textInput(left.description, 'left-desc');
  const leftLoad = numberInput(left.load_value, 'left-load');
  const leftUnit = unitSelect(left.load_unit || 'VA');
  const leftCat = categorySelect(left.load_category || 'lighting');
  const leftNotes = textInput(left.notes, 'left-notes');

  const rightSpan = spanSelect(right.breaker_span || '1');
  const rightDesc = textInput(right.description, 'right-desc');
  const rightLoad = numberInput(right.load_value, 'right-load');
  const rightUnit = unitSelect(right.load_unit || 'VA');
  const rightCat = categorySelect(right.load_category || 'lighting');
  const rightNotes = textInput(right.notes, 'right-notes');

  const cells = [
    tdLeftCkt, leftSpan, leftDesc, leftLoad, leftUnit, leftCat, leftNotes,
    tdPhase,
    rightNotes, rightCat, rightUnit, rightLoad, rightDesc, rightSpan, tdRightCkt
  ];

  cells.forEach((el) => {
    const td = document.createElement('td');
    td.appendChild(el);
    tr.appendChild(td);
  });

  if (left.disabled) {
    tr.querySelectorAll('td').forEach((td, idx) => {
      if (idx >= 1 && idx <= 6) { // Adjust indices for new layout
        td.classList.add('slot-disabled');
        const el = td.querySelector('input,select');
        if (el) el.disabled = true;
      }
    });
  }

  if (right.disabled) {
    tr.querySelectorAll('td').forEach((td, idx) => {
      if (idx >= 8 && idx <= 13) { // Adjust indices for new layout
        td.classList.add('slot-disabled');
        const el = td.querySelector('input,select');
        if (el) el.disabled = true;
      }
    });
  }

  leftSpan.addEventListener('change', (e) => {
    engine.applySpan('L', index, e.target.value);
    render();
  });
  rightSpan.addEventListener('change', (e) => {
    engine.applySpan('R', index, e.target.value);
    render();
  });

  leftDesc.addEventListener('input', (e) => { left.description = e.target.value; });
  leftLoad.addEventListener('input', (e) => { left.load_value = e.target.value; updatePhaseTotals(); });
  leftUnit.addEventListener('change', (e) => { left.load_unit = e.target.value; updatePhaseTotals(); });
  leftCat.addEventListener('change', (e) => { left.load_category = e.target.value; });
  leftNotes.addEventListener('input', (e) => { left.notes = e.target.value; });

  rightDesc.addEventListener('input', (e) => { right.description = e.target.value; });
  rightLoad.addEventListener('input', (e) => { right.load_value = e.target.value; updatePhaseTotals(); });
  rightUnit.addEventListener('change', (e) => { right.load_unit = e.target.value; updatePhaseTotals(); });
  rightCat.addEventListener('change', (e) => { right.load_category = e.target.value; });
  rightNotes.addEventListener('input', (e) => { right.notes = e.target.value; });

  return tr;
}

function render() {
  scheduleTbody.innerHTML = '';
  for (let i = 0; i < engine.left.length; i += 1) {
    scheduleTbody.appendChild(renderRow(i));
  }
  updatePhaseTotals();
}

function assignPhase(side, rowIndex) {
  const pw = currentPhaseWire();
  const sequence = (pw && pw.includes('1PH')) ? ['A', 'B'] : ['A', 'B', 'C'];
  return sequence[rowIndex % sequence.length];
}

function parseVoltage(val) {
  const match = String(val || '').match(/\d+(\.\d+)?/);
  return match ? parseFloat(match[0]) : 0;
}

function isThreePhase(phaseWire, voltageLabel) {
  if (String(phaseWire).includes('3PH')) return true;
  if (String(voltageLabel).includes('Y')) return true;
  return false;
}

function slotKva(slot) {
  const value = parseFloat(slot.load_value || slot.load_va || 0);
  if (!value) return 0;
  const unit = String(slot.load_unit || 'VA').toUpperCase();
  return unit === 'KVA' ? value : value / 1000;
}

function updatePhaseTotals() {
  if (!engine) return;
  const voltageLabel = currentVoltage();
  const voltageLL = parseVoltage(voltageLabel);
  
  // Determine L-N voltage for per-phase amp calculation
  let voltageLN = 0;
  if (voltageLL > 0) {
    // If 240V system (usually 240/120), LN is half. For 208/480 (Wye), LN is / sqrt(3)
    if (voltageLabel.includes('240')) {
      voltageLN = voltageLL / 2; 
    } else {
      voltageLN = voltageLL / Math.sqrt(3);
    }
  }

  const threePhase = isThreePhase(currentPhaseWire(), voltageLabel);
  const amps = { A: 0, B: 0, C: 0 };
  const kva = { A: 0, B: 0, C: 0 };

  ['left', 'right'].forEach((sideKey) => {
    const slots = engine[sideKey];
    slots.forEach((slot, index) => {
      if (slot.disabled || slot.span_head_id) return;
      const k = slotKva(slot);
      if (!k) return;
      
      const span = parseInt(slot.breaker_span || 1);
      const kPerPhase = k / span;
      
      for (let i = 0; i < span; i++) {
        const phase = assignPhase(sideKey === 'left' ? 'L' : 'R', index + i);
        kva[phase] += kPerPhase;
        if (voltageLN > 0) {
          amps[phase] += (kPerPhase * 1000) / voltageLN;
        }
      }
    });
  });

  lastCalculatedAmps = amps;
  phaseA.textContent = `${amps.A.toFixed(2)} A / ${kva.A.toFixed(2)} kVA`;
  phaseB.textContent = `${amps.B.toFixed(2)} A / ${kva.B.toFixed(2)} kVA`;
  phaseC.textContent = `${amps.C.toFixed(2)} A / ${kva.C.toFixed(2)} kVA`;

  const is1PH = String(currentPhaseWire()).includes('1PH');

  if (phaseC && phaseC.parentElement) {
    phaseC.parentElement.style.display = is1PH ? 'none' : '';
  }

  const fA = document.getElementById('footerAmpsA');
  const fB = document.getElementById('footerAmpsB');
  const fC = document.getElementById('footerAmpsC');
  if (fA) fA.textContent = amps.A.toFixed(2);
  if (fB) fB.textContent = amps.B.toFixed(2);
  if (fC) fC.textContent = amps.C.toFixed(2);

  const fCContainer = document.getElementById('footerPhaseCContainer');
  if (fCContainer) {
    fCContainer.style.display = is1PH ? 'none' : '';
  }

  const imbalance = computeImbalance(amps, kva, threePhase, is1PH);
  setBalanceBadgeLocal(imbalance, threePhase, is1PH);
}

function bindHeaderListeners() {
  const voltageInput = document.querySelector('input[name="voltage"], select[name="voltage"]');
  const phaseInput = document.querySelector('select[name="phase_wire"]');
  const polesInput = document.querySelector('select[name="poles_config"]');

  if (voltageInput) voltageInput.addEventListener('change', updatePhaseTotals);
  if (phaseInput) phaseInput.addEventListener('change', render);
  if (polesInput) {
    polesInput.addEventListener('change', (e) => {
      if (!engine) return;
      const newConfig = e.target.value;
      const currentData = engine.toJSON();
      engine = new ScheduleEngine(newConfig, currentData);
      render();
    });
  }
}

function setBalanceBadge(status, message) {
  if (!balanceBadge || !balanceMessage) return;
  balanceBadge.textContent = status || '--';
  balanceMessage.textContent = message || '';
  balanceBadge.className = 'badge';
  if (status === 'FAIL') balanceBadge.classList.add('bg-danger');
  else if (status === 'WARN') balanceBadge.classList.add('bg-warning', 'text-dark');
  else if (status === 'OK') balanceBadge.classList.add('bg-success');
  else balanceBadge.classList.add('bg-secondary');
}

function computeImbalance(amps, kva, threePhase, is1PH) {
  if (!threePhase && !is1PH) return null;

  const phases = is1PH ? ['A', 'B'] : ['A', 'B', 'C'];
  
  // Try calculating with Amps first
  let total = 0;
  phases.forEach(p => total += amps[p]);
  let avg = total / phases.length;
  
  let useKva = false;
  // Fallback to KVA if Amps are 0
  if (avg === 0) {
    total = 0;
    phases.forEach(p => total += kva[p]);
    avg = total / phases.length;
    useKva = true;
  }
  
  if (avg === 0) return 0;

  let maxDev = 0;
  phases.forEach(p => {
    const val = useKva ? kva[p] : amps[p];
    const dev = Math.abs(val - avg);
    if (dev > maxDev) maxDev = dev;
  });

  return (maxDev / avg) * 100;
}

function setBalanceBadgeLocal(imbalance, threePhase, is1PH) {
  if (!threePhase && !is1PH) {
    setBalanceBadge('OK', 'Select 3PH or 1PH to compute imbalance');
    return;
  }
  const value = Number.isFinite(imbalance) ? imbalance : 0;
  let status = 'OK';
  if (value > 20) status = 'FAIL';
  else if (value > 10) status = 'WARN';
  setBalanceBadge(status, `Imbalance ${value.toFixed(2)}%`);
}

async function loadSchedule() {
  const res = await fetch(apiUrl(`/api/panels/${panelId}/schedule`));
  if (!res.ok) throw new Error('Failed to load schedule');
  const data = await res.json();
  // FIX: Prioritize polesConfig from DB (window.panelScheduleConfig) to ensure sync with header
  const config = polesConfig || data.poles_config || '42';
  engine = new ScheduleEngine(config, data);
  render();
}

async function saveSchedule() {
  const res = await fetch(apiUrl(`/api/panels/${panelId}/schedule`), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken(),
    },
    body: JSON.stringify(engine.toJSON()),
  });
  if (!res.ok) throw new Error('Save failed');
  return res.json();
}

async function recalcPanel() {
  const res = await fetch(apiUrl(`/api/panels/${panelId}/recalc`), {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken() },
  });
  if (!res.ok) throw new Error('Recalc failed');
  return res.json();
}

saveScheduleBtn.addEventListener('click', async () => {
  const mainInput = document.querySelector('[name="main_size_type"]');
  if (mainInput) {
    const mainLimit = parseFloat(mainInput.value);
    if (!isNaN(mainLimit) && mainLimit > 0) {
      const maxAmps = Math.max(lastCalculatedAmps.A, lastCalculatedAmps.B, lastCalculatedAmps.C);
      if (maxAmps > mainLimit) {
        alert(`Cannot save: Total Load (${maxAmps.toFixed(2)} A) exceeds Main Breaker size (${mainLimit} A).`);
        return;
      }
    }
  }
  try {
    const data = await saveSchedule();
    if (data.panel) {
      setBalanceBadge(data.panel.balance_status, data.panel.balance_message);
    }
    alert('Schedule saved');
  } catch (err) {
    alert(err.message || 'Error');
  }
});

recalcBtn.addEventListener('click', async () => {
  try {
    await saveSchedule();
    const data = await recalcPanel();
    if (data.panel) {
      setBalanceBadge(data.panel.balance_status, data.panel.balance_message);
    }
    alert('Recalculated');
  } catch (err) {
    alert(err.message || 'Error');
  }
});

if (addRowBtn) {
  addRowBtn.addEventListener('click', () => {
    if (!engine) return;
    const currentData = engine.toJSON();
    const currentPoles = parseInt(currentData.poles_config) || 42;
    const newPoles = currentPoles + 2;
    currentData.poles_config = newPoles;
    
    engine = new ScheduleEngine(newPoles, currentData);
    render();
    
    // Optional: Update the UI select if it exists to reflect custom size
    const select = document.querySelector('select[name="poles_config"]');
    if (select) {
      // If option doesn't exist, create it
      if (![...select.options].some(o => o.value == newPoles)) {
        const opt = document.createElement('option');
        opt.value = newPoles;
        opt.textContent = newPoles;
        select.appendChild(opt);
      }
      select.value = newPoles;
    }
  });
}

if (removeRowBtn) {
  removeRowBtn.addEventListener('click', () => {
    if (!engine) return;
    // Logic to remove row is handled by resizing engine with fewer poles
    // But we need to be careful not to delete data without warning? 
    // For now, simple decrement.
    const currentData = engine.toJSON();
    const currentPoles = parseInt(currentData.poles_config) || 42;
    if (currentPoles <= 2) return;
    
    const newPoles = currentPoles - 2;
    currentData.poles_config = newPoles;
    
    // Trim arrays
    const newRows = Math.ceil(newPoles / 2);
    currentData.left = currentData.left.slice(0, newRows);
    currentData.right = currentData.right.slice(0, newRows);
    
    engine = new ScheduleEngine(newPoles, currentData);
    render();

    const select = document.querySelector('select[name="poles_config"]');
    if (select) {
       if (![...select.options].some(o => o.value == newPoles)) {
        const opt = document.createElement('option');
        opt.value = newPoles;
        opt.textContent = newPoles;
        select.appendChild(opt);
      }
      select.value = newPoles;
    }
  });
}

loadSchedule()
  .then(() => {
    updatePhaseTotals();
    bindHeaderListeners();
    recalcPanel().then((data) => {
      if (data.panel) {
        setBalanceBadge(data.panel.balance_status, data.panel.balance_message);
      }
    }).catch(() => {});
  })
  .catch((err) => alert(err.message || 'Load error'));
