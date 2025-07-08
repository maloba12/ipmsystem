<?php
session_start();
require_once '../backend/controllers/SettingsController.php';
require_once __DIR__ . '/includes/navigation.php';

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: settings.php?error=invalid_token');
        exit();
    }
}

// Initialize SettingsController
$settingsController = new SettingsController();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $success = false;
    $message = '';
    
    switch ($action) {
        case 'update_profile':
            try {
                $result = $settingsController->updateUserProfile($_POST);
                $success = true;
                $message = 'Profile updated successfully';
            } catch (Exception $e) {
                $message = 'Failed to update profile: ' . $e->getMessage();
            }
            break;
            
        case 'update_notifications':
            try {
                $result = $settingsController->updateNotificationPreferences($_POST);
                $success = true;
                $message = 'Notification preferences updated successfully';
            } catch (Exception $e) {
                $message = 'Failed to update notification preferences: ' . $e->getMessage();
            }
            break;
            
        case 'update_security':
            try {
                $result = $settingsController->updateSecuritySettings($_POST);
                $success = true;
                $message = 'Security settings updated successfully';
            } catch (Exception $e) {
                $message = 'Failed to update security settings: ' . $e->getMessage();
            }
            break;
            
        default:
            $message = 'Invalid action';
            break;
    }

    if ($success) {
        header('Location: settings.php?success=' . urlencode($message));
    } else {
        header('Location: settings.php?error=' . urlencode($message));
    }
    exit();
}

// Get user-specific settings
$userRole = $_SESSION['user']['role'];
$settings = [];

// Get settings based on user role
switch ($userRole) {
    case 'Admin':
        $settings['system'] = $settingsController->getSystemSettings();
        $settings['notifications'] = $settingsController->getNotificationSettings();
        $settings['email_templates'] = $settingsController->getEmailTemplates();
        $settings['preferences'] = $settingsController->getSystemPreferences();
        $settings['integrations'] = $settingsController->getIntegrationSettings();
        break;
    case 'Insurance Agent':
        $settings['notifications'] = $settingsController->getNotificationSettings();
        break;
    case 'Client':
        $settings['notifications'] = $settingsController->getNotificationSettings();
        break;
    default:
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/your-code.js"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <div class="container mx-auto p-6">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ZamSure Insurance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-section {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .settings-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .settings-description {
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .settings-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 500;
        }

        .settings-button {
            background: #1e40af;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .settings-button:hover {
            background: #1d3f8d;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Settings</h1>
            <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if ($userRole === 'Admin'): ?>
        <div class="settings-section">
            <div class="settings-header">
                <h2 class="settings-title">System Settings</h2>
            </div>
            <div class="settings-description">
                Configure system-wide settings and preferences
            </div>
            <form class="settings-form" onsubmit="return updateSettings('system', event)">
                <!-- System settings form fields will be populated here -->
                <button type="submit" class="settings-button">Save Changes</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="settings-section">
            <div class="settings-header">
                <h2 class="settings-title">Notification Preferences</h2>
            </div>
            <div class="settings-description">
                Choose how you receive notifications
            </div>
            <form class="settings-form" onsubmit="return updateSettings('notifications', event)">
                <?php foreach ($settings['notifications'] as $notification): ?>
                <div class="form-group">
                    <label><?php echo htmlspecialchars($notification['notification_type']); ?></label>
                    <div class="flex gap-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="email_<?php echo $notification['id']; ?>" name="email_enabled" <?php echo $notification['email_enabled'] ? 'checked' : ''; ?>>
                            <label for="email_<?php echo $notification['id']; ?>">Email</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="sms_<?php echo $notification['id']; ?>" name="sms_enabled" <?php echo $notification['sms_enabled'] ? 'checked' : ''; ?>>
                            <label for="sms_<?php echo $notification['id']; ?>">SMS</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="push_<?php echo $notification['id']; ?>" name="push_enabled" <?php echo $notification['push_enabled'] ? 'checked' : ''; ?>>
                            <label for="push_<?php echo $notification['id']; ?>">Push Notifications</label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="settings-button">Save Changes</button>
            </form>
        </div>

        <?php if ($userRole === 'Admin'): ?>
        <div class="settings-section">
            <div class="settings-header">
                <h2 class="settings-title">Email Templates</h2>
            </div>
            <div class="settings-description">
                Manage email templates for system notifications
            </div>
            <div class="space-y-4">
                <?php foreach ($settings['email_templates'] as $template): ?>
                <div class="p-4 border rounded-lg">
                    <h3 class="font-medium mb-2"><?php echo htmlspecialchars($template['template_name']); ?></h3>
                    <button class="text-blue-600 hover:text-blue-800" onclick="editTemplate(<?php echo $template['id']; ?>)">Edit Template</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($userRole === 'Admin'): ?>
        <div class="settings-section">
            <div class="settings-header">
                <h2 class="settings-title">Profile Settings</h2>
            </div>
            <div class="settings-description">
                Update your personal information and preferences
            </div>
            <form class="settings-form" onsubmit="return updateSettings('profile', event)">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($_SESSION['user']['last_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['user']['email']); ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($_SESSION['user']['phone']); ?>">
                </div>
                <button type="submit" class="settings-button">Save Profile</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        async function updateSettings(type, event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', type);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>');

            try {
                const response = await fetch('settings.php', {
                    method: 'POST',
                    body: formData
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo $_SESSION['csrf_token']; ?>'
                    },
                    body: new URLSearchParams({
                        action: 'update_' + type,
                        ...Object.fromEntries(formData)
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Settings updated successfully');
                } else {
                    alert('Failed to update settings: ' + result.error);
                }
            } catch (error) {
                console.error('Error updating settings:', error);
                alert('An error occurred while updating settings');
            }
            return false;
        }

        function editTemplate(templateId) {
            // Implement template editing modal
            alert('Edit template functionality will be implemented');
        }
    </script>
<?php
// Close any open PHP blocks
if (isset($settingsController)) {
    $settingsController = null;
}
?>
</body>
</html>
