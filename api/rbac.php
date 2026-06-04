<?php

function ep_normalize_role(string $role): string {
    $r = strtolower(trim($role));
    $map = [
        'owner' => 'owner', 'admin' => 'admin', 'project_manager' => 'project_manager',
        'field_manager' => 'field_manager', 'foreman' => 'foreman',
        'internal_worker' => 'internal_worker', 'external_worker' => 'external_worker',
        'technician' => 'internal_worker', 'viewer' => 'external_worker'
    ];
    return $map[$r] ?? 'external_worker';
}

function getUserRoleInProject(PDO $pdo, int $userId, int $projectId): string {
    $stmt = $pdo->prepare("SELECT role FROM user_project_roles WHERE user_id=? AND project_id=? LIMIT 1");
    try { $stmt->execute([$userId, $projectId]); $r = $stmt->fetchColumn(); if ($r) return ep_normalize_role((string)$r); } catch(Throwable $e) {}
    $stmt2 = $pdo->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
    $stmt2->execute([$userId]);
    return ep_normalize_role((string)$stmt2->fetchColumn());
}

function canAccessProject(PDO $pdo, int $userId, int $projectId): bool {
    $role = getUserRoleInProject($pdo, $userId, $projectId);
    if (in_array($role, ['owner','admin'], true)) return true;
    $stmt = $pdo->prepare("SELECT 1 FROM directory WHERE project_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$projectId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function canAccessTaskManager(PDO $pdo, int $userId, int $projectId): bool {
    $role = getUserRoleInProject($pdo, $userId, $projectId);
    return in_array($role, ['owner','admin','project_manager'], true);
}
function canCreateTask(PDO $pdo, int $userId, int $projectId): bool {
    $role = getUserRoleInProject($pdo, $userId, $projectId);
    return in_array($role, ['owner','admin','project_manager','foreman'], true);
}
function canDeleteTask(PDO $pdo, int $userId, int $taskId): bool { return false; }
function requiresApprovalForTask(PDO $pdo, int $userId, int $projectId): bool { return getUserRoleInProject($pdo,$userId,$projectId)==='foreman'; }

function canViewFolder(PDO $pdo, int $userId, int $projectId, int $folderId): bool {
    if (!canAccessProject($pdo,$userId,$projectId)) return false;
    $role = getUserRoleInProject($pdo, $userId, $projectId);
    if (in_array($role, ['owner','admin','project_manager'], true)) return true;
    $stmt = $pdo->prepare("SELECT LOWER(name) FROM folders WHERE id=? AND project_id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$folderId,$projectId]); $name = (string)$stmt->fetchColumn();
    $allowedFM = ['drawings','plans','photos','rfi','panel schedule','panel meter/tag','panel tags'];
    if (in_array($role, ['field_manager','foreman'], true)) return in_array($name, $allowedFM, true);
    if ($role === 'internal_worker') return in_array($name, ['drawings','photos','plans'], true);
    if ($role === 'external_worker') {
        $q = $pdo->prepare("SELECT 1 FROM folder_visibility_rules WHERE folder_id=? AND ((subject_type='user' AND subject_id=?) OR (subject_type='role' AND subject_value=?)) AND allow_view=1 LIMIT 1");
        $q->execute([$folderId,$userId,$role]);
        return (bool)$q->fetchColumn();
    }
    return false;
}
function canViewFile(PDO $pdo, int $userId, int $projectId, int $fileId): bool {
    $st = $pdo->prepare("SELECT folder_id FROM files WHERE id=? AND project_id=? AND deleted_at IS NULL LIMIT 1");
    $st->execute([$fileId,$projectId]);
    $folderValue = $st->fetchColumn();
    if ($folderValue === false) return false;
    $folderId = (int)$folderValue;
    $role = getUserRoleInProject($pdo,$userId,$projectId);
    if (in_array($role,['owner','admin'],true)) return true;
    $q = $pdo->prepare("SELECT allow_view,deny_view FROM file_visibility_rules WHERE file_id=? AND ((subject_type='user' AND subject_id=?) OR (subject_type='role' AND subject_value=?)) ORDER BY id DESC LIMIT 1");
    try { $q->execute([$fileId,$userId,$role]); $r=$q->fetch(PDO::FETCH_ASSOC); if($r){ if((int)$r['deny_view']===1) return false; if((int)$r['allow_view']===1) return true; }} catch(Throwable $e) {}
    return $folderId > 0 ? canViewFolder($pdo,$userId,$projectId,$folderId) : canAccessProject($pdo,$userId,$projectId);
}
function canUploadToFolder(PDO $pdo, int $userId, int $projectId, ?int $folderId): bool {
    $role = getUserRoleInProject($pdo,$userId,$projectId);
    if (in_array($role,['owner','admin','project_manager','field_manager','foreman'],true)) return true;
    if ($role==='internal_worker' && $folderId){
        $s=$pdo->prepare("SELECT LOWER(name) FROM folders WHERE id=? LIMIT 1"); $s->execute([$folderId]); return ((string)$s->fetchColumn())==='photos';
    }
    return false;
}
function canDownloadFile(PDO $pdo,int $userId,int $projectId,int $fileId): bool { $role=getUserRoleInProject($pdo,$userId,$projectId); if($role==='external_worker') return false; return canViewFile($pdo,$userId,$projectId,$fileId);} 
function canEditFile(PDO $pdo,int $userId,int $projectId,int $fileId): bool { $role=getUserRoleInProject($pdo,$userId,$projectId); if($role==='external_worker') return false; return canViewFile($pdo,$userId,$projectId,$fileId);} 
function canDeleteFile(PDO $pdo,int $userId,int $projectId,int $fileId): bool { $role=getUserRoleInProject($pdo,$userId,$projectId); return in_array($role,['owner','admin','project_manager'],true); }
