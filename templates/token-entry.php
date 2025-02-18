<?php
function render_token_entry() {
    // Verify nonce field
    wp_nonce_field('verify_token_nonce', 'token_nonce');
    
    ob_start();
    ?>
    <div class="survey-token-entry">
        <form id="token-entry-form" method="post">
            <h2>Enter Your Study Token</h2>
            <p>Please enter the token provided to you for this study.</p>
            
            <div class="token-input-group">
                <label for="study-token">Study Token:</label>
                <input type="text" id="study-token" name="study_token" pattern="[A-Za-z0-9]{32}" required>
                <?php wp_nonce_field('verify_study_token_nonce', 'study_token_nonce'); ?>
            </div>

            <button type="submit">Continue</button>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#token-entry-form').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: surveyAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'verify_study_token',
                    token: $('#study-token').val(),
                    nonce: $('#study_token_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        sessionStorage.setItem('survey_token', $('#study-token').val());
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Invalid token. Please try again.');
                    }
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}