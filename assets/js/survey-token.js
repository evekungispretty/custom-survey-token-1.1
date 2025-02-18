// File: assets/js/survey-token.js
(function($) {
    'use strict';

    class TokenEntryHandler {
        constructor() {
            this.setupEventListeners();
        }

        setupEventListeners() {
            // Format input as user types
            $('#study-token').on('input', this.formatTokenInput);

            // Handle verification button click
            $('#verify-token-link').on('click', (e) => {
                e.preventDefault();
                this.handleTokenVerification();
            });

            // Handle enter key press
            $('#study-token').on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    this.handleTokenVerification();
                }
            });
        }

        formatTokenInput(e) {
            let value = $(this).val().toUpperCase();
            // Allow only one letter followed by numbers
            let letter = value.charAt(0).replace(/[^A-Z]/g, '');
            let numbers = value.substring(1).replace(/[^\d]/g, '');
            $(this).val(letter + numbers);
        }

        handleTokenVerification() {
            const token = $('#study-token').val().trim().toUpperCase();
            const $message = $('#token-message');
            const redirectUrl = $('#verify-token-link').attr('href');
        
            // Clear previous messages
            $message.removeClass('success error').hide();
        
            // Validate token format
            if (!token) {
                this.showMessage('Please enter your ID', 'error');
                return;
            }
        
            if (!token.match(/^[A-Z]\d{4}$/)) {
                this.showMessage('Please enter one letter followed by four numbers (e.g., A1234)', 'error');
                return;
            }
        
            // Show loading state
            this.showMessage('Verifying ID...', 'info');
            $('#verify-token-link').prop('disabled', true).css('opacity', '0.7');
        
            // Use REST API endpoint
            const apiUrl = `${window.location.origin}/wp-json/thrives/v1/verify-token`;
            console.log('Attempting token verification at:', apiUrl);
        
            // First try REST API with proper error handling
            this.verifyWithRestApi(token, redirectUrl)
                .catch(error => {
                    console.log('Falling back to traditional AJAX due to:', error);
                    return this.verifyWithAjax(token, redirectUrl);
                })
                .catch(error => {
                    console.error('All verification methods failed:', error);
                    this.showMessage('Error verifying ID. Please try again later.', 'error');
                    $('#verify-token-link').prop('disabled', false).css('opacity', '1');
                });
        }
        
        async verifyWithRestApi(token, redirectUrl) {
            const apiUrl = surveySystem.restUrl + '/verify-token';
            
            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-WP-Nonce': surveySystem.restNonce
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ 
                        token: token,
                        isInitialLogin: true  // Add this flag for initial login
                    })
                });
        
                if (!response.ok) {
                    const error = await response.json();
                    console.error('REST API verification failed:', error);
                    throw new Error(error.message || 'REST API verification failed');
                }
        
                const data = await response.json();
                return this.handleVerificationSuccess(data, token, redirectUrl);
            } catch (error) {
                console.error('REST API error:', error);
                throw error;
            }
        }
        
        async verifyWithAjax(token, redirectUrl) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: surveySystem.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'verify_study_token',
                        token: token,
                        nonce: surveySystem.tokenNonce,
                        isInitialLogin: true  // Add this flag for initial login
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(this.handleVerificationSuccess(response.data, token, redirectUrl));
                        } else {
                            reject(new Error(response.data?.message || 'AJAX verification failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(error));
                    }
                });
            });
        }
        
        handleVerificationSuccess(data, token, redirectUrl) {
            // Store token in session storage
            sessionStorage.setItem('survey_token', token);
            if (data.expiry) {
                sessionStorage.setItem('token_expiry', data.expiry);
            }
            
            // Show success message and redirect
            this.showMessage('ID verified successfully! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);
            
            return data;
        }

        showMessage(message, type) {
            const $message = $('#token-message');
            $message.removeClass('success error info')
                   .addClass(type)
                   .html(message)
                   .fadeIn();
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new TokenEntryHandler();
    });

})(jQuery);