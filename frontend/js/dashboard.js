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
});
