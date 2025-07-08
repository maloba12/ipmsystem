<?php
namespace IPMS\Controllers;

class InsuranceController extends BaseController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getInsuranceTypes() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    it.id,
                    it.name,
                    it.description,
                    it.status,
                    it.created_at,
                    COUNT(DISTINCT is.id) as service_count,
                    COUNT(DISTINCT p.id) as policy_count
                FROM insurance_types it
                LEFT JOIN insurance_services is ON it.id = is.insurance_type_id
                LEFT JOIN policies p ON p.insurance_type_id = it.id
                GROUP BY it.id
                ORDER BY it.name
            ");
            
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format timestamps and add status labels
            foreach ($types as &$type) {
                $type['created_at'] = date('Y-m-d H:i:s', strtotime($type['created_at']));
                $type['status_label'] = $this->getStatusLabel($type['status']);
            }
            
            return [
                'success' => true,
                'insurance_types' => $types
            ];
            
        } catch (PDOException $e) {
            error_log("Error fetching insurance types: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to fetch insurance types'
            ];
        }
    }

    public function createInsuranceType($data) {
        try {
            // Validate data
            $this->validateInsuranceTypeData($data);
            
            // Sanitize inputs
            $sanitizedData = $this->sanitizeInput($data);

            // Check if name already exists
            $stmt = $this->db->prepare("SELECT id FROM insurance_types WHERE name = ?");
            $stmt->execute([$sanitizedData['name']]);
            if ($stmt->fetch()) {
                throw new Exception("Insurance type with this name already exists");
            }

            // Begin transaction
            $this->db->beginTransaction();
            
            try {
                // Insert new insurance type
                $stmt = $this->db->prepare("
                    INSERT INTO insurance_types 
                    (name, description, status, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $sanitizedData['name'],
                    $sanitizedData['description'],
                    strtolower($sanitizedData['status'] ?? 'active')
                ]);

                $typeId = $this->db->lastInsertId();

                // Log the creation
                $this->logAction('create', $typeId, $sanitizedData['name']);

                // Commit transaction
                $this->db->commit();

                return $this->handleSuccess([
                    'message' => 'Insurance type created successfully',
                    'type_id' => $typeId
                ]);

            } catch (Exception $e) {
                // Rollback transaction on error
                $this->db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error creating insurance type: " . $e->getMessage());
            return $this->handleError([
                'message' => $e->getMessage(),
                'code' => 500,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    private function validateInsuranceTypeData($data) {
        $errors = [];
        
        // Validate required fields
        if (empty(trim($data['name']))) {
            $errors[] = "Name is required";
        }
        
        if (empty(trim($data['description']))) {
            $errors[] = "Description is required";
        }
        
        // Validate name
        $name = trim($data['name']);
        if (!preg_match('/^[a-zA-Z0-9\s\-\_\.]+$/', $name)) {
            $errors[] = "Name can only contain letters, numbers, spaces, hyphens, underscores, and periods";
        }
        
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = "Name must be between 2 and 100 characters";
        }
        
        // Validate description
        $description = trim($data['description']);
        if (strlen($description) < 10 || strlen($description) > 500) {
            $errors[] = "Description must be between 10 and 500 characters";
        }
        
        // Validate status
        if (!empty($data['status'])) {
            if (!in_array(strtolower($data['status']), ['active', 'inactive', 'archived'])) {
                $errors[] = "Status must be one of: active, inactive, or archived";
            }
        }
        
        // Check for related data constraints
        if (!empty($data['id'])) {
            // Check if type has active policies before deletion
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as policy_count 
                FROM policies 
                WHERE insurance_type_id = ? AND status = 'active'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['policy_count'] > 0) {
                $errors[] = "Cannot modify insurance type with active policies";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(". ", $errors));
        }
    }

    private function logAction($action, $typeId, $typeName) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_log 
                (user_id, action, entity_type, entity_id, details)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user']['id'],
                $action,
                'insurance_type',
                $typeId,
                "Insurance type '$typeName' was $action"
            ]);
        } catch (Exception $e) {
            error_log("Error logging action: " . $e->getMessage());
        }
    }
    }

    public function updateInsuranceType($id, $data) {
        try {
            // Validate data
            $this->validateInsuranceTypeData($data);
            
            // Sanitize inputs
            $sanitizedData = $this->sanitizeInput($data);

            // Begin transaction
            $this->db->beginTransaction();
            
            try {
                // Check if insurance type exists
                $stmt = $this->db->prepare("SELECT id, name FROM insurance_types WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing) {
                    throw new Exception("Insurance type not found");
                }

                // Check if name exists for other types
                if ($existing['name'] !== $sanitizedData['name']) {
                    if ($this->checkInsuranceTypeNameExists($sanitizedData['name'])) {
                        throw new Exception("Insurance type with this name already exists");
                    }
                }

                // Update insurance type
                $stmt = $this->db->prepare("
                    UPDATE insurance_types 
                    SET name = ?, description = ?, status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $sanitizedData['name'],
                    $sanitizedData['description'],
                    strtolower($sanitizedData['status'] ?? 'active'),
                    $id
                ]);

                // Log the update
                $this->logAction('update', $id, $sanitizedData['name']);

                // Commit transaction
                $this->db->commit();

                return $this->handleSuccess([
                    'message' => 'Insurance type updated successfully',
                    'type_id' => $id
                ]);

            } catch (Exception $e) {
                // Rollback transaction on error
                $this->db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error updating insurance type: " . $e->getMessage());
            return $this->handleError([
                'message' => $e->getMessage(),
                'code' => 500,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }

} catch (Exception $e) {
    error_log("Error updating insurance type: " . $e->getMessage());
    return $this->handleError([
        'message' => $e->getMessage(),
        'code' => 500,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
                return '<span class="status-badge status-success">Active</span>';
            case 'inactive':
                return '<span class="status-badge status-warning">Inactive</span>';
            case 'archived':
                return '<span class="status-badge status-error">Archived</span>';
            default:
                return '<span class="status-badge">Unknown</span>';
        }
    }
}
