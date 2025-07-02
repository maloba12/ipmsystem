// Dashboard Functionality
function initDashboardCharts() {
    // Initialize policy distribution chart
    const policyCtx = document.getElementById('policy-chart');
    if (policyCtx) {
        new Chart(policyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Auto', 'Home', 'Life', 'Health'],
                datasets: [{
                    data: [30, 25, 20, 25],
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Initialize claims overview chart
    const claimsCtx = document.getElementById('claims-chart');
    if (claimsCtx) {
        new Chart(claimsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Claims',
                    data: [12, 19, 15, 17, 22, 25],
                    borderColor: '#3B82F6',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// File Management
async function loadFiles() {
    try {
        const response = await fetch('/api/files');
        if (response.ok) {
            const files = await response.json();
            updateFilesTable(files);
        }
    } catch (error) {
        console.error('Error loading files:', error);
    }
}

function updateFilesTable(files) {
    const tableBody = document.getElementById('files-table');
    if (!tableBody) return;

    tableBody.innerHTML = files.map(file => `
        <tr>
            <td class="px-6 py-4 whitespace-nowrap">${file.file_name}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${file.file_type === 'application/pdf' ? 'PDF' : 'Excel'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${(file.file_size / 1024 / 1024).toFixed(2)} MB
            </td>
            <td class="px-6 py-4 whitespace-nowrap">${file.user_name}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <button onclick="downloadFile(${file.id})" class="text-blue-600 hover:text-blue-900 mr-4">
                    <i class="fas fa-download"></i>
                </button>
                <button onclick="deleteFile(${file.id})" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

async function downloadFile(fileId) {
    try {
        const response = await fetch(`/api/files/${fileId}/download`);
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = response.headers.get('content-disposition').split('filename=')[1];
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    } catch (error) {
        console.error('Error downloading file:', error);
        alert('Failed to download file');
    }
}

async function deleteFile(fileId) {
    if (!confirm('Are you sure you want to delete this file?')) return;

    try {
        const response = await fetch(`/api/files/${fileId}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            loadFiles();
        } else {
            throw new Error('Failed to delete file');
        }
    } catch (error) {
        console.error('Error deleting file:', error);
        alert('Failed to delete file');
    }
}

// File Upload Modal
function showFileModal(action) {
    const modal = document.getElementById('fileModal');
    if (modal) {
        modal.style.display = 'block';
        document.getElementById('fileAction').value = action;
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

async function uploadFile() {
    const formData = new FormData();
    const fileInput = document.getElementById('fileInput');
    
    if (!fileInput.files[0]) {
        alert('Please select a file');
        return;
    }

    formData.append('file', fileInput.files[0]);

    try {
        const response = await fetch('/api/files', {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            closeModal('fileModal');
            loadFiles();
            alert('File uploaded successfully');
        } else {
            throw new Error('Failed to upload file');
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('Failed to upload file');
    }
}

// Load dashboard data
async function loadDashboardData() {
    try {
        const response = await fetch('/api/dashboard/stats');
        if (response.ok) {
            const data = await response.json();
            updateDashboardStats(data);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

// Update dashboard statistics
function updateDashboardStats(data) {
    // Update total policies
    const totalPolicies = document.getElementById('total-policies');
    if (totalPolicies) {
        totalPolicies.textContent = data.totalPolicies.toLocaleString();
    }

    // Update pending claims
    const pendingClaims = document.getElementById('pending-claims');
    if (pendingClaims) {
        pendingClaims.textContent = data.pendingClaims.toLocaleString();
    }

    // Update monthly premium
    const monthlyPremium = document.getElementById('monthly-premium');
    if (monthlyPremium) {
        monthlyPremium.textContent = `K ${data.monthlyPremium.toLocaleString()}`;
    }

    // Update active clients
    const activeClients = document.getElementById('active-clients');
    if (activeClients) {
        activeClients.textContent = data.activeClients.toLocaleString();
    }
}

// Load recent activities
async function loadRecentActivities() {
    try {
        const response = await fetch('/api/dashboard/activities');
        if (response.ok) {
            const data = await response.json();
            updateRecentActivities(data);
        }
    } catch (error) {
        console.error('Error loading recent activities:', error);
    }
}

// Update recent activities list
function updateRecentActivities(activities) {
    const activityList = document.getElementById('activity-list');
    if (activityList) {
        activityList.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas ${getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-details">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-time">${formatTime(activity.timestamp)}</div>
                </div>
            </div>
        `).join('');
    }
}

// Get activity icon based on type
function getActivityIcon(type) {
    const icons = {
        'policy': 'fa-file-contract',
        'claim': 'fa-clipboard-list',
        'client': 'fa-user',
        'payment': 'fa-money-bill-wave',
        'system': 'fa-cog'
    };
    return icons[type] || 'fa-info-circle';
}

// Format timestamp
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    // Less than 24 hours
    if (diff < 24 * 60 * 60 * 1000) {
        return date.toLocaleTimeString();
    }
    
    // Less than 7 days
    if (diff < 7 * 24 * 60 * 60 * 1000) {
        return date.toLocaleDateString(undefined, { weekday: 'long' });
    }
    
    // Otherwise show full date
    return date.toLocaleDateString();
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();
    loadRecentActivities();
    initDashboardCharts();
    
    // Initialize clients
    loadClients();
    
    // Initialize policies
    loadPolicies();
    
    // Initialize claims
    loadClaims();
    
    // Initialize modals
    initializeModals();
    
    // Initialize event listeners
    initializeEventListeners();
});

// Clients Management
async function loadClients() {
    try {
        const response = await fetch('/api/clients');
        if (response.ok) {
            const clients = await response.json();
            updateClientsTable(clients);
        }
    } catch (error) {
        console.error('Error loading clients:', error);
    }
}

function updateClientsTable(clients) {
    const tbody = document.getElementById('clients-table').getElementsByTagName('tbody')[0];
    tbody.innerHTML = '';

    clients.forEach(client => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${client.name}</td>
            <td>${client.email}</td>
            <td>${client.phone}</td>
            <td>${client.total_policies}</td>
            <td>${client.total_claims}</td>
            <td>
                <button onclick="editClient(${client.id})" class="btn btn-primary btn-sm">Edit</button>
                <button onclick="deleteClient(${client.id})" class="btn btn-danger btn-sm">Delete</button>
            </td>
        `;
    });
}

async function editClient(id) {
    try {
        const response = await fetch(`/api/clients/${id}`);
        if (response.ok) {
            const client = await response.json();
            populateClientForm(client);
            showModal('client-modal');
        }
    } catch (error) {
        console.error('Error loading client:', error);
    }
}

async function deleteClient(id) {
    if (!confirm('Are you sure you want to delete this client?')) return;

    try {
        const response = await fetch(`/api/clients/${id}`, {
            method: 'DELETE'
        });
        if (response.ok) {
            loadClients();
        }
    } catch (error) {
        console.error('Error deleting client:', error);
    }
}

// Policies Management
async function loadPolicies() {
    try {
        const response = await fetch('/api/policies');
        if (response.ok) {
            const policies = await response.json();
            updatePoliciesTable(policies);
        }
    } catch (error) {
        console.error('Error loading policies:', error);
    }
}

function updatePoliciesTable(policies) {
    const tbody = document.getElementById('policies-table').getElementsByTagName('tbody')[0];
    tbody.innerHTML = '';

    policies.forEach(policy => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${policy.policy_number}</td>
            <td>${policy.client_name}</td>
            <td>${policy.type}</td>
            <td>K ${policy.premium}</td>
            <td>${policy.status}</td>
            <td>${policy.total_claims}</td>
            <td>
                <button onclick="editPolicy(${policy.id})" class="btn btn-primary btn-sm">Edit</button>
                <button onclick="deletePolicy(${policy.id})" class="btn btn-danger btn-sm">Delete</button>
            </td>
        `;
    });
}

async function editPolicy(id) {
    try {
        const response = await fetch(`/api/policies/${id}`);
        if (response.ok) {
            const policy = await response.json();
            populatePolicyForm(policy);
            showModal('policy-modal');
        }
    } catch (error) {
        console.error('Error loading policy:', error);
    }
}

async function deletePolicy(id) {
    if (!confirm('Are you sure you want to delete this policy?')) return;

    try {
        const response = await fetch(`/api/policies/${id}`, {
            method: 'DELETE'
        });
        if (response.ok) {
            loadPolicies();
        }
    } catch (error) {
        console.error('Error deleting policy:', error);
    }
}

// Claims Management
async function loadClaims() {
    try {
        const response = await fetch('/api/claims');
        if (response.ok) {
            const claims = await response.json();
            updateClaimsTable(claims);
        }
    } catch (error) {
        console.error('Error loading claims:', error);
    }
}

function updateClaimsTable(claims) {
    const tbody = document.getElementById('claims-table').getElementsByTagName('tbody')[0];
    tbody.innerHTML = '';

    claims.forEach(claim => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${claim.id}</td>
            <td>${claim.policy_number}</td>
            <td>${claim.client_name}</td>
            <td>${claim.status}</td>
            <td>K ${claim.amount}</td>
            <td>${new Date(claim.created_at).toLocaleDateString()}</td>
            <td>
                <button onclick="editClaim(${claim.id})" class="btn btn-primary btn-sm">Edit</button>
                <button onclick="deleteClaim(${claim.id})" class="btn btn-danger btn-sm">Delete</button>
            </td>
        `;
    });
}

async function editClaim(id) {
    try {
        const response = await fetch(`/api/claims/${id}`);
        if (response.ok) {
            const claim = await response.json();
            populateClaimForm(claim);
            showModal('claim-modal');
        }
    } catch (error) {
        console.error('Error loading claim:', error);
    }
}

async function deleteClaim(id) {
    if (!confirm('Are you sure you want to delete this claim?')) return;

    try {
        const response = await fetch(`/api/claims/${id}`, {
            method: 'DELETE'
        });
        if (response.ok) {
            loadClaims();
        }
    } catch (error) {
        console.error('Error deleting claim:', error);
    }
}

// Reports
async function generateReport() {
    const type = document.getElementById('report-type').value;
    const startDate = document.getElementById('report-start-date').value;
    const endDate = document.getElementById('report-end-date').value;

    try {
        const response = await fetch('/api/reports', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type,
                start_date: startDate,
                end_date: endDate
            })
        });

        if (response.ok) {
            const report = await response.json();
            displayReport(report);
        }
    } catch (error) {
        console.error('Error generating report:', error);
    }
}

function displayReport(report) {
    const preview = document.getElementById('report-preview');
    preview.innerHTML = '<h3>Report Preview</h3>' + report.html;
}

// Form Handling
async function submitClientForm(event) {
    event.preventDefault();
    const id = document.getElementById('client-id').value;
    const data = {
        name: document.getElementById('client-name').value,
        email: document.getElementById('client-email').value,
        phone: document.getElementById('client-phone').value,
        address: document.getElementById('client-address').value
    };

    try {
        const response = await fetch(`/api/clients/${id ? id : ''}`, {
            method: id ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            closeModal('client-modal');
            loadClients();
        }
    } catch (error) {
        console.error('Error saving client:', error);
    }
}

async function submitPolicyForm(event) {
    event.preventDefault();
    const id = document.getElementById('policy-id').value;
    const data = {
        client_id: document.getElementById('policy-client').value,
        type: document.getElementById('policy-type').value,
        premium: document.getElementById('policy-premium').value,
        start_date: document.getElementById('policy-start-date').value,
        end_date: document.getElementById('policy-end-date').value
    };

    try {
        const response = await fetch(`/api/policies/${id ? id : ''}`, {
            method: id ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            closeModal('policy-modal');
            loadPolicies();
        }
    } catch (error) {
        console.error('Error saving policy:', error);
    }
}

async function submitClaimForm(event) {
    event.preventDefault();
    const id = document.getElementById('claim-id').value;
    const data = {
        policy_id: document.getElementById('claim-policy').value,
        amount: document.getElementById('claim-amount').value,
        description: document.getElementById('claim-description').value,
        status: document.getElementById('claim-status').value
    };

    try {
        const response = await fetch(`/api/claims/${id ? id : ''}`, {
            method: id ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            closeModal('claim-modal');
            loadClaims();
        }
    } catch (error) {
        console.error('Error saving claim:', error);
    }
}

// Modal Functions
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function populateClientForm(client) {
    document.getElementById('client-id').value = client.id;
    document.getElementById('client-name').value = client.name;
    document.getElementById('client-email').value = client.email;
    document.getElementById('client-phone').value = client.phone;
    document.getElementById('client-address').value = client.address;
}

function populatePolicyForm(policy) {
    document.getElementById('policy-id').value = policy.id;
    document.getElementById('policy-client').value = policy.client_id;
    document.getElementById('policy-type').value = policy.type;
    document.getElementById('policy-premium').value = policy.premium;
    document.getElementById('policy-start-date').value = policy.start_date;
    document.getElementById('policy-end-date').value = policy.end_date;
}

function populateClaimForm(claim) {
    document.getElementById('claim-id').value = claim.id;
    document.getElementById('claim-policy').value = claim.policy_id;
    document.getElementById('claim-amount').value = claim.amount;
    document.getElementById('claim-description').value = claim.description;
    document.getElementById('claim-status').value = claim.status;
}

// Helper Functions
function initializeModals() {
    // Add close modal functionality
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
}

function initializeEventListeners() {
    // Form submissions
    document.getElementById('client-form').addEventListener('submit', submitClientForm);
    document.getElementById('policy-form').addEventListener('submit', submitPolicyForm);
    document.getElementById('claim-form').addEventListener('submit', submitClaimForm);

    // Generate report
    document.getElementById('generate-report').addEventListener('click', generateReport);

    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            showScreen(tab.getAttribute('data-screen'));
        });
    });
}

// Tab Switching Functionality
function showScreen(screenId) {
    // Hide all screens
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });

    // Show selected screen
    const screen = document.getElementById(screenId);
    if (screen) {
        screen.classList.add('active');
    }

    // Update tab states
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('data-screen') === screenId) {
            tab.classList.add('active');
        }
    });
}
