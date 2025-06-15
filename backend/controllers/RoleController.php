<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class RoleController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function getRoles() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    r.*,
                    COUNT(u.id) as user_count
                FROM roles r
                LEFT JOIN users u ON r.role_name = u.role
                GROUP BY r.id
                ORDER BY r.role_name
            ");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['roles' => $roles]);

        } catch (PDOException $e) {
            error_log("Error fetching roles: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch roles'], 500);
        }
    }

    public function getRole($id) {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    r.*,
                    GROUP_CONCAT(p.permission_name) as permissions
                FROM roles r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE r.id = ?
                GROUP BY r.id
            ");
            $stmt->execute([$id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$role) {
                sendJsonResponse(['error' => 'Role not found'], 404);
                return;
            }

            // Convert permissions string to array
            $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];

            sendJsonResponse(['role' => $role]);

        } catch (PDOException $e) {
            error_log("Error fetching role: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch role'], 500);
        }
    }

    public function createRole() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $role_name = $_POST['role_name'] ?? null;
            $description = $_POST['description'] ?? null;
            $permissions = $_POST['permissions'] ?? [];
            $parent_role_id = $_POST['parent_role_id'] ?? null;

            if (!$role_name) {
                sendJsonResponse(['error' => 'Role name is required'], 400);
                return;
            }

            // Check if role name already exists
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = ?");
            $stmt->execute([$role_name]);
            if ($stmt->fetch()) {
                sendJsonResponse(['error' => 'Role name already exists'], 400);
                return;
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Insert role
                $stmt = $this->db->prepare("
                    INSERT INTO roles (
                        role_name, description, parent_role_id, created_at
                    ) VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$role_name, $description, $parent_role_id]);
                $role_id = $this->db->lastInsertId();

                // Assign permissions
                if (!empty($permissions)) {
                    $stmt = $this->db->prepare("
                        INSERT INTO role_permissions (
                            role_id, permission_id
                        ) VALUES (?, ?)
                    ");
                    foreach ($permissions as $permission_id) {
                        $stmt->execute([$role_id, $permission_id]);
                    }
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'role_created',
                    "Created role: {$role_name}"
                );

                sendJsonResponse([
                    'message' => 'Role created successfully',
                    'role_id' => $role_id
                ]);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error creating role: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create role'], 500);
        }
    }

    public function updateRole() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $role_id = $_POST['role_id'] ?? null;
            $role_name = $_POST['role_name'] ?? null;
            $description = $_POST['description'] ?? null;
            $permissions = $_POST['permissions'] ?? [];
            $parent_role_id = $_POST['parent_role_id'] ?? null;
            $status = $_POST['status'] ?? null;

            if (!$role_id || !$role_name) {
                sendJsonResponse(['error' => 'Role ID and name are required'], 400);
                return;
            }

            // Check if role name exists for other roles
            $stmt = $this->db->prepare("
                SELECT id FROM roles 
                WHERE role_name = ? AND id != ?
            ");
            $stmt->execute([$role_name, $role_id]);
            if ($stmt->fetch()) {
                sendJsonResponse(['error' => 'Role name already exists'], 400);
                return;
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Update role
                $stmt = $this->db->prepare("
                    UPDATE roles 
                    SET role_name = ?,
                        description = ?,
                        parent_role_id = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $role_name,
                    $description,
                    $parent_role_id,
                    $status,
                    $role_id
                ]);

                // Update permissions
                $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$role_id]);

                if (!empty($permissions)) {
                    $stmt = $this->db->prepare("
                        INSERT INTO role_permissions (
                            role_id, permission_id
                        ) VALUES (?, ?)
                    ");
                    foreach ($permissions as $permission_id) {
                        $stmt->execute([$role_id, $permission_id]);
                    }
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'role_updated',
                    "Updated role ID: {$role_id}"
                );

                sendJsonResponse(['message' => 'Role updated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error updating role: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update role'], 500);
        }
    }

    public function deleteRole() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $role_id = $_POST['role_id'] ?? null;
            
            if (!$role_id) {
                sendJsonResponse(['error' => 'Role ID is required'], 400);
                return;
            }

            // Check if role exists and get its name
            $stmt = $this->db->prepare("SELECT role_name FROM roles WHERE id = ?");
            $stmt->execute([$role_id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$role) {
                sendJsonResponse(['error' => 'Role not found'], 404);
                return;
            }

            // Check if role is assigned to any users
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as user_count 
                FROM users 
                WHERE role = ?
            ");
            $stmt->execute([$role['role_name']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['user_count'] > 0) {
                sendJsonResponse([
                    'error' => 'Cannot delete role that is assigned to users',
                    'user_count' => $result['user_count']
                ], 400);
                return;
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Delete role permissions
                $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$role_id]);

                // Delete role
                $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'role_deleted',
                    "Deleted role: {$role['role_name']}"
                );

                sendJsonResponse(['message' => 'Role deleted successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error deleting role: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete role'], 500);
        }
    }

    public function getPermissions() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM permissions 
                ORDER BY permission_name
            ");
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['permissions' => $permissions]);

        } catch (PDOException $e) {
            error_log("Error fetching permissions: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch permissions'], 500);
        }
    }

    public function createPermission() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $permission_name = $_POST['permission_name'] ?? null;
            $description = $_POST['description'] ?? null;
            $module = $_POST['module'] ?? null;

            if (!$permission_name || !$module) {
                sendJsonResponse(['error' => 'Permission name and module are required'], 400);
                return;
            }

            // Check if permission already exists
            $stmt = $this->db->prepare("
                SELECT id FROM permissions 
                WHERE permission_name = ? AND module = ?
            ");
            $stmt->execute([$permission_name, $module]);
            if ($stmt->fetch()) {
                sendJsonResponse(['error' => 'Permission already exists for this module'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO permissions (
                    permission_name, description, module, created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$permission_name, $description, $module]);

            $permission_id = $this->db->lastInsertId();

            logActivity(
                $_SESSION['user_id'],
                'permission_created',
                "Created permission: {$permission_name} for module: {$module}"
            );

            sendJsonResponse([
                'message' => 'Permission created successfully',
                'permission_id' => $permission_id
            ]);

        } catch (PDOException $e) {
            error_log("Error creating permission: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create permission'], 500);
        }
    }

    public function getRoleHierarchy() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    r.id,
                    r.role_name,
                    r.description,
                    r.parent_role_id,
                    p.role_name as parent_role_name
                FROM roles r
                LEFT JOIN roles p ON r.parent_role_id = p.id
                ORDER BY r.role_name
            ");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build hierarchy
            $hierarchy = [];
            foreach ($roles as $role) {
                if (!$role['parent_role_id']) {
                    $hierarchy[] = $this->buildRoleTree($role, $roles);
                }
            }

            sendJsonResponse(['role_hierarchy' => $hierarchy]);

        } catch (PDOException $e) {
            error_log("Error fetching role hierarchy: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch role hierarchy'], 500);
        }
    }

    private function buildRoleTree($role, $all_roles) {
        $children = [];
        foreach ($all_roles as $r) {
            if ($r['parent_role_id'] == $role['id']) {
                $children[] = $this->buildRoleTree($r, $all_roles);
            }
        }
        $role['children'] = $children;
        return $role;
    }

    public function getRolePermissions($role_id) {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    CASE WHEN rp.role_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
                FROM permissions p
                LEFT JOIN role_permissions rp ON p.id = rp.permission_id 
                    AND rp.role_id = ?
                ORDER BY p.module, p.permission_name
            ");
            $stmt->execute([$role_id]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['permissions' => $permissions]);

        } catch (PDOException $e) {
            error_log("Error fetching role permissions: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch role permissions'], 500);
        }
    }

    public function updateRolePermissions() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $role_id = $_POST['role_id'] ?? null;
            $permissions = $_POST['permissions'] ?? [];

            if (!$role_id) {
                sendJsonResponse(['error' => 'Role ID is required'], 400);
                return;
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Remove all existing permissions
                $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$role_id]);

                // Add new permissions
                if (!empty($permissions)) {
                    $stmt = $this->db->prepare("
                        INSERT INTO role_permissions (
                            role_id, permission_id
                        ) VALUES (?, ?)
                    ");
                    foreach ($permissions as $permission_id) {
                        $stmt->execute([$role_id, $permission_id]);
                    }
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'role_permissions_updated',
                    "Updated permissions for role ID: {$role_id}"
                );

                sendJsonResponse(['message' => 'Role permissions updated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error updating role permissions: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update role permissions'], 500);
        }
    }
} 