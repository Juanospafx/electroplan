<!-- Custom Calendar Styles & Scripts (Global Flatpickr) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    /* ESTILOS COMPACTOS PARA EL CALENDARIO (FLATPICKR) */
    .flatpickr-calendar {
        width: 280px !important;
        background: var(--bg-card) !important;
        border: 1px solid var(--border-subtle) !important;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5) !important;
        border-radius: 12px !important;
        font-family: 'Poppins', sans-serif !important;
        padding: 5px;
    }
    .flatpickr-months .flatpickr-month, 
    .flatpickr-current-month .flatpickr-monthDropdown-months,
    .flatpickr-current-month input.cur-year,
    .flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month {
        color: var(--text-white) !important;
        fill: var(--text-white) !important;
    }
    /* Ajuste dinámico de la lista de meses */
    .flatpickr-monthDropdown-months {
        background: var(--bg-card) !important;
        color: var(--text-white) !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
    }
    .flatpickr-monthDropdown-month {
        background: var(--bg-card);
        color: var(--text-white);
    }
    /* Nuevo selector de año desplegable */
    .cur-year-select {
        background: transparent !important;
        color: var(--text-white) !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        border: none !important;
        cursor: pointer;
    }
    .cur-year-select option { background: var(--bg-card); color: var(--text-white); }
    .flatpickr-current-month {
        display: flex !important;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    .flatpickr-current-month input.cur-year {
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        text-align: center;
    }
    .flatpickr-weekdays, span.flatpickr-weekday {
        color: var(--text-gray) !important;
        font-size: 0.75rem !important;
    }
    .dayContainer { width: 270px !important; min-width: 270px !important; max-width: 270px !important; }
    .flatpickr-day {
        color: var(--text-white) !important;
        font-size: 0.85rem !important;
        border-radius: 6px !important;
        max-width: 36px !important;
        height: 36px !important;
        line-height: 36px !important;
    }
    .flatpickr-day:hover { background: var(--bg-input) !important; border-color: var(--border-subtle) !important; }
    .flatpickr-day.selected, .flatpickr-day.selected:hover, .flatpickr-day.selected:focus {
        background: var(--primary) !important; border-color: var(--primary) !important; color: white !important;
    }
    .flatpickr-day.today { border-color: var(--primary) !important; }
    .flatpickr-day.flatpickr-disabled { color: var(--text-muted) !important; }
    .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { opacity: 0.3 !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const datepickers = document.querySelectorAll('.app-datepicker');
        datepickers.forEach(picker => {
            flatpickr(picker, {
                dateFormat: picker.dataset.dateFormat || "Y-m-d",
                altInput: true,
                altFormat: picker.dataset.altFormat || "F j, Y",
                disableMobile: true,
                position: picker.dataset.position || "below center",
                minDate: picker.dataset.minDate || null,
                maxDate: picker.dataset.maxDate || null,
                
                // Reemplazar el input de año nativo por un Select del 2024 al 2029
                onReady: function(selectedDates, dateStr, instance) {
                    const yearWrapper = instance.currentYearElement.parentNode;
                    yearWrapper.style.display = 'none'; // Ocultar el input con flechas
                    
                    const select = document.createElement('select');
                    select.className = 'cur-year-select';
                    
                    for (let y = 2024; y <= 2029; y++) {
                        const opt = document.createElement('option');
                        opt.value = y; opt.text = y;
                        select.appendChild(opt);
                    }
                    
                    select.value = instance.currentYear;
                    select.addEventListener('change', function(e) {
                        instance.changeYear(parseInt(e.target.value));
                    });
                    
                    instance.customYearSelect = select;
                    instance.monthNav.querySelector('.flatpickr-current-month').appendChild(select);
                },
                onYearChange: function(selectedDates, dateStr, instance) {
                    if (instance.customYearSelect) {
                        instance.customYearSelect.value = instance.currentYear;
                    }
                }
            });
        });
    });
</script>