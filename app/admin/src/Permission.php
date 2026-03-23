<?php

class Permission {
    const PERMS = [
        'admin'    => ['*'],
        'operator' => [
            'dashboard.view',
            'console.read',
            'console.write',
            'files.read',
            'files.write',
            'files.delete',
            'settings.view',
            'logs.view',
        ],
        'viewer'   => [
            'dashboard.view',
            'console.read',
            'files.read',
            'logs.view',
        ],
    ];

    /**
     * Check whether a role has a specific permission.
     * Supports exact match and wildcard patterns (e.g. 'console.*' matches 'console.read').
     */
    public static function check(string $role, string $perm): bool {
        $perms = self::PERMS[$role] ?? [];

        // Wildcard role (admin)
        if (in_array('*', $perms, true)) {
            return true;
        }

        foreach ($perms as $p) {
            if ($p === $perm) {
                return true;
            }
            // Wildcard permission: 'console.*' matches 'console.read', 'console.write', etc.
            if (str_ends_with($p, '.*')) {
                $prefix = substr($p, 0, -2); // Remove '.*' to get 'console'
                if (str_starts_with($perm, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Require a permission or abort with 403.
     */
    public static function requirePerm(string $role, string $perm): void {
        if (!self::check($role, $perm)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }
    }
}
