<?php
// FASE 34: Sincronización Estricta de Zona Horaria
date_default_timezone_set('America/Santo_Domingo');

/**
 * Class TimeEngine
 * 
 * Motor principal para cálculos de tiempo, plazos (deadlines) y validación
 * de horarios laborables en el módulo Smart PM.
 */
class TimeEngine {
    /**
     * Feriados fijos de Florida/USA (Formato: MM-DD)
     * Se pueden agregar más según las políticas de Brightronix.
     */
    private const FIXED_HOLIDAYS = [
        '01-01', // New Year's Day
        '07-04', // Independence Day
        '11-11', // Veterans Day
        '12-25', // Christmas Day
    ];

    /**
     * FASE AUDITORÍA 4: Caché en memoria para evitar llamadas masivas a strtotime().
     */
    private array $holidayCache = [];

    /**
     * Obtiene un array de feriados combinando los fijos con los dinámicos (calculados)
     * para un año específico.
     */
    private function getHolidaysForYear(int $year): array {
        if (isset($this->holidayCache[$year])) {
            return $this->holidayCache[$year];
        }

        $holidays = self::FIXED_HOLIDAYS;
        
        // Feriados dinámicos estadounidenses comunes
        $holidays[] = date('m-d', strtotime("fourth thursday of november $year")); // Thanksgiving
        $holidays[] = date('m-d', strtotime("last monday of may $year")); // Memorial Day
        $holidays[] = date('m-d', strtotime("first monday of september $year")); // Labor Day
        
        $this->holidayCache[$year] = $holidays;
        return $holidays;
    }

    /**
     * Verifica si una fecha específica cae en un día feriado.
     */
    private function isHoliday(DateTime $date): bool {
        return in_array($date->format('m-d'), $this->getHolidaysForYear((int)$date->format('Y')), true);
    }

    /**
     * Verifica si el día actual (o la fecha simulada) es un feriado.
     */
    public function isTodayHoliday(DateTime $date = null): bool {
        return $this->isHoliday($date ?? new DateTime());
    }

    /**
     * ========================================================================
     * MÉTODO AUXILIAR: isWorkingHour()
     * Verifica si una acción ocurre dentro del horario permitido.
     * ========================================================================
     */
    public function isWorkingHour(DateTime $date = null, string $startStr = '07:00:00', string $endStr = '19:00:00'): bool {
        $date = $date ?? new DateTime();

        // 1. Domingos (0) y Feriados son no laborables
        if ((int)$date->format('w') === 0 || $this->isHoliday($date)) {
            return false;
        }

        $timeInSeconds = ((int)$date->format('G') * 3600) + ((int)$date->format('i') * 60) + (int)$date->format('s');
        $startSeconds = $this->timeToSeconds($startStr);
        $endSeconds = $this->timeToSeconds($endStr);

        return $timeInSeconds >= $startSeconds && $timeInSeconds < $endSeconds;
    }

    private function timeToSeconds(string $time): int {
        $parts = explode(':', $time);
        return ((int)($parts[0] ?? 0) * 3600) + ((int)($parts[1] ?? 0) * 60) + (int)($parts[2] ?? 0);
    }

    /**
     * Avanza la fecha al siguiente día laborable a las 07:00 AM.
     */
    private function jumpToNextWorkingDay(DateTime $date, string $startStr): void {
        $parts = explode(':', $startStr);
        $h = (int)$parts[0];
        $m = (int)($parts[1] ?? 0);
        do {
            // modify('+1 day') + setTime() es DST Safe, previniendo bugs de zonas horarias.
            $date->modify('+1 day')->setTime($h, $m, 0);
        } while ((int)$date->format('w') === 0 || $this->isHoliday($date)); // Repetir si es domingo o feriado
    }

    /**
     * Ajusta la fecha a un momento laborable válido si no lo es actualmente.
     */
    private function snapToValidWorkingTime(DateTime $date, string $startStr, string $endStr): void {
        $timeInSeconds = ((int)$date->format('G') * 3600) + ((int)$date->format('i') * 60) + (int)$date->format('s');
        $startSeconds = $this->timeToSeconds($startStr);
        $endSeconds = $this->timeToSeconds($endStr);

        if ((int)$date->format('w') === 0 || $this->isHoliday($date) || $timeInSeconds >= $endSeconds) {
            $this->jumpToNextWorkingDay($date, $startStr);
        } 
        elseif ($timeInSeconds < $startSeconds) {
            $parts = explode(':', $startStr);
            $date->setTime((int)$parts[0], (int)($parts[1] ?? 0), 0);
        }
    }

    /**
     * ========================================================================
     * MÉTODO PRINCIPAL: calculateDeadline()
     * Calcula la fecha de entrega sumando horas hábiles a una fecha de inicio.
     * ========================================================================
     */
    public function calculateDeadline(DateTime $startTime, int $minutesToSum, string $startStr = '07:00:00', string $endStr = '19:00:00'): DateTime {
        $deadline = clone $startTime;
        
        // Establecer un mínimo de 10 minutos (Seguridad algorítmica)
        $totalSeconds = max(10 * 60, $minutesToSum * 60);

        // Asegurarnos de que el cálculo inicie en un punto laborable válido
        $this->snapToValidWorkingTime($deadline, $startStr, $endStr);

        $startSeconds = $this->timeToSeconds($startStr);
        $endSeconds = $this->timeToSeconds($endStr);
        $workDaySeconds = $endSeconds - $startSeconds;
        
        if ($workDaySeconds <= 0) $workDaySeconds = 12 * 3600; // Failsafe

        while ($totalSeconds > 0) {
            $currentTimeSeconds = ((int)$deadline->format('G') * 3600) + ((int)$deadline->format('i') * 60) + (int)$deadline->format('s');
            $remainingSecondsToday = $endSeconds - $currentTimeSeconds;

            if ($totalSeconds <= $remainingSecondsToday) {
                $deadline->modify("+{$totalSeconds} seconds");
                $totalSeconds = 0;
            } else {
                $totalSeconds -= $remainingSecondsToday;
                $this->jumpToNextWorkingDay($deadline, $startStr);
                
                // FAST FORWARD (Optimización): Si el remanente requiere varios días enteros, los saltamos en bloque.
                if ($totalSeconds >= $workDaySeconds) {
                    $fullDays = (int)floor($totalSeconds / $workDaySeconds);
                    for ($i = 0; $i < $fullDays; $i++) {
                        $this->jumpToNextWorkingDay($deadline, $startStr);
                    }
                    $totalSeconds %= $workDaySeconds;
                }
            }
        }

        return $deadline;
    }
}