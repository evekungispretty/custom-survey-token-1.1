// File: assets/js/survey-token-entry.js
(function($) {
    'use strict';

    function showMessage(message, type) {
        const $messageDiv = $('#token-message');
        $messageDiv.removeClass('error success')
            .addClass(type)
            .text(message)
            .show();
    }

    function storeToken(token) {
        // Store token with 24-hour expiration
        const expires = new Date().getTime() + (24 * 60 * 60 * 1000);
        const tokenData = {
            token: token,
            expires: expires
        };
        localStorage.setItem('survey_token_data', JSON.stringify(tokenData));
    }

    $(document).ready(function() {
        // Handle input formatting
        $('#study-token').on('input', function() {
            let value = this.value.toUpperCase();
            let letter = value.charAt(0).replace(/[^A-Z]/g, '');
            let numbers = value.substring(1).replace(/[^\d]/g, '');
            this.value = letter + numbers;
        });

        // Check for existing token and redirect if valid
        const tokenData = localStorage.getItem('survey_token_data');
        if (tokenData) {
            try {
                const data = JSON.parse(tokenData);
                const now = new Date().getTime();
                if (now < data.expires) {
                    const redirectUrl = $('#verify-token-link').attr('href');
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                        return;
                    }
                }
            } catch (e) {
                localStorage.removeItem('survey_token_data');
            }
        }

        // Handle token verification
        $('#verify-token-link').on('click', function(e) {
            e.preventDefault();
            
            const token = $('#study-token').val().trim().toUpperCase();
            const redirectUrl = $(this).attr('href');
            
            if (!token) {
                showMessage('Please enter your ID', 'error');
                return;
            }

            // Validate format: one letter followed by four numbers
            if (!token.match(/^[A-Z]\d{4}$/)) {
                showMessage('Please enter one letter followed by four numbers (e.g., A1234)', 'error');
                return;
            }

            $.ajax({
                url: surveyTokenData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'verify_study_token',
                    token: token,
                    nonce: surveyTokenData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('ID verified! Redirecting...', 'success');
                        storeToken(token);
                        setTimeout(function() {
                            window.location.href = redirectUrl;
                        }, 1500);
                    } else {
                        showMessage(response.data.message || 'Invalid ID', 'error');
                    }
                },
                error: function() {
                    showMessage('Error verifying ID. Please try again.', 'error');
                }
            });
        });
    });
})(jQuery);