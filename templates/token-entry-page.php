<?php
function render_token_entry_page($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'redirect' => 'https://education.ufl.edu/thrives/modules-home/',
        'title' => 'Welcome to the THRIVES',
        'description' => 'Please enter your ID to begin.' 
    ), $atts);

    ob_start();
    ?>
    <div class="survey-token-entry">
        <div class="token-entry-container">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <p class="token-description"><?php echo esc_html($atts['description']); ?></p>

            <div class="token-input-group">
                <label for="study-token">Enter your User ID:</label>
                <input type="text" 
                       id="study-token" 
                       name="study_token"
                       required 
                       autocomplete="off"
                       maxlength="5"
                       placeholder="A1234"
                       style="text-transform: uppercase;">
                <p class="input-help">Hint: Your last initial and the last four digits of your phone number</p>
            </div>

            <div class="button-container">
                <a href="<?php echo esc_url($atts['redirect']); ?>" 
                   id="verify-token-link" 
                   class="token-button">
                    Continue
                </a>
            </div>

            <div id="token-message" class="token-message" style="display: none;"></div>
        </div>
    </div>

    <style>
        .survey-token-entry {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        .token-entry-container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .token-input-group {
            margin: 20px 0;
        }
        .token-input-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .token-input-group input {
            width: 100%;
            max-width: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 2px;
        }
        .input-help {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .token-button {
            display: inline-block;
            background: #00476b;
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        .token-button:hover {
            background: #2980b9;
            color: white;
            text-decoration: none;
        }
        .token-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .token-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .token-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .button-container {
            margin-top: 20px;
        }
    </style>
    <?php
    return ob_get_clean();
}

// Add shortcode for token entry page
add_shortcode('survey_token_entry_page', 'render_token_entry_page');
?>