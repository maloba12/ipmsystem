// Main Application Logic
class App {
    constructor() {
        this.currentPage = 'dashboard';
        this.init();
    }

    init() {
        // Initialize event listeners
        this.initEventListeners();
        
        // Initialize navigation
        this.initNavigation();
    }

    initEventListeners() {
        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }

        // Navigation links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => this.handleNavigation(e));
        });

        // User menu links
        document.querySelectorAll('.dropdown-menu a').forEach(link => {
            link.addEventListener('click', (e) => this.handleNavigation(e));
        });
    }

    initNavigation() {
        // Set active nav link based on current page
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.dataset.page === this.currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('active');
    }

    async handleNavigation(e) {
        e.preventDefault();
        
        const link = e.currentTarget;
        const page = link.dataset.page;
        
        if (page && page !== this.currentPage) {
            await this.loadPage(page);
        }
    }

    async loadPage(page) {
        try {
            // Update current page
            this.currentPage = page;
            
            // Update navigation
            this.initNavigation();
            
            // Update page title
            document.getElementById('page-title').textContent = this.capitalizeFirstLetter(page);
            
            // Load page content
            const response = await fetch(`/api/pages/${page}`);
            if (response.ok) {
                const data = await response.json();
                this.renderPage(data);
            } else {
                console.error('Failed to load page:', page);
            }
        } catch (error) {
            console.error('Error loading page:', error);
        }
    }

    renderPage(data) {
        const contentArea = document.querySelector('.content-area');
        
        // Hide all screens
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
        });
        
        // Create or update page screen
        let screen = document.getElementById(`${this.currentPage}-screen`);
        if (!screen) {
            screen = document.createElement('div');
            screen.id = `${this.currentPage}-screen`;
            screen.className = 'screen';
            contentArea.appendChild(screen);
        }
        
        // Render page content
        screen.innerHTML = data.content;
        screen.classList.add('active');
        
        // Initialize page-specific functionality
        this.initPageFunctionality();
    }

    initPageFunctionality() {
        // Initialize page-specific functionality based on current page
        switch (this.currentPage) {
            case 'dashboard':
                this.initDashboard();
                break;
            case 'clients':
                this.initClients();
                break;
            case 'policies':
                this.initPolicies();
                break;
            case 'claims':
                this.initClaims();
                break;
            case 'reports':
                this.initReports();
                break;
            case 'agents':
                this.initAgents();
                break;
            case 'settings':
                this.initSettings();
                break;
        }
    }

    initDashboard() {
        // Initialize dashboard charts and stats
        if (typeof initDashboardCharts === 'function') {
            initDashboardCharts();
        }
    }

    initClients() {
        // Initialize client management functionality
        this.initClientForm();
        this.loadClients();
    }

    initPolicies() {
        // Initialize policy management functionality
        this.initPolicyForm();
        this.loadPolicies();
    }

    initClaims() {
        // Initialize claims management functionality
        this.initClaimForm();
        this.loadClaims();
    }

    initReports() {
        // Initialize reports functionality
        this.initReportFilters();
        this.loadReports();
    }

    initAgents() {
        // Initialize agent management functionality
        this.initAgentForm();
        this.loadAgents();
    }

    initSettings() {
        // Initialize settings functionality
        this.loadSettings();
    }

    capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
}

// Initialize application
const app = new App(); 