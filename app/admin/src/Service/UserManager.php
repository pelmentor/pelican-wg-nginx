<?php

class UserManager {
    /**
     * Get all users from the users file.
     *
     * @return array List of user records
     */
    public static function getAll(): array {
        return self::readFile();
    }

    /**
     * Find a user by ID.
     */
    public static function getById(string $id): ?array {
        $users = self::readFile();
        foreach ($users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Find a user by username (case-insensitive).
     */
    public static function getByUsername(string $username): ?array {
        $users = self::readFile();
        $lower = strtolower($username);
        foreach ($users as $user) {
            if (strtolower($user['username']) === $lower) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Create a new user.
     *
     * @return array The created user record (without password_hash exposed)
     * @throws RuntimeException if the username already exists
     */
    public static function create(string $username, string $password, string $role): array {
        if (self::getByUsername($username) !== null) {
            throw new RuntimeException('Username already exists');
        }

        $validRoles = ['admin', 'operator', 'viewer'];
        if (!in_array($role, $validRoles, true)) {
            throw new RuntimeException('Invalid role: ' . $role);
        }

        $user = [
            'id'            => 'u_' . bin2hex(random_bytes(8)),
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role'          => $role,
            'created_at'    => time(),
            'updated_at'    => time(),
        ];

        self::withLock(function (array &$users) use ($user) {
            $users[] = $user;
        });

        return self::sanitize($user);
    }

    /**
     * Update a user's fields (username, role).
     * Use setPassword() to change passwords.
     */
    public static function update(string $id, array $fields): void {
        // Only allow updating safe fields
        $allowed = ['username', 'role'];
        $updates = array_intersect_key($fields, array_flip($allowed));

        if (isset($updates['role'])) {
            $validRoles = ['admin', 'operator', 'viewer'];
            if (!in_array($updates['role'], $validRoles, true)) {
                throw new RuntimeException('Invalid role: ' . $updates['role']);
            }
        }

        if (isset($updates['username'])) {
            $existing = self::getByUsername($updates['username']);
            if ($existing !== null && $existing['id'] !== $id) {
                throw new RuntimeException('Username already exists');
            }
        }

        self::withLock(function (array &$users) use ($id, $updates) {
            $found = false;
            foreach ($users as &$user) {
                if ($user['id'] === $id) {
                    foreach ($updates as $key => $value) {
                        $user[$key] = $value;
                    }
                    $user['updated_at'] = time();
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new RuntimeException('User not found');
            }
        });
    }

    /**
     * Delete a user by ID.
     */
    public static function delete(string $id): void {
        self::withLock(function (array &$users) use ($id) {
            $original = count($users);
            $users = array_values(array_filter($users, fn(array $u) => $u['id'] !== $id));
            if (count($users) === $original) {
                throw new RuntimeException('User not found');
            }
        });
    }

    /**
     * Verify a plaintext password against a user's stored hash.
     */
    public static function verifyPassword(array $user, string $password): bool {
        return password_verify($password, $user['password_hash'] ?? '');
    }

    /**
     * Set a new password for a user.
     */
    public static function setPassword(string $id, string $newPassword): void {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        self::withLock(function (array &$users) use ($id, $hash) {
            $found = false;
            foreach ($users as &$user) {
                if ($user['id'] === $id) {
                    $user['password_hash'] = $hash;
                    $user['updated_at'] = time();
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new RuntimeException('User not found');
            }
        });
    }

    /**
     * Strip sensitive fields before returning user data to the client.
     */
    public static function sanitize(array $user): array {
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Read the users JSON file.
     */
    private static function readFile(): array {
        if (!file_exists(USERS_FILE)) {
            return [];
        }
        $json = @file_get_contents(USERS_FILE);
        if ($json === false || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Execute a callback with exclusive file locking on the users file.
     * The callback receives the users array by reference.
     */
    private static function withLock(callable $callback): void {
        $dir = dirname(USERS_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fh = fopen(USERS_FILE, 'c+');
        if ($fh === false) {
            throw new RuntimeException('Cannot open users file');
        }

        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            throw new RuntimeException('Cannot lock users file');
        }

        // Read current state under lock
        $json = stream_get_contents($fh);
        $users = [];
        if ($json !== false && $json !== '') {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $users = $data;
            }
        }

        // Execute the callback (may modify $users)
        $callback($users);

        // Write back
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fh);

        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
