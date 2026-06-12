// Screen Navigation
function showLogin(role) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(role + '-screen').classList.add('active');
    
    // Only fetch Google URL for admin (staff doesn't have SSO)
    if (role === 'admin') {
        fetch('api/get-google-url.php?role=' + role)
            .then(r => r.json())
            .then(data => {
                const googleBtn = document.getElementById(role + 'GoogleBtn');
                if (googleBtn && data.url) {
                    googleBtn.href = data.url;
                }
            })
            .catch(err => console.log('Google auth not configured'));
    }
    
    // Reset forms when switching screens
    resetForms(role);
}

function showMain() {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById('main-screen').classList.add('active');
    
    // Clear all alerts
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.display = 'none';
        alert.textContent = '';
    });
}

// Tab Switching (for admin only now)
function switchTab(role, type) {
    // Update tab buttons
    document.querySelectorAll('#' + role + '-screen .tab-btn').forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('onclick').includes(type)) {
            t.classList.add('active');
        }
    });
    
    // Update tab content
    document.querySelectorAll('#' + role + '-screen .tab-content').forEach(t => {
        t.classList.remove('active');
    });
    document.getElementById(role + '-' + type + '-form').classList.add('active');
    
    // Reset forgot password view when switching tabs
    if (role === 'admin') {
        showLoginForm(role);
    }
}

// Password Toggle
function togglePassword(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Forgot Password Functions
function showForgotPassword(role) {
    hideAlerts(role);
    
    if (role === 'staff') {
        document.getElementById('staff-login-form').classList.remove('active');
        document.getElementById('staff-forgot-form').classList.add('active');
    } else if (role === 'admin') {
        document.getElementById('admin-login-wrapper').classList.remove('active');
        document.getElementById('admin-forgot-wrapper').classList.add('active');
    }
}

function showLoginForm(role) {
    hideAlerts(role);
    
    if (role === 'staff') {
        document.getElementById('staff-forgot-form').classList.remove('active');
        document.getElementById('staff-login-form').classList.add('active');
    } else if (role === 'admin') {
        document.getElementById('admin-forgot-wrapper').classList.remove('active');
        document.getElementById('admin-login-wrapper').classList.add('active');
    }
}

// Login Handler
function handleLogin(e, role) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('role', role);
    
    hideAlerts(role);
    const errorDiv = document.getElementById(role + '-error');
    const successDiv = document.getElementById(role + '-success');
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    submitBtn.disabled = true;
    
    fetch('api/login.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid JSON');
        }
    })
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            successDiv.textContent = 'Login successful! Redirecting...';
            successDiv.style.display = 'flex';
            
            setTimeout(() => {
                window.location.href = role === 'admin' ? 'admin.html' : 'staff.html';
            }, 1000);
        } else {
            errorDiv.textContent = data.error || 'Invalid email or password';
            errorDiv.style.display = 'flex';
            
            // Shake animation on error
            form.classList.add('shake');
            setTimeout(() => form.classList.remove('shake'), 500);
        }
    })
    .catch(err => {
        console.error('Login error:', err);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        errorDiv.textContent = 'Network error: ' + err.message;
        errorDiv.style.display = 'flex';
    });
    
    return false;
}

// Forgot Password Handler - UPDATED with better error handling
function handleForgotPassword(e, role) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('role', role);
    formData.append('action', 'forgot_password');
    
    hideAlerts(role);
    const errorDiv = document.getElementById(role + '-error');
    const successDiv = document.getElementById(role + '-success');
    
    // Validate email
    const email = formData.get('email');
    if (!email || !email.includes('@')) {
        errorDiv.textContent = 'Please enter a valid email address';
        errorDiv.style.display = 'flex';
        return;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    submitBtn.disabled = true;
    
    // Show sending message
    const tempMsg = document.createElement('div');
    tempMsg.className = 'alert alert-info';
    tempMsg.innerHTML = '<i class="fas fa-info-circle"></i> Sending reset link...';
    tempMsg.style.background = '#dbeafe';
    tempMsg.style.color = '#1e40af';
    tempMsg.style.display = 'flex';
    form.parentNode.insertBefore(tempMsg, form.nextSibling);
    
    fetch('api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        // Remove temp message
        if (tempMsg && tempMsg.parentNode) {
            tempMsg.parentNode.removeChild(tempMsg);
        }
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON:', text);
            throw new Error('Server returned invalid JSON. Please check console.');
        }
    })
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            successDiv.textContent = data.message || 'Reset link sent! Check your email.';
            successDiv.style.display = 'flex';
            form.reset();
            
            // Auto return to login after 5 seconds
            setTimeout(() => {
                showLoginForm(role);
                hideAlerts(role);
            }, 5000);
        } else {
            errorDiv.textContent = data.error || 'Failed to send reset link';
            errorDiv.style.display = 'flex';
        }
    })
    .catch(err => {
        console.error('Forgot password error:', err);
        
        // Remove temp message if still there
        if (tempMsg && tempMsg.parentNode) {
            tempMsg.parentNode.removeChild(tempMsg);
        }
        
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        let errorMessage = 'Network error. Please try again.';
        if (err.message.includes('404')) {
            errorMessage = 'API endpoint not found. Please check if auth.php exists.';
        } else if (err.message.includes('500')) {
            errorMessage = 'Server error. Please try again later.';
        } else if (err.message) {
            errorMessage = err.message;
        }
        
        errorDiv.textContent = errorMessage;
        errorDiv.style.display = 'flex';
    });
    
    return false;
}

// Utility Functions
function hideAlerts(role) {
    const errorDiv = document.getElementById(role + '-error');
    const successDiv = document.getElementById(role + '-success');
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
    }
    if (successDiv) {
        successDiv.style.display = 'none';
        successDiv.textContent = '';
    }
    
    // Also remove any temporary info messages
    document.querySelectorAll('.alert-info').forEach(msg => {
        if (msg.parentNode) {
            msg.parentNode.removeChild(msg);
        }
    });
}

function resetForms(role) {
    hideAlerts(role);
    
    // Reset all forms for this role
    const forms = document.querySelectorAll('#' + role + '-screen form');
    forms.forEach(form => form.reset());
    
    // Show login form by default
    showLoginForm(role);
    
    // Reset password visibility
    document.querySelectorAll('.toggle-password i').forEach(icon => {
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    });
    document.querySelectorAll('input[type="text"]').forEach(input => {
        if (input.name === 'password') {
            input.type = 'password';
        }
    });
}

// Add shake animation styles dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    .shake {
        animation: shake 0.5s ease-in-out;
    }
    
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
        padding: 12px 16px;
        border-radius: 8px;
        margin-top: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }
`;
document.head.appendChild(style);

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Add enter key support for forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                // Let the form submit naturally
            }
        });
    });
    
    // Check for URL parameters (error messages)
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    if (error) {
        // Show error message on main screen
        const mainScreen = document.getElementById('main-screen');
        if (mainScreen) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.style.marginTop = '20px';
            errorDiv.style.display = 'flex';
            
            switch(error) {
                case 'google_failed':
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Google login failed. Please try again.';
                    break;
                case 'admin_only_sso':
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Google login is only for admin users.';
                    break;
                case 'token_expired':
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password reset link has expired. Please request a new one.';
                    break;
                case 'invalid_token':
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Invalid password reset link.';
                    break;
                default:
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + error;
            }
            
            const loginBox = mainScreen.querySelector('.login-box');
            if (loginBox) {
                loginBox.appendChild(errorDiv);
            }
        }
    }
});