const app = {
  csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  },
  async postForm(event) {
    event.preventDefault();
    const form = event.target;
    const res = await fetch(form.action, {
      method: 'POST',
      headers: { 'X-CSRF-Token': this.csrfToken() },
      body: new FormData(form),
    });
    if (!res.ok) {
      alert('Request failed');
      return false;
    }
    const data = await res.json().catch(() => ({}));
    if (data.success) {
      window.location.reload();
    }
    return false;
  },
};

function calcServiceAmps(kvaValue, voltageValue) {
  if (!voltageValue) return 'Select Volts';
  const kva = parseFloat(kvaValue);
  if (Number.isNaN(kva)) return '';
  const voltage = parseFloat(String(voltageValue).match(/\d+(\.\d+)?/)?.[0] || '0');
  if (!voltage) return '';
  const isThreePhase = String(voltageValue).includes('Y');
  const amps = isThreePhase ? (kva * 1000) / (voltage * Math.sqrt(3)) : (kva * 1000) / voltage;
  return amps.toFixed(2);
}

function bindServiceTotals() {
  document.querySelectorAll('form').forEach((form) => {
    const voltage = form.querySelector('.js-service-voltage');
    const kva = form.querySelector('.js-service-kva');
    const amps = form.querySelector('.js-service-amps');
    if (!voltage || !kva || !amps) return;

    const update = () => {
      amps.value = calcServiceAmps(kva.value, voltage.value);
    };

    voltage.addEventListener('change', update);
    kva.addEventListener('input', update);
    update();
  });
}

document.addEventListener('DOMContentLoaded', bindServiceTotals);


function applyTheme(theme) {
  const root = document.documentElement;
  root.setAttribute('data-theme', theme);
  try { localStorage.setItem('panelScheduleTheme', theme); } catch(e) {}
  const btn = document.getElementById('themeToggleBtn');
  if (btn) btn.innerHTML = theme === 'light' ? '<i class="fa-solid fa-moon"></i>' : '<i class="fa-solid fa-sun"></i>';
}

document.addEventListener('DOMContentLoaded', () => {
  let t = 'dark';
  try { t = localStorage.getItem('panelScheduleTheme') || 'dark'; } catch(e) {}
  applyTheme(t);
  const btn = document.getElementById('themeToggleBtn');
  if (btn) btn.addEventListener('click', () => {
    const curr = document.documentElement.getAttribute('data-theme') || 'dark';
    applyTheme(curr === 'dark' ? 'light' : 'dark');
  });
});
