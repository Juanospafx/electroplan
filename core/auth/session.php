<?php
// core/auth/session.php
session_start();

function normalizeRole($role): string {
    $r = strtolower(trim((string)$role));

    // Compatibilidad con typo histórico
    if ($r === 'viwer') {
        $r = 'viewer';
    }

    // Roles válidos del sistema
    $allowed = ['admin', 'technician', 'viewer'];
    if (!in_array($r, $allowed, true)) {
        $r = 'viewer';
    }

    return $r;
}

// Verificamos no solo el ID, sino tambien el username y el role.
// Si falta CUALQUIERA de los tres, cerramos sesion y mandamos al login.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // Destruir sesion corrupta por si acaso
    session_destroy();
    session_unset();

    // Redirigir al login
    header("Location: ../pages/login.php");
    exit();
}

// Normalizamos el rol de sesión para evitar bypass por valores inesperados/typos.
$_SESSION['role'] = normalizeRole($_SESSION['role']);

// Opcional: Funcion para chequear roles especificos
function requireRole($role) {
    $required = normalizeRole($role);
    if ($_SESSION['role'] !== $required && $_SESSION['role'] !== 'admin') {
        die("Acceso denegado: Se requieren permisos de " . $required);
    }
}
?>
