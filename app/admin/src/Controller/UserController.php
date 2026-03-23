<?php

class UserController {
    /**
     * GET /admin/users — render the users management page (admin only).
     */
    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'users.manage');
        $page = 'admin_users';
        require __DIR__ . '/../View/layout.php';
    }

    /**
     * GET /api/users — list all users (admin only).
     */
    public function list(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'users.manage');

        $users = UserManager::getAll();
        $sanitized = array_map([UserManager::class, 'sanitize'], $users);
        echo json_encode(['users' => array_values($sanitized)]);
    }

    /**
     * POST /api/users — create a new user (admin only).
     * Body: {"username": "...", "password": "...", "role": "admin|operator|viewer"}
     */
    public function create(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'users.manage');

        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? '';

        if ($username === '' || strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Username must be at least 3 characters']);
            return;
        }

        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            return;
        }

        try {
            $user = UserManager::create($username, $password, $role);
            ActivityLog::log('user.create', 'Created user: ' . $username . ' (' . $role . ')');
            echo json_encode(['success' => true, 'user' => $user]);
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/users/update — update a user (admin only).
     * Body: {"id": "...", "username": "...", "role": "..."}
     */
    public function update(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'users.manage');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';

        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }

        $fields = [];
        if (isset($input['username'])) {
            $username = trim($input['username']);
            if (strlen($username) < 3) {
                http_response_code(400);
                echo json_encode(['error' => 'Username must be at least 3 characters']);
                return;
            }
            $fields['username'] = $username;
        }
        if (isset($input['role'])) {
            $fields['role'] = $input['role'];
        }

        try {
            UserManager::update($id, $fields);

            // Optionally set a new password (from the edit modal)
            if (!empty($input['password'])) {
                if (strlen($input['password']) < 8) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Password must be at least 8 characters']);
                    return;
                }
                UserManager::setPassword($id, $input['password']);
            }

            ActivityLog::log('user.update', 'Updated user: ' . $id);
            echo json_encode(['success' => true]);
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/users/delete — delete a user (admin only).
     * Body: {"id": "..."}
     */
    public function delete(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'users.manage');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';

        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }

        // Prevent deleting yourself
        $currentUser = Auth::getCurrentUser();
        if ($currentUser && $currentUser['id'] === $id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            return;
        }

        try {
            UserManager::delete($id);
            ActivityLog::log('user.delete', 'Deleted user: ' . $id);
            echo json_encode(['success' => true]);
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/users/password — change password.
     * Admins can change any user's password. Non-admins can only change their own.
     * Body: {"id": "...", "current_password": "...", "new_password": "..."}
     */
    public function changePassword(): void {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $targetId = $input['id'] ?? '';
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        $currentUser = Auth::getCurrentUser();
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // Default to changing own password if no ID given
        if ($targetId === '') {
            $targetId = $currentUser['id'];
        }

        // Non-admins can only change their own password
        $isOwnPassword = ($targetId === $currentUser['id']);
        if (!$isOwnPassword) {
            Permission::requirePerm($currentUser['role'], 'users.manage');
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'New password must be at least 8 characters']);
            return;
        }

        // For own password changes, verify current password
        if ($isOwnPassword && $currentPassword !== '') {
            if (!UserManager::verifyPassword($currentUser, $currentPassword)) {
                http_response_code(400);
                echo json_encode(['error' => 'Current password is incorrect']);
                return;
            }
        } elseif ($isOwnPassword) {
            http_response_code(400);
            echo json_encode(['error' => 'Current password is required']);
            return;
        }

        try {
            UserManager::setPassword($targetId, $newPassword);
            ActivityLog::log('user.password_change', 'Password changed for user: ' . $targetId);
            echo json_encode(['success' => true]);
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
