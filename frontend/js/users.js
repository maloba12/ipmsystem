document.addEventListener('DOMContentLoaded', function() {
    // Fetch system settings
    fetchSystemSettings();
    
    // Add event listeners for user actions
    setupUserActions();
});

async function fetchSystemSettings() {
    try {
        const response = await fetch('../backend/api/settings.php');
        const data = await response.json();
        
        if (response.ok) {
            // Handle successful settings fetch
            console.log('System settings loaded:', data);
            // You can update UI elements here if needed
        } else {
            throw new Error(data.message || 'Failed to fetch system settings');
        }
    } catch (error) {
        console.error('Error fetching system settings:', error);
        showErrorMessage('Failed to fetch system settings. Please try again later.');
    }
}

function setupUserActions() {
    // Add new user form submission
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await handleAddUser(addUserForm);
        });
    }

    // Delete user confirmation
    const deleteUserButtons = document.querySelectorAll('.delete-user');
    deleteUserButtons.forEach(button => {
        button.addEventListener('click', async (e) => {
            if (confirm('Are you sure you want to delete this user?')) {
                await handleDeleteUser(e.target.dataset.userId);
            }
        });
    });
}

async function handleAddUser(form) {
    try {
        const formData = new FormData(form);
        const response = await fetch('/ipmsystem/backend/api/users.php?action=add', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showSuccessMessage('User added successfully');
            // Refresh user list or redirect
            window.location.reload();
        } else {
            throw new Error(result.message || 'Failed to add user');
        }
    } catch (error) {
        console.error('Error adding user:', error);
        showErrorMessage('Failed to add user. Please try again.');
    }
}

async function handleDeleteUser(userId) {
    try {
        const response = await fetch(`/ipmsystem/backend/api/users.php?action=delete&id=${userId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showSuccessMessage('User deleted successfully');
            // Refresh user list
            window.location.reload();
        } else {
            throw new Error(result.message || 'Failed to delete user');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showErrorMessage('Failed to delete user. Please try again.');
    }
}

function showErrorMessage(message) {
    const errorContainer = document.createElement('div');
    errorContainer.className = 'alert alert-error mb-4';
    errorContainer.textContent = message;
    document.querySelector('.container').insertBefore(errorContainer, document.querySelector('.container').firstChild);
    
    // Remove after 5 seconds
    setTimeout(() => {
        errorContainer.remove();
    }, 5000);
}

function showSuccessMessage(message) {
    const successContainer = document.createElement('div');
    successContainer.className = 'alert alert-success mb-4';
    successContainer.textContent = message;
    document.querySelector('.container').insertBefore(successContainer, document.querySelector('.container').firstChild);
    
    // Remove after 5 seconds
    setTimeout(() => {
        successContainer.remove();
    }, 5000);
}
