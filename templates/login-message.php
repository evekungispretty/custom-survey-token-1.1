<?php
// templates/login-message.php
if (!defined('ABSPATH')) {
    exit;
}

function render_login_message() {
    ob_start();
    ?>
    <div class="login-message-container">
        <div class="login-message-box">
            <h2>Please Log In to View</h2>
            <p>This content is protected. Please log in with your ID to access this page.</p>
            <a href="https://education.ufl.edu/thrives/" class="login-button">
                Log in here <span class="arrow">→</span>
            </a>
        </div>
    </div>

    <style>
        .login-message-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .login-message-box {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
        }
        
        .login-message-box h2 {
            color: #333333;
            font-size: 24px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        
        .login-message-box p {
            color: #666666;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        
        .login-button {
            display: inline-flex;
            align-items: center;
            background-color: #37A0EA;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        
        .login-button:hover {
            background-color: #2980b9;
            color: white;
            text-decoration: none;
        }
        
        .arrow {
            margin-left: 8px;
            font-size: 18px;
        }
    </style>
    <?php
    return ob_get_clean();
}