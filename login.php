<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// login.php - Fixed login page

// Start session
session_start();

// Include config
require_once 'config.php';

// Check if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$error = '';
$email = isset($_POST['email']) ? trim($_POST['email']) : 'admin@hotel.com';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Test connection first
        $test = testConnection();
        
        if (!$test['connected']) {
            $error = 'Database connection failed. Please check config.php';
        } elseif (!$test['has_staff_table']) {
            // Try to auto-setup database
            setupDatabase();
            $test = testConnection(); // Test again after setup
        }
        
        if ($test['connected'] && $test['has_staff_table']) {
            // Connect to database
            $conn = connectDB();
            
            // Prepare and execute query
            $stmt = $conn->prepare("SELECT id, email, password, name, role, status FROM staff WHERE email = ?");
            
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if user is active
                    if ($user['status'] !== 'active') {
                        $error = 'Account is inactive. Please contact administrator.';
                    } else {
                        $stored_password = $user['password'];
                        
                        // Check password (support both MD5 and password_hash)
                        $authenticated = false;
                        
                        // Try password_verify first
                        if (password_verify($password, $stored_password)) {
                            $authenticated = true;
                        } 
                        // Try MD5 for legacy passwords
                        elseif (md5($password) === $stored_password) {
                            $authenticated = true;
                            
                            // Upgrade to password_hash
                            $new_hash = password_hash($password, PASSWORD_DEFAULT);
                            $update_stmt = $conn->prepare("UPDATE staff SET password = ? WHERE id = ?");
                            $update_stmt->bind_param("si", $new_hash, $user['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        if ($authenticated) {
                            // Set session variables
                            $_SESSION['loggedin'] = true;
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['name'] = $user['name'] ?? 'User';
                            $_SESSION['role'] = $user['role'] ?? 'Staff';
                            
                            // Redirect to dashboard
                            header('Location: dashboard.php');
                            exit;
                        } else {
                            $error = 'Invalid email or password';
                        }
                    }
                } else {
                    $error = 'Invalid email or password';
                }
                
                $stmt->close();
            } else {
                $error = 'Database query error';
            }
            
            $conn->close();
        } else {
            $error = 'Database setup incomplete. Please check configuration.';
        }
    }
}

// Get database status for display
$db_status = testConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EvoDesk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #4a6fa5 0%, #3a5980 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .tagline {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .login-form {
            padding: 30px;
        }
        
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        
        .status-box {
            background: #f0f7ff;
            border: 1px solid #d1e3ff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .status-title {
            color: #4a6fa5;
            margin-bottom: 10px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        
        .status-item i {
            margin-right: 8px;
            width: 16px;
        }
        
        .status-good {
            color: #28a745;
        }
        
        .status-bad {
            color: #dc3545;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .input-field {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border 0.3s;
            background: #fafafa;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #4a6fa5;
            background: white;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 5px;
        }
        
        .signin-btn {
            width: 100%;
            background: linear-gradient(135deg, #4a6fa5 0%, #3a5980 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .signin-btn:hover {
            background: linear-gradient(135deg, #3a5980 0%, #2e4766 100%);
            transform: translateY(-2px);
        }
        
        .signin-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .demo-info {
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        
        .demo-info h4 {
            color: #666;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.85rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .login-header {
                padding: 25px 20px;
            }
            
            .login-form {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">EvoDesk</div>
            <div class="tagline">Manage Smarter</div>
        </div>
        
        <form class="login-form" method="POST" action="">
            <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="status-box">
                <div class="status-title">Database Status</div>
                <div class="status-item">
                    <i class="fas fa-server"></i>
                    <span>Host: <?php echo htmlspecialchars(DB_HOST); ?></span>
                </div>
                <div class="status-item">
                    <i class="fas fa-database"></i>
                    <span>Database: <?php echo htmlspecialchars(DB_NAME); ?></span>
                </div>
                <div class="status-item">
                    <?php if ($db_status['connected']): ?>
                    <i class="fas fa-check-circle status-good"></i>
                    <span>Connection: Successful</span>
                    <?php else: ?>
                    <i class="fas fa-times-circle status-bad"></i>
                    <span>Connection: Failed</span>
                    <?php endif; ?>
                </div>
                <div class="status-item">
                    <?php if ($db_status['has_staff_table']): ?>
                    <i class="fas fa-check-circle status-good"></i>
                    <span>Staff Table: Found</span>
                    <?php else: ?>
                    <i class="fas fa-times-circle status-bad"></i>
                    <span>Staff Table: Not found (will auto-create)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="input-field" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="input-field" 
                           placeholder="Enter your password" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="signin-btn" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
            
            <div class="demo-info">
                <h4><i class="fas fa-key"></i> Default Credentials</h4>
                <p>Email: admin@hotel.com</p>
                <p>Password: admin (auto-upgrades to secure hash)</p>
            </div>
        </form>
        
        <div class="footer">
            © 2025 EvoDesk - Manage Smarter
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form submission loading state
        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        
        form.addEventListener('submit', function() {
            const btnIcon = submitBtn.querySelector('i');
            const btnText = submitBtn.querySelector('span') || submitBtn;
            
            // Save original text
            if (!submitBtn.dataset.originalText) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
            }
            
            // Show loading state
            btnIcon.className = 'fas fa-spinner fa-spin';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
        });
        
        // Auto-focus email field
        document.getElementById('email').focus();
    </script>
</body>
</html>