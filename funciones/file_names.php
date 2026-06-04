<?php
// funciones/file_names.php
function clean_filename($name) {
    return preg_replace('/[^A-Za-z0-9\-_\.]/', '', str_replace(' ', '_', $name));
}

function export_filename_part(string $value, string $fallback): string {
    $value = preg_replace('/[^A-Za-z0-9]+/', '', $value);
    return $value !== '' ? $value : $fallback;
}

function build_export_filename(string $userName, string $projectName, string $extension, ?DateTimeInterface $date = null): string {
    $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension));
    if ($extension === '') {
        $extension = 'pdf';
    }

    $date = $date ?: new DateTimeImmutable('now');
    return export_filename_part($userName, 'User')
        . '_' . export_filename_part($projectName, 'Project')
        . '_' . $date->format('dm')
        . '.' . $extension;
}
?>
