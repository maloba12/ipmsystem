<?php
// Navigation menu items based on user role
$navigation = [
    'Admin' => [
        'Dashboard' => '/ipmsystem/frontend/admin_dashboard.php',
        'Users' => '/ipmsystem/frontend/users.php',
        'Policies' => '/ipmsystem/frontend/policies.php',
        'Analytics' => '/ipmsystem/frontend/analytics.php',
        'Security' => '/ipmsystem/frontend/security.php',
        'Settings' => '/ipmsystem/frontend/settings.php'
    ],
    'Insurance Agent' => [
        'Dashboard' => '/ipmsystem/frontend/agent_dashboard.php',
        'Policies' => '/ipmsystem/frontend/policies.php',
        'Analytics' => '/ipmsystem/frontend/analytics.php',
        'Settings' => '/ipmsystem/frontend/settings.php'
    ],
    'Client' => [
        'Dashboard' => '/ipmsystem/frontend/client_dashboard.php',
        'Policies' => '/ipmsystem/frontend/policies.php',
        'Settings' => '/ipmsystem/frontend/settings.php'
    ]
];

// Get current page URL
$current_page = $_SERVER['PHP_SELF'];
?>

<!-- Navigation Bar -->
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <a href="/ipmsystem/frontend/admin_dashboard.php" class="text-xl font-bold text-blue-600">
                    IPMS
                </a>
            </div>

            <!-- Navigation Menu -->
            <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                <?php
                // Get navigation items for current user role
                $user_role = $_SESSION['user_role'] ?? 'Client';
                $nav_items = $navigation[$user_role] ?? [];

                foreach ($nav_items as $label => $url) {
                    $active = strpos($current_page, basename($url)) !== false;
                    $classes = $active ? 
                        'border-blue-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium' : 
                        'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium';
                    ?>
                    <a href="<?php echo $url; ?>" class="<?php echo $classes; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php } ?>
            </div>

            <!-- User Profile -->
            <div class="flex items-center">
                <div class="ml-3 relative">
                    <div>
                        <button type="button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Open user menu</span>
                            <span class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                                <?php echo $_SESSION['user_name'] ?? 'User'; ?>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
