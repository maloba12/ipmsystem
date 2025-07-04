<?php
session_start();
require_once '../backend/auth/middleware.php';

// Check if user is logged in and has appropriate role
AuthMiddleware::requireLogin();
AuthMiddleware::requireRole('Admin');

// Get action from query parameters
$action = $_GET['action'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Types - IPMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .type-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .type-card:hover {
            transform: translateY(-5px);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .action-button {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .edit-button {
            color: #3b82f6;
        }

        .delete-button {
            color: #ef4444;
        }

        .edit-button:hover {
            background-color: #dbeafe;
        }

        .delete-button:hover {
            background-color: #fee2e2;
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
                    <a href="admin_dashboard.php" class="text-xl font-bold text-blue-600">
                        IPMS
                    </a>
                </div>

                <!-- Navigation Menu -->
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="admin_dashboard.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-blue-500 text-sm font-medium text-gray-900">
                        Dashboard
                    </a>
                    <a href="users.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                        Users
                    </a>
                    <a href="insurance_types.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                        Insurance Types
                    </a>
                </div>

                <!-- User Profile -->
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div>
                            <button type="button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="sr-only">Open user menu</span>
                                <span class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                                    <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Admin User'); ?>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Insurance Types</h1>
            <div class="flex gap-2">
                <button onclick="exportToExcel()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button onclick="exportToPDF()" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button onclick="openAddTypeModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Add New Type
                </button>
            </div>
        </div>

        <!-- Insurance Types List -->
        <div id="insuranceTypesList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Will be populated by JavaScript -->
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="typeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-96">
            <h3 class="text-lg font-bold mb-4">Add Insurance Type</h3>
            <form id="typeForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Services</label>
                    <div id="servicesContainer" class="space-y-2 mt-2">
                        <!-- Services will be dynamically added here -->
                    </div>
                    <button type="button" onclick="addServiceField()" class="text-blue-600 hover:text-blue-800 mt-2">
                        Add Service
                    </button>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentTypeId = null;

        async function loadInsuranceTypes() {
            try {
                const response = await fetch('../backend/api/insurance_types.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    const container = document.getElementById('insuranceTypesList');
                    container.innerHTML = data.data.map(type => `
                        <div class="type-card">
                            <h2 class="text-xl font-semibold mb-2">${type.type_name}</h2>
                            <p class="text-gray-600 mb-4">${type.type_description}</p>
                            <div class="mb-4">
                                <h3 class="font-semibold mb-2">Services</h3>
                                <ul class="list-disc list-inside">
                                    ${type.services ? type.services.split(',').map(service => `<li>${service.trim()}</li>`).join('') : '<li>No services added</li>'}
                                </ul>
                            </div>
                            <div class="action-buttons">
                                <button onclick="editType(${type.type_id}, '${type.type_name}', '${type.type_description}', '${type.services || ''}')" class="edit-button">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteType(${type.type_id})" class="delete-button">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error loading insurance types:', error);
            }
        }

        function openAddTypeModal() {
            currentTypeId = null;
            document.getElementById('typeModal').classList.remove('hidden');
            document.getElementById('typeForm').reset();
            document.getElementById('servicesContainer').innerHTML = '';
        }

        function closeModal() {
            currentTypeId = null;
            document.getElementById('typeModal').classList.add('hidden');
        }

        function addServiceField() {
            const container = document.getElementById('servicesContainer');
            const serviceCount = container.children.length;
            const serviceField = document.createElement('div');
            serviceField.innerHTML = `
                <div class="flex gap-2">
                    <input type="text" name="services[]" placeholder="Service ${serviceCount + 1}" class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="button" onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(serviceField);
        }

        async function editType(id, name, description, services) {
            currentTypeId = id;
            openAddTypeModal();
            document.getElementById('typeForm').elements['name'].value = name;
            document.getElementById('typeForm').elements['description'].value = description;
            
            const servicesContainer = document.getElementById('servicesContainer');
            servicesContainer.innerHTML = '';
            
            if (services) {
                services.split(',').forEach(service => {
                    if (service.trim()) {
                        addServiceField();
                        servicesContainer.lastElementChild.querySelector('input').value = service.trim();
                    }
                });
            }
        }

        async function deleteType(id) {
            if (confirm('Are you sure you want to delete this insurance type?')) {
                try {
                    const response = await fetch('../backend/api/insurance_types.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        loadInsuranceTypes();
                    }
                } catch (error) {
                    console.error('Error deleting type:', error);
                }
            }
        }

        // Add export functions
        function exportToExcel() {
            window.location.href = '../backend/api/export.php?type=excel&report=insurance_types';
        }

        function exportToPDF() {
            window.location.href = '../backend/api/export.php?type=pdf&report=insurance_types';
        }

        document.getElementById('typeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const action = currentTypeId ? 'update' : 'add';
            
            try {
                let response;
                if (action === 'update') {
                    response = await fetch('../backend/api/insurance_types.php?action=update', {
                        method: 'POST',
                        body: new URLSearchParams({
                            id: currentTypeId,
                            name: formData.get('name'),
                            description: formData.get('description'),
                            services: Array.from(formData.getAll('services[]')).filter(service => service.trim()).join(',')
                        })
                    });
                } else {
                    response = await fetch('../backend/api/insurance_types.php?action=add', {
                        method: 'POST',
                        body: new URLSearchParams({
                            name: formData.get('name'),
                            description: formData.get('description'),
                            services: Array.from(formData.getAll('services[]')).filter(service => service.trim()).join(',')
                        })
                    });
                }
                
                const data = await response.json();
                
                if (data.success) {
                    closeModal();
                    loadInsuranceTypes();
                }
            } catch (error) {
                console.error('Error saving type:', error);
            }
        });

        // Load types when page loads
        loadInsuranceTypes();
    </script>
</body>
</html>
