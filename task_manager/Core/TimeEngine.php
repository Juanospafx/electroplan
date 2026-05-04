<?php
/**
 * Class TimeEngine
 * 
 * Motor principal para cálculos de tiempo, plazos (deadlines) y validación
 * de horarios laborables en el módulo Smart PM.
 */
class TimeEngine {
    // Rango horario: 07:00 a 19:00 (12 horas hábiles)
    private const WORK_START_HOUR = 7;
    private const WORK_END_HOUR = 19;
    
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
     * Obtiene un array de feriados combinando los fijos con los dinámicos (calculados)
     * para un año específico.
     */
    private function getHolidaysForYear(int $year): array {
        $holidays = self::FIXED_HOLIDAYS;
        
        // Feriados dinámicos estadounidenses comunes
        $thanksgiving = date('m-d', strtotime("fourth thursday of november $year"));
        $memorialDay = date('m-d', strtotime("last monday of may $year"));
        $laborDay = date('m-d', strtotime("first monday of september $year"));
        
        $holidays[] = $thanksgiving;
        $holidays[] = $memorialDay;
        $holidays[] = $laborDay;
        
        return $holidays;
    }

    /**
     * Verifica si una fecha específica cae en un día feriado.
     */
    private function isHoliday(DateTime $date): bool {
        $year = (int)$date->format('Y');
        $monthDay = $date->format('m-d');
        $holidays = $this->getHolidaysForYear($year);
        
        return in_array($monthDay, $holidays, true);
    }

    /**
     * Verifica si el día actual (o la fecha simulada) es un feriado.
     * FASE 22: Pausa Física en Días Feriados
     */
    public function isTodayHoliday(DateTime $date = null): bool {
        if ($date === null) {
            $date = new DateTime();
        }
        return $this->isHoliday($date);
    }

    /**
     * ========================================================================
     * MÉTODO AUXILIAR: isWorkingHour()
     * Verifica si una acción ocurre dentro del horario permitido.
     * ========================================================================
     */
    public function isWorkingHour(DateTime $date = null): bool {
        if ($date === null) {
            $date = new DateTime();
        }

        // 1. Domingos son días muertos (0 en formato 'w' de PHP)
        if ((int)$date->format('w') === 0) {
            return false;
        }

        // 2. Feriados son días muertos
        if ($this->isHoliday($date)) {
            return false;
        }

        // 3. Verificar rango de horas (07:00:00 a 18:59:59 es válido)
        $hour = (int)$date->format('G');
        $minute = (int)$date->format('i');
        $second = (int)$date->format('s');
        
        $timeInSeconds = ($hour * 3600) + ($minute * 60) + $second;
        $startInSeconds = self::WORK_START_HOUR * 3600;
        $endInSeconds = self::WORK_END_HOUR * 3600;

        if ($timeInSeconds < $startInSeconds || $timeInSeconds >= $endInSeconds) {
            return false;
        }

        return true;
    }

    /**
     * Avanza la fecha al siguiente día laborable a las 07:00 AM.
     */
    private function jumpToNextWorkingDay(DateTime $date): void {
        do {
            $date->modify('+1 day');
            $date->setTime(self::WORK_START_HOUR, 0, 0);
        } while ((int)$date->format('w') === 0 || $this->isHoliday($date)); // Repetir si es domingo o feriado
    }

    /**
     * Ajusta la fecha a un momento laborable válido si no lo es actualmente.
     */
    private function snapToValidWorkingTime(DateTime $date): void {
        $timeInSeconds = ((int)$date->format('G') * 3600) + ((int)$date->format('i') * 60) + (int)$date->format('s');
        $startInSeconds = self::WORK_START_HOUR * 3600;
        $endInSeconds = self::WORK_END_HOUR * 3600;

        // Si es un día no laborable (Domingo/Feriado) o ya pasó las 19:00, saltar al siguiente día hábil
        if ((int)$date->format('w') === 0 || $this->isHoliday($date) || $timeInSeconds >= $endInSeconds) {
            $this->jumpToNextWorkingDay($date);
        } 
        // Si es un día laborable pero antes de las 07:00, ajustar a las 07:00 de hoy
        elseif ($timeInSeconds < $startInSeconds) {
            $date->setTime(self::WORK_START_HOUR, 0, 0);
        }
    }

    /**
     * ========================================================================
     * MÉTODO PRINCIPAL: calculateDeadline()
     * Calcula la fecha de entrega sumando horas hábiles a una fecha de inicio.
     * ========================================================================
     */
    public function calculateDeadline(DateTime $startTime, float|string $hoursToSum): DateTime {
        // Clonar para evitar modificar la variable original pasada por referencia
        $deadline = clone $startTime;
        
        // FASE 23: Corrección Crítica del TimeEngine (Parseo de Decimales)
        // Limpiar la variable para evitar errores de coma decimal según el locale
        $hoursClean = (float)str_replace(',', '.', (string)$hoursToSum);
        
        error_log("[TimeEngine Debug] Input hours: '{$hoursToSum}' -> Cleaned float: {$hoursClean}");

        // FASE 24: Forzar un mínimo de 10 minutos (0.1666... horas) para tareas con 0 horas
        if ($hoursClean <= 0) {
            $hoursClean = 10 / 60; 
            error_log("[TimeEngine Debug] Hours were <= 0. Forced minimum of 10 minutes.");
        }

        // Asegurarnos de que el cálculo inicie en un punto laborable válido
        $this->snapToValidWorkingTime($deadline);

        // FASE 26: Refactorización Estricta a Minutos Enteros
        // Ej: 0.5 horas * 60 = 30 minutos
        $total_minutes = (int) round($hoursClean * 60);
        $endOfDayMinutes = self::WORK_END_HOUR * 60; // 19 * 60 = 1140

        error_log("[TimeEngine Debug] Total minutes to sum: {$total_minutes}");

        while ($total_minutes > 0) {
            $currentTimeMinutes = ((int)$deadline->format('G') * 60) + (int)$deadline->format('i');
            $remainingMinutesToday = $endOfDayMinutes - $currentTimeMinutes;

            if ($total_minutes <= $remainingMinutesToday) {
                $deadline->modify("+{$total_minutes} minutes");
                $total_minutes = 0; // Finalizamos
            } else {
                $total_minutes -= $remainingMinutesToday;
                $this->jumpToNextWorkingDay($deadline); // Rollover al día siguiente a las 07:00
            }
        }

        error_log("[TimeEngine Debug] Final calculated deadline: " . $deadline->format('Y-m-d H:i:s'));

        return $deadline;
    }
}