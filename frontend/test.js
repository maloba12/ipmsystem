// Frontend Test Script
document.addEventListener('DOMContentLoaded', function() {
    // Test login functionality
    function testLogin() {
        const loginForm = document.querySelector('#loginForm');
        if (loginForm) {
            console.log('Testing login form...');
            const testCredentials = {
                email: 'test@example.com',
                password: 'test123'
            };
            
            // Simulate form submission
            const formData = new FormData();
            formData.append('email', testCredentials.email);
            formData.append('password', testCredentials.password);
            
            fetch('/api/auth/login', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Login test result:', data);
            })
            .catch(error => {
                console.error('Login test error:', error);
            });
        }
    }

    // Test report generation
    function testReportGeneration() {
        const reportForm = document.querySelector('#reportForm');
        if (reportForm) {
            console.log('Testing report generation...');
            const testParams = {
                report_type: 'financial_summary',
                start_date: '2024-01-01',
                end_date: '2024-03-31',
                format: 'pdf'
            };
            
            fetch('/api/reports/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(testParams)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Report generation test result:', data);
            })
            .catch(error => {
                console.error('Report generation test error:', error);
            });
        }
    }

    // Test policy creation
    function testPolicyCreation() {
        const policyForm = document.querySelector('#policyForm');
        if (policyForm) {
            console.log('Testing policy creation...');
            const testPolicy = {
                client_id: 1,
                product_type: 'auto',
                coverage_amount: 50000,
                start_date: '2024-04-01',
                end_date: '2025-04-01'
            };
            
            fetch('/api/policies/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(testPolicy)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Policy creation test result:', data);
            })
            .catch(error => {
                console.error('Policy creation test error:', error);
            });
        }
    }

    // Test claim recording
    function testClaimRecording() {
        const claimForm = document.querySelector('#claimForm');
        if (claimForm) {
            console.log('Testing claim recording...');
            const testClaim = {
                policy_id: 1,
                claim_type: 'accident',
                description: 'Test claim',
                amount: 5000
            };
            
            fetch('/api/claims/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(testClaim)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Claim recording test result:', data);
            })
            .catch(error => {
                console.error('Claim recording test error:', error);
            });
        }
    }

    // Run all tests
    function runAllTests() {
        console.log('Starting frontend tests...');
        testLogin();
        testReportGeneration();
        testPolicyCreation();
        testClaimRecording();
        console.log('Frontend tests completed.');
    }

    // Add test button to the page
    const testButton = document.createElement('button');
    testButton.textContent = 'Run Frontend Tests';
    testButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 10px 20px;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        z-index: 1000;
    `;
    testButton.onclick = runAllTests;
    document.body.appendChild(testButton);
}); 