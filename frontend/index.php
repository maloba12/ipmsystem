<?php
session_start();
require_once '../backend/auth/middleware.php';

// Check if user is logged in
AuthMiddleware::requireLogin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPMS - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .service-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
        }

        .service-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #3b82f6;
        }

        .service-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }

        .service-description {
            color: #4b5563;
            line-height: 1.6;
        }

        .services-grid {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.php" class="text-xl font-bold text-blue-600">
                        IPMS
                    </a>
                </div>

                <!-- Navigation Menu -->
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="index.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-blue-500 text-sm font-medium text-gray-900">
                        Home
                    </a>
                    <a href="services.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                        Services
                    </a>
                    <a href="about.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                        About
                    </a>
                </div>

                <!-- User Profile -->
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div>
                            <button type="button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="sr-only">Open user menu</span>
                                <span class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                                    <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'User'); ?>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative bg-gradient-to-r from-blue-500 to-blue-600 text-white py-24 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center">
                <h1 class="text-4xl tracking-tight font-extrabold sm:text-5xl md:text-6xl">
                    <span class="block">Integrated Pest Management</span>
                    <span class="block text-blue-100">System</span>
                </h1>
                <p class="mt-3 max-w-md mx-auto text-base text-blue-100 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                    Comprehensive pest management solutions for a healthier environment
                </p>
            </div>
        </div>
    </div>

    <!-- Services Section -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-12">Our Services</h2>
            <div id="servicesGrid" class="grid services-grid">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </section>

    <!-- JavaScript to fetch and display services -->
    <script>
        async function loadServices() {
            try {
                const response = await fetch('../backend/api/insurance_types.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    const servicesGrid = document.getElementById('servicesGrid');
                    servicesGrid.innerHTML = data.data.map(type => `
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3 class="service-title">${type.type_name}</h3>
                            <p class="service-description">${type.type_description}</p>
                            ${type.services ? `
                                <div class="mt-4">
                                    <h4 class="font-semibold mb-2">Available Services:</h4>
                                    <ul class="list-disc list-inside text-gray-600">
                                        ${type.services.split(',').map(service => `<li>${service.trim()}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error loading services:', error);
            }
        }

        // Load services when page loads
        loadServices();
    </script>
</body>
</html>
