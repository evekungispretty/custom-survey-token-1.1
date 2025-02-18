// File: access-control.js
(function($) {
    'use strict';

    const debug = {
        log: function(message, data) {
            if (surveySystem && surveySystem.isDebug) {
                console.log('AccessControl:', message);
                if (data) console.log(data);
            }
        },
        error: function(message, error) {
            if (surveySystem && surveySystem.isDebug) {
                console.error('AccessControl Error:', message);
                if (error) console.error(error);
            }
        }
    };

    class AccessControl {
        constructor() {
            if (typeof surveySystem === 'undefined') {
                debug.error('Survey system configuration not found');
                return;
            }
    
            this.config = surveySystem;
            this.token = this.getStoredToken();
            
            this.protectedPaths = {
                parents: [
                    '/modules-home/'
                ],
                modulePatterns: [
                    '/module-\\d+/',  // Matches any module number
                ],
                standalone: [
                    '/my-progress/',
                    '/quiz-responses/'
                ]
            };
            
            this.initialize();
        }

        getStoredToken() {
            // Try sessionStorage first
            let token = sessionStorage.getItem('survey_token');
            let tokenExpiry = sessionStorage.getItem('token_expiry');
            
            // If not in sessionStorage, try localStorage
            if (!token) {
                token = localStorage.getItem('survey_token');
                tokenExpiry = localStorage.getItem('token_expiry');
            }
            
            // If no token found anywhere
            if (!token) {
                debug.log('No token found in any storage');
                return null;
            }

            // Check expiry
            if (tokenExpiry && new Date().getTime() > parseInt(tokenExpiry)) {
                debug.log('Token expired');
                this.clearToken();
                return null;
            }

            // Verify token format
            if (!this.isValidTokenFormat(token)) {
                debug.log('Invalid token format');
                this.clearToken();
                return null;
            }

            debug.log('Valid token found:', token);
            this.syncToken(token, tokenExpiry);
            return token;
        }

        isValidTokenFormat(token) {
            return /^[A-Z]\d{4}$/.test(token);
        }

        syncToken(token, expiry) {
            if (!this.isValidTokenFormat(token)) {
                debug.error('Attempted to sync invalid token format');
                return;
            }
            
            // Store in both session and local storage
            sessionStorage.setItem('survey_token', token);
            localStorage.setItem('survey_token', token);
            if (expiry) {
                sessionStorage.setItem('token_expiry', expiry);
                localStorage.setItem('token_expiry', expiry);
            }
        }

        clearToken() {
            sessionStorage.removeItem('survey_token');
            sessionStorage.removeItem('token_expiry');
            localStorage.removeItem('survey_token');
            localStorage.removeItem('token_expiry');
            
            debug.log('Cleared token from all storage');
        }
    
        initialize() {
            debug.log('Initializing access control');
            const currentPath = window.location.pathname.toLowerCase();
            debug.log('Current path:', currentPath);
            
            if (this.isProtectedPage(currentPath)) {
                debug.log('Protected page detected');
                if (!this.token) {
                    debug.log('No token found, requesting login');
                    this.requestLoginMessage();
                } else {
                    debug.log('Token found, verifying...');
                    this.verifyToken()
                        .then(response => {
                            debug.log('Token verification successful:', response);
                            $(document).trigger('tokenVerified', [response]);
                        })
                        .catch(error => {
                            debug.error('Token verification failed:', error);
                            this.requestLoginMessage();
                        });
                }
            } else {
                debug.log('Page is not protected');
            }
        }
    
        verifyToken() {
            return new Promise((resolve, reject) => {
                if (!this.token) {
                    reject(new Error('No token available'));
                    return;
                }

                if (!this.isValidTokenFormat(this.token)) {
                    reject(new Error('Invalid token format'));
                    return;
                }

                debug.log('Starting token verification process');
                
                // Try REST API first
                this.verifyTokenREST()
                    .then(resolve)
                    .catch(error => {
                        debug.log('REST verification failed, trying AJAX fallback:', error);
                        return this.verifyTokenAJAX();
                    })
                    .then(resolve)
                    .catch(error => {
                        debug.error('All verification methods failed:', error);
                        this.clearToken();
                        reject(error);
                    });
            });
        }

        verifyTokenREST() {
            debug.log('Attempting REST API verification');
            return new Promise((resolve, reject) => {
                const restUrl = this.config.restUrl + '/verify-token';
                debug.log('REST URL:', restUrl);

                fetch(restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.restNonce
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        token: this.token,
                        isInitialLogin: false
                    })
                })
                .then(response => {
                    debug.log('REST response received:', response);
                    if (!response.ok) {
                        throw new Error(`REST API returned ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        debug.log('REST verification successful:', data);
                        if (data.expiry) {
                            this.syncToken(this.token, data.expiry);
                        }
                        resolve(data);
                    } else {
                        throw new Error(data.message || 'Verification failed');
                    }
                })
                .catch(reject);
            });
        }

        verifyTokenAJAX() {
            debug.log('Attempting AJAX verification');
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: this.config.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'verify_study_token',
                        token: this.token,
                        nonce: this.config.tokenNonce,
                        isInitialLogin: false
                    },
                    success: (response) => {
                        debug.log('AJAX response received:', response);
                        if (response.success) {
                            if (response.data && response.data.expiry) {
                                this.syncToken(this.token, response.data.expiry);
                            }
                            resolve(response);
                        } else {
                            reject(new Error(response.data?.message || 'AJAX verification failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        debug.error('AJAX error:', { xhr, status, error });
                        reject(new Error(error || 'AJAX request failed'));
                    }
                });
            });
        }
    
        isProtectedPage(currentPath) {
            debug.log('Checking if path is protected:', currentPath);
            
            // Check standalone protected paths
            for (const path of this.protectedPaths.standalone) {
                if (currentPath.includes(path.toLowerCase())) {
                    debug.log(`Protected standalone path matched: ${path}`);
                    return true;
                }
            }
            
            // Check parent paths
            for (const parentPath of this.protectedPaths.parents) {
                if (currentPath.includes(parentPath.toLowerCase())) {
                    debug.log(`Protected parent path matched: ${parentPath}`);
                    return true;
                }
            }

            // Check module patterns
            for (const pattern of this.protectedPaths.modulePatterns) {
                if (new RegExp(pattern, 'i').test(currentPath)) {
                    debug.log(`Protected module pattern matched: ${pattern}`);
                    return true;
                }
            }
            
            return false;
        }

        requestLoginMessage() {
            const $mainContent = $('.entry-content, #main-content > .container');
            if (!$mainContent.length) {
                debug.error('Could not find main content container');
                return;
            }

            $mainContent.html('<div class="loading-message" style="text-align: center; padding: 20px;">Loading...</div>');

            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_login_message',
                    nonce: this.config.tokenNonce
                },
                success: (response) => {
                    if (response.success && response.data && response.data.message) {
                        $mainContent.fadeOut(200, function() {
                            $(this).html(response.data.message).fadeIn(200);
                        });
                    } else {
                        this.showErrorMessage($mainContent);
                    }
                },
                error: (error) => {
                    debug.error('Error getting login message:', error);
                    this.showErrorMessage($mainContent);
                }
            });
        }

        showErrorMessage($container) {
            const message = `
                <div class="error-message" style="text-align: center; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px 0;">
                    <h3>Please Log In</h3>
                    <p>You need to log in to access this content. <a href="${this.config.homeUrl}" style="color: #721c24; text-decoration: underline;">Click here to log in</a></p>
                </div>
            `;
            $container.html(message);
        }
    }
    
    // Initialize when document is ready
    $(document).ready(() => {
        window.accessControl = new AccessControl();
    });

})(jQuery);