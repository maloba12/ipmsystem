<?php
session_start();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    // Here you would typically save this data to your database
    // For now, we'll just simulate the success and redirect
    
    // Set session variables
    $_SESSION['user'] = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'role' => $role
    ];
    
    // Redirect based on role
    if ($role === 'Client') {
        header('Location: client_dashboard.php');
        exit();
    } elseif ($role === 'Insurance Agent') {
        header('Location: agent_dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ZamSure Insurance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        .animate-shake {
            animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
            20%, 40%, 60%, 80% { transform: translateX(10px); }
        }

        .social-login {
            display: flex;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .social-btn {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .google-btn {
            background: #fff;
            color: #333;
            border: 1px solid #ddd;
        }

        .facebook-btn {
            background: #1877f2;
            color: white;
        }

        .stay-signed-in {
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-message {
            background: #d1fae5;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            display: none;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            display: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-500 to-purple-600 min-h-screen p-5">
    <div class="max-w-6xl mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-br from-gray-800 to-blue-600 text-white p-8 text-center">
            <h1 class="text-3xl font-bold mb-2">Create Your Account</h1>
            <p class="opacity-90 text-lg">Join ZamSure Insurance today</p>
        </div>

        <div class="max-w-2xl mx-auto p-8">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-200 text-red-800 p-4 rounded-lg mb-5">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-6" id="registrationForm">
                <div class="success-message" id="successMessage">
                    Registration successful! Redirecting...
                </div>
                <div class="error-message" id="errorMessage"></div>

                <!-- Social Login -->
                <div class="social-login">
                    <button type="button" class="social-btn google-btn">
                        <i class="fab fa-google"></i>
                        Continue with Google
                    </button>
                    <button type="button" class="social-btn facebook-btn">
                        <i class="fab fa-facebook-f"></i>
                        Continue with Facebook
                    </button>
                </div>

                <div class="divide-y divide-gray-200 my-4">
                    <div class="text-center">
                        <span class="bg-white px-4 text-gray-500">or</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-2 font-semibold text-gray-800">First Name *</label>
                        <input type="text" name="first_name" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                    </div>
                    <div>
                        <label class="block mb-2 font-semibold text-gray-800">Last Name *</label>
                        <input type="text" name="last_name" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                    </div>
                </div>

                <div>
                    <label class="block mb-2 font-semibold text-gray-800">Email Address *</label>
                    <input type="email" name="email" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                </div>

                <div>
                    <label class="block mb-2 font-semibold text-gray-800">Phone Number *</label>
                    <input type="tel" name="phone" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-2 font-semibold text-gray-800">Password *</label>
                        <input type="password" name="password" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                    </div>
                    <div>
                        <label class="block mb-2 font-semibold text-gray-800">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                    </div>
                </div>

                <div>
                    <label class="block mb-2 font-semibold text-gray-800">Account Type *</label>
                    <select name="role" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-600" required>
                        <option value="Client">Client</option>
                        <option value="Insurance Agent">Insurance Agent</option>
                    </select>
                </div>

                <div class="flex items-start mb-6">
                    <div class="flex items-center h-5">
                        <input type="checkbox" name="terms" class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-blue-300" required>
                    </div>
                    <label class="ml-2 text-sm text-gray-600">I agree to the <a href="#" class="text-blue-600 hover:underline">Terms and Conditions</a> and <a href="#" class="text-blue-600 hover:underline">Privacy Policy</a></label>
                </div>

                <div class="stay-signed-in">
                    <input type="checkbox" name="stay_signed_in" id="staySignedIn">
                    <label for="staySignedIn">Stay signed in</label>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                    <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-gradient-to-br from-blue-600 to-gray-800 text-white rounded-lg font-semibold hover:shadow-lg hover:-translate-y-0.5 transition-transform">Create Account</button>
                    <a href="index.php" class="text-gray-600 hover:text-gray-800">Already have an account? Login</a>
                </div>
            </form>
        </div>
    </div>
        <script>
            // Form submission handler
            document.getElementById('registrationForm').addEventListener('submit', function(e) {
                const form = e.target;
                const errorMessage = document.getElementById('errorMessage');
                const successMessage = document.getElementById('successMessage');

                // Reset messages
                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';

                // Check password match
                const password = form.querySelector('input[name="password"]').value;
                const confirmPassword = form.querySelector('input[name="confirm_password"]').value;

                if (password !== confirmPassword) {
                    errorMessage.textContent = 'Passwords do not match!';
                    errorMessage.style.display = 'block';
                    form.classList.add('animate-shake');
                    setTimeout(() => form.classList.remove('animate-shake'), 800);
                    e.preventDefault();
                    return;
                }
            });

            // Social login buttons
            document.querySelectorAll('.social-btn').forEach(button => {
                button.addEventListener('click', () => {
                    // Implement social login logic here
                    alert('Social login coming soon!');
                });
            });

            // Success message animation
            function showSuccessMessage() {
                const successMessage = document.getElementById('successMessage');
                successMessage.style.display = 'block';
                successMessage.classList.add('animate-fade-in');
            }

            // Error message animation
            function showErrorMessage(message) {
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
                errorMessage.classList.add('animate-fade-in');
            }
        </script>
    </body>
</html> 