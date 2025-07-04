// Authentication and User Management
class Auth {
    constructor() {
        this.user = null;
        this.token = localStorage.getItem('token');
        this.init();
    }

    init() {
        // Check if user is already logged in
        if (this.token) {
            this.validateToken();
        }

        // Initialize event listeners
        this.initEventListeners();

        // Initialize UI state
        this.initializeUI();
    }

    initializeUI() {
        // Initialize page state
        const loginScreen = document.getElementById('login-screen');
        const dashboardScreen = document.getElementById('dashboard-screen');
        const publicHeader = document.getElementById('public-header');
        const dashboardHeader = document.getElementById('dashboard-header');
        const sidebar = document.querySelector('.sidebar');

        // Set initial visibility
        if (this.user) {
            loginScreen.classList.remove('active');
            dashboardScreen.classList.add('active');
            publicHeader.style.display = 'none';
            dashboardHeader.style.display = 'block';
            sidebar.style.display = 'block';
        } else {
            loginScreen.classList.add('active');
            dashboardScreen.classList.remove('active');
            publicHeader.style.display = 'block';
            dashboardHeader.style.display = 'none';
            sidebar.style.display = 'none';
        }
    }

    initEventListeners() {
        // Login form submission
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Logout button
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => this.handleLogout(e));
        }
    }

    async validateToken() {
        try {
            const response = await fetch('/api/auth/validate', {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.setUser(data.user);
                this.initializeUI();
                this.updateUI();
            } else {
                this.logout();
            }
        } catch (error) {
            this.logout();
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const username = formData.get('username');
        const password = formData.get('password');
        const role = formData.get('role');

        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username, password, role })
            });

            if (response.ok) {
                const data = await response.json();
                this.token = data.token;
                localStorage.setItem('token', this.token);
                this.validateToken();
            } else {
                const error = await response.json();
                this.showError(error.message || 'Invalid credentials');
            }
        } catch (error) {
            this.showError('An error occurred during login');
        }
    }

    handleLogout(e) {
        e.preventDefault();
        localStorage.removeItem('token');
        this.token = null;
        this.user = null;
        window.location.href = '/frontend/login.html';
    }

    logout() {
        this.user = null;
        this.token = null;
        localStorage.removeItem('token');
        this.showLoginScreen();
    }

    setUser(user) {
        this.user = user;
        document.getElementById('user-role').textContent = user.role;
    }

    updateUI() {
        // Update user info in header
        const userName = document.getElementById('user-name');
        const userRole = document.getElementById('user-role');
        const userAvatar = document.getElementById('user-avatar');

        if (this.user) {
            if (userName) userName.textContent = this.user.name;
            if (userRole) userRole.textContent = this.user.role;
            if (userAvatar) userAvatar.textContent = this.user.name.charAt(0).toUpperCase();

            // Show/hide admin-only elements
            const adminElements = document.querySelectorAll('.admin-only');
            adminElements.forEach(el => {
                el.style.display = this.user.role === 'admin' ? 'block' : 'none';
            });
        }
    }

    showLoginScreen() {
        document.getElementById('login-screen').classList.add('active');
        document.getElementById('dashboard-screen').classList.remove('active');
        document.querySelector('.sidebar').style.display = 'none';
        document.querySelector('.top-header').style.display = 'none';
        // Hide all other navigation elements
        document.querySelectorAll('.screen').forEach(screen => {
            if (screen.id !== 'login-screen') {
                screen.classList.remove('active');
            }
        });
    }

    updateUI() {
        // Show/hide elements based on user role
        const isAdmin = this.user?.role === 'admin';
        
        // Show all elements for admin
        if (isAdmin) {
            document.querySelectorAll('.admin-only').forEach(el => {
                el.style.display = 'block';
            });
            
            // Show admin-specific navigation items
            document.querySelectorAll('.sidebar-nav li').forEach(li => {
                li.style.display = 'block';
            });
        } else {
            // For non-admin users, hide admin-only elements
            document.querySelectorAll('.admin-only').forEach(el => {
                el.style.display = 'none';
            });
            
            // For non-admin users, hide admin-specific navigation items
            document.querySelectorAll('.sidebar-nav li').forEach(li => {
                li.style.display = li.classList.contains('admin-only') ? 'none' : 'block';
            });
        }

        // Update the main content area based on user role
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            if (isAdmin) {
                mainContent.innerHTML = `<!-- Admin Dashboard Content -->
                    <div class="dashboard-content">
                        <h2>Welcome, ${this.user?.name}</h2>
                        <p>Role: ${this.user?.role}</p>
                        <div class="admin-dashboard-stats">
                            <div class="stat-card">
                                <h3>Total Users</h3>
                                <p id="total-users">Loading...</p>
                            </div>
                            <div class="stat-card">
                                <h3>Total Policies</h3>
                                <p id="total-policies">Loading...</p>
                            </div>
                            <div class="stat-card">
                                <h3>Total Claims</h3>
                                <p id="total-claims">Loading...</p>
                            </div>
                        </div>
                    </div>`;
            } else {
                mainContent.innerHTML = `<!-- Regular User Dashboard Content -->
                    <div class="dashboard-content">
                        <h2>Welcome, ${this.user?.name}</h2>
                        <p>Role: ${this.user?.role}</p>
                        <div class="user-dashboard-stats">
                            <div class="stat-card">
                                <h3>Your Policies</h3>
                                <p id="user-policies">Loading...</p>
                            </div>
                            <div class="stat-card">
                                <h3>Your Claims</h3>
                                <p id="user-claims">Loading...</p>
                            </div>
                        </div>
                    </div>`;
            }
        }

        // Update header elements
        const pageHeader = document.getElementById('page-title');
        if (pageHeader) {
            pageHeader.textContent = 'Dashboard';
        }
    }

    showError(message) {
        const errorElement = document.getElementById('email-error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }
}

// Initialize authentication
const auth = new Auth(); 