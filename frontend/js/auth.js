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
        } else {
            this.showLoginScreen();
        }

        // Initialize event listeners
        this.initEventListeners();
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
                this.showDashboard();
            } else {
                this.logout();
            }
        } catch (error) {
            console.error('Token validation error:', error);
            this.logout();
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (response.ok) {
                this.token = data.token;
                localStorage.setItem('token', this.token);
                this.setUser(data.user);
                this.showDashboard();
            } else {
                this.showError(data.message || 'Login failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showError('An error occurred during login');
        }
    }

    handleLogout(e) {
        e.preventDefault();
        this.logout();
    }

    logout() {
        this.user = null;
        this.token = null;
        localStorage.removeItem('token');
        this.showLoginScreen();
    }

    setUser(user) {
        this.user = user;
        this.updateUI();
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
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
        });
        document.getElementById('login-screen').classList.add('active');
    }

    showDashboard() {
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
        });
        document.getElementById('dashboard-screen').classList.add('active');
        document.getElementById('page-title').textContent = 'Dashboard';
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