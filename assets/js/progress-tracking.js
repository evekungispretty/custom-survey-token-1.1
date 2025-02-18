(function($) {
    'use strict';
    /**
     * ProgressTracker Class
     * Handles tracking user progress through content based on button clicks
     * and displays progress information in a dashboard
     */
    class ProgressTracker {
        /**
         * Initialize the progress tracker
         * Sets up initial state and begins tracking if a token is present
         */
        constructor() {
            // Check if we have access control first
            if (!window.accessControl) {
                console.log('Access control not initialized, waiting...');
                setTimeout(() => this.initialize(), 500);
                return;
            }
    
            this.token = window.accessControl.token;
            this.formId = $('input[name="form_id"]').val() || 'default';
            this.moduleProgress = {};
            this.lastSaveTimestamp = null;
            this.initialized = false;
    
            if (this.token) {
                console.log('Initializing progress tracker with token:', this.token);
                this.initializeTracking().catch(error => {
                    console.error('Failed to initialize progress tracking:', error);
                });
            } else {
                console.log('No token found, progress tracking disabled');
            }
        }
    
    

        /**
         * Set up event listeners and load initial progress
         */
        async initializeTracking() {
            try {
                // Load existing progress
                await this.loadProgress();
                
                // Set up event listeners
                this.setupEventListeners();
                
                // Update UI elements
                this.updateProgressUI();
                this.updateTrackedButtons();
                
                // Set up auto-save
                setInterval(() => {
                    this.saveProgress().catch(error => {
                        console.error('Auto-save failed:', error);
                    });
                }, 30000);
    
                // Save on page unload
                $(window).on('beforeunload', () => {
                    this.saveProgress();
                });
    
                this.initialized = true;
                console.log('Progress tracking initialized successfully');
                
            } catch (error) {
                console.error('Error during progress tracking initialization:', error);
                throw error;
            }
        }

        setupEventListeners() {
            // Progress tracking button clicks
            $(document).on('click', '.progress-tracking-btn', (event) => {
                const $button = $(event.currentTarget);
                
                // Handle modified clicks
                if (event.ctrlKey || event.shiftKey || event.metaKey || event.button === 1) {
                    return true;
                }
    
                this.handleProgressClick($button);
            });
        }

        
        /**
         * Handle clicks on progress tracking buttons
         * @param {HTMLElement} button - The clicked button element
         */
        handleProgressClick(button) {
            const $button = $(button);
            
            // Get relative URL by removing domain
            const fullUrl = $button.data('url') || window.location.pathname;
            const relativeUrl = new URL(fullUrl, window.location.origin).pathname;
            
            const progressData = {
                title: $button.data('title') || document.title.replace(' - Thrives', '').trim(),
                url: relativeUrl,
                moduleNumber: $button.data('module') || this.extractModuleNumber(relativeUrl),
                timestamp: new Date().toISOString(),
                metadata: this.getButtonMetadata($button),
                visited: true
            };
        
            // Update progress
            this.moduleProgress[relativeUrl] = progressData;
            
            // Update last visited info
            this.lastVisited = {
                url: relativeUrl,
                timestamp: new Date().toISOString()
            };
        
            // Save progress and update UI
            this.saveProgress()
                .then(() => {
                    this.updateButtonState($button);
                    this.updateProgressUI();
                    this.showProgressFeedback($button);
                })
                .catch(error => {
                    console.error('Failed to save progress:', error);
                });
        }
        // Add this new method to handle initialization more robustly
        static initialize() {
            // Check if we've already initialized
            if (window.progressTrackerInitialized) {
                return;
            }
        
            // Set initialization flag
            window.progressTrackerInitialized = true;
        
            // Create new instance
            try {
                window.progressTracker = new ProgressTracker();
                console.log('Progress tracker initialized successfully');
            } catch (error) {
                console.error('Failed to initialize progress tracker:', error);
            }
        }
        
        /**
         * Extract additional metadata from button data attributes
         * @param {jQuery} $button - jQuery button element
         * @returns {Object} Metadata object
         */
        getButtonMetadata($button) {
            const metadata = {};
            const excludeKeys = ['title', 'url', 'module'];
            
            $.each($button.data(), (key, value) => {
                if (!excludeKeys.includes(key)) {
                    metadata[key] = value;
                }
            });
            
            return metadata;
        }

        /**
         * Update button appearance to show tracked state
         * @param {jQuery} $button - jQuery button element
         */
        updateButtonState($button) {
            $button.addClass('tracked-complete');
            
            const completedText = $button.data('completed-text');
            if (completedText) {
                $button.text(completedText);
            }
        }

        /**
         * Update all tracked buttons to reflect current progress
         */
        updateTrackedButtons() {
            $('.progress-tracking-btn').each((_, button) => {
                const $button = $(button);
                const buttonUrl = $button.data('url') || window.location.pathname;
                // Convert to relative URL for comparison
                const relativeUrl = new URL(buttonUrl, window.location.origin).pathname;
                
                if (this.moduleProgress[relativeUrl]?.visited) {
                    this.updateButtonState($button);
                }
            });
        }
        normalizeUrl(url) {
            try {
                return new URL(url, window.location.origin).pathname;
            } catch (e) {
                return url;
            }
        }

        /**
         * Show temporary feedback message when progress is saved
         * @param {jQuery} $button - jQuery button element
         */
        showProgressFeedback($button) {
            let $feedback = $('#progress-feedback');
            if (!$feedback.length) {
                $feedback = $('<div id="progress-feedback" class="progress-feedback"></div>')
                    .appendTo('body');
            }

            $feedback
                .text('Progress saved!')
                .addClass('show')
                .delay(2000)
                .fadeOut(() => {
                    $feedback.removeClass('show');
                });
        }

        /**
         * Extract module number from URL path
         * @param {string} path - URL path
         * @returns {number|null} Module number if found
         */
        extractModuleNumber(path) {
            const match = path.match(/module-(\d+)/);
            return match ? parseInt(match[1]) : null;
        }
    /* Save current progress to server
         */

    saveProgress() {
        if (!this.token) {
            console.log('No token available, skipping progress save');
            return Promise.reject(new Error('No token available'));
        }
    
        // Prepare save data
        const saveData = new FormData();
        saveData.append('action', 'save_user_progress');
        saveData.append('nonce', progressData.nonce);
        saveData.append('token', this.token);
        saveData.append('form_id', this.formId);
        saveData.append('page_id', document.body.dataset.pageId || '0');
        saveData.append('page_url', window.location.pathname);
        saveData.append('last_visited_url', window.location.pathname);
        saveData.append('last_visited_timestamp', new Date().toISOString());
    
        // Ensure module_progress is properly serialized
        try {
            const moduleProgressString = JSON.stringify(this.moduleProgress);
            saveData.append('module_progress', moduleProgressString);
        } catch (e) {
            console.error('Error serializing module progress:', e);
            return Promise.reject(e);
        }
    
        // Implement retry logic
        return this.attemptSave(saveData);
    }
        /* Load progress
         */
    loadProgress() {
        if (!this.token) {
            console.log('No token available for loading progress');
            return Promise.reject(new Error('No token available'));
        }
    
        return new Promise((resolve, reject) => {
            $.ajax({
                url: progressData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_user_progress',
                    nonce: progressData.nonce,
                    token: this.token,
                    form_id: this.formId
                },
                dataType: 'json',
                success: (response) => {
                    console.log('Progress load response:', response);
                    
                    if (response.success && response.data.progress) {
                        const progress = response.data.progress;
                        
                        try {
                            // Handle module_progress
                            if (progress.module_progress) {
                                this.moduleProgress = typeof progress.module_progress === 'string' 
                                    ? JSON.parse(progress.module_progress)
                                    : progress.module_progress;
                            } else {
                                this.moduleProgress = {};
                            }
    
                            // Handle last visited data
                            if (progress.last_visited_url && progress.last_visited_timestamp) {
                                this.lastVisited = {
                                    url: progress.last_visited_url,
                                    timestamp: progress.last_visited_timestamp
                                };
                            }
                            
                            this.updateProgressUI();
                            resolve(progress);
                        } catch (e) {
                            console.error('Error parsing progress data:', e);
                            reject(e);
                        }
                    } else {
                        // Initialize empty progress
                        this.moduleProgress = {};
                        resolve(null);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error loading progress:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    reject(error);
                }
            });
        });
    }

attemptSave(saveData, attempt = 1, maxAttempts = 3) {
    console.log(`Attempting to save progress (attempt ${attempt}/${maxAttempts})`);

    return new Promise((resolve, reject) => {
        $.ajax({
            url: progressData.ajaxurl,
            type: 'POST',
            data: saveData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: (xhr) => {
                console.log('Sending progress save request...');
            },
            success: (response) => {
                console.log('Progress save response:', response);
                
                if (response.success) {
                    console.log('Progress saved successfully');
                    this.lastSaveTimestamp = response.data.timestamp;
                    resolve(response);
                } else {
                    const error = new Error(response.data?.message || 'Server reported error');
                    error.response = response;
                    reject(error);
                }
            },
            error: (xhr, status, error) => {
                console.error('Progress save error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });

                // Check if we should retry
                if (attempt < maxAttempts) {
                    const delay = Math.min(1000 * Math.pow(2, attempt - 1), 5000);
                    console.log(`Retrying in ${delay}ms...`);
                    
                    setTimeout(() => {
                        this.attemptSave(saveData, attempt + 1, maxAttempts)
                            .then(resolve)
                            .catch(reject);
                    }, delay);
                } else {
                    reject(new Error('Max retry attempts reached'));
                }
            }
        });
    });
}


handleSaveError(error, currentRetry, maxRetries, retryFunction) {
    if (currentRetry < maxRetries) {
        const nextRetry = currentRetry + 1;
        const delay = Math.min(1000 * Math.pow(2, currentRetry), 5000); // Exponential backoff with 5s max
        
        console.log(`Retry ${nextRetry}/${maxRetries} scheduled in ${delay}ms`);
        setTimeout(() => {
            console.log(`Executing retry ${nextRetry}/${maxRetries}`);
            retryFunction();
        }, delay);
    } else {
        console.error('Max retries reached, progress save failed');
    }
}

        /**
         * Update the progress dashboard UI
         */

// Add this method to your ProgressTracker class
updateProgressUI() {
    const $overview = $('#user-progress-overview');
    
    if (!$overview.length) {
        console.log('Progress overview container not found');
        return;
    }

    // Filter and sort progress entries
    const sortedVisits = Object.entries(this.moduleProgress)
        .filter(([_, data]) => data.visited)
        .sort((a, b) => new Date(b[1].timestamp) - new Date(a[1].timestamp));

    const lastVisited = sortedVisits[0];
    
    let html = '';

    // Add learning progress section only if we have visits
    if (lastVisited) {
        html = `
            <div class="card-content">
                <div class="last-visited">
                    <h4>Last Visited Module</h4>
                    <div class="module-title">
                        <a href="${lastVisited[0]}" class="module-link">
                            ${lastVisited[1].title || 'Module ' + this.extractModuleNumber(lastVisited[0])}
                        </a>
                    </div>
                    <div class="visit-time">
                        ${new Date(lastVisited[1].timestamp).toLocaleDateString()} at 
                        ${new Date(lastVisited[1].timestamp).toLocaleTimeString()}
                    </div>
                </div>
                ${this.generateModuleList(sortedVisits)}
            </div>
        `;
    } else {
        html = `
            <div class="card-content empty-state">
                <p>No learning progress yet</p>
                <p>Visit learning modules to track your progress</p>
            </div>
        `;
    }

    $overview.html(html);
    this.applyProgressStyles();
}
        generateModuleResponsesHTML(responses) {
            let html = '<div class="modules-container">';
            
            // Sort parent modules alphabetically
            const sortedParentModules = Object.keys(responses).sort();
            
            sortedParentModules.forEach(parentModule => {
                if (parentModule === 'Uncategorized') return; // Skip uncategorized items
                
                html += `
                    <div class="progress-card module-card">
                        <div class="card-header">
                            <h3>${parentModule}</h3>
                        </div>
                        <div class="card-content">
                            ${this.generateChildModulesHTML(responses[parentModule].modules)}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }
        
        generateChildModulesHTML(modules) {
            let html = '';
            
            // Sort child modules alphabetically
            const sortedModules = Object.keys(modules).sort();
            
            sortedModules.forEach(moduleName => {
                const moduleData = modules[moduleName];
                html += `
                    <div class="child-module">
                        <h4 class="module-title">${moduleName}</h4>
                        <div class="responses-container">
                            ${this.generateResponsesListHTML(moduleData.responses)}
                        </div>
                    </div>
                `;
            });
            
            return html;
        }
        
        generateResponsesListHTML(responses) {
            return responses.map(response => `
                <div class="response-item">
                    <div class="question">${response.question}</div>
                    <div class="answer">${response.response}</div>
                    <div class="timestamp">${new Date(response.created_at).toLocaleString()}</div>
                </div>
            `).join('');
        }


        /**
         * Generate HTML for progress dashboard
         * @param {Array} sortedVisits - Sorted array of progress entries
         * @returns {string} Generated HTML
         */
        generateProgressHTML(sortedVisits) {
            const lastVisited = sortedVisits[0];
            
            return `
                <div class="progress-card">
                    <div class="card-header">
                        <h3>My Learning Progress</h3>
                        ${sortedVisits.length ? `
                            <span class="module-count">
                                ${sortedVisits.length} item${sortedVisits.length !== 1 ? 's' : ''} completed
                            </span>
                        ` : ''}
                    </div>
                    ${lastVisited ? this.generateCompletedContent(lastVisited, sortedVisits) : this.generateEmptyState()}
                </div>
            `;
        }

        /**
         * Generate HTML for completed content section
         * @param {Array} lastVisited - Most recently completed item
         * @param {Array} sortedVisits - All completed items
         * @returns {string} Generated HTML
         */
        generateCompletedContent(lastVisited, sortedVisits) {
            return `
                <div class="card-content">
                    <div class="last-visited">
                        <h4>Last Visited Module</h4>
                        <div class="module-title">
                            <a href="${lastVisited[0]}" class="module-link">
                                ${lastVisited[1].title || 'Module ' + this.extractModuleNumber(lastVisited[0])}
                            </a>
                        </div>
                        <div class="visit-time">
                            ${new Date(lastVisited[1].timestamp).toLocaleDateString()} at 
                            ${new Date(lastVisited[1].timestamp).toLocaleTimeString()}
                        </div>
                    </div>
                    ${this.generateModuleList(sortedVisits)}
                </div>
            `;
        }

        /**
         * Generate HTML for empty state
         * @returns {string} Generated HTML
         */
        generateEmptyState() {
            return `
                <div class="card-content empty-state">
                    <p>No items completed yet</p>
                    <a href="thrives/modules-home/" class="start-button">Start Learning</a>
                </div>
            `;
        }

        /**
         * Generate HTML for list of completed modules
         * @param {Array} sortedVisits - Sorted array of completed items
         * @returns {string} Generated HTML
         */
        generateModuleList(sortedVisits) {
            if (sortedVisits.length <= 1) return '';
            
            return `
                <div class="module-list">
                    <h4>Recently Visited Modules</h4>
                    <ul>
                        ${sortedVisits.slice(0, 5).map(([path, data]) => `
                            <li>
                                <a href="${path}" class="module-link">
                                    ${data.title || 'Module ' + this.extractModuleNumber(path)}
                                </a>
                                <span class="visit-time">
                                    ${new Date(data.timestamp).toLocaleDateString()}
                                </span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
        }

        /**
         * Apply styles to the progress dashboard
         */
        applyProgressStyles() {
            if (!$('#progress-dashboard-styles').length) {
                $('head').append(`
                    <style id="progress-dashboard-styles">
                        .last-visited {
                            margin-bottom: 20px;
                        }
                        
                        .module-title {
                            font-size: 1.1em;
                            margin: 8px 0;
                        }
                        
                        .module-link {
                            color: #37A0EA;
                            text-decoration: none;
                        }
                        
                        .module-link:hover {
                            text-decoration: underline;
                        }
                        
                        .visit-time {
                            color: #666;
                            font-size: 0.9em;
                        }
                        
                        .module-list {
                            margin-top: 20px;
                            padding-top: 20px;
                            border-top: 1px solid #eee;
                        }
                        
                        .module-list h4 {
                            margin-bottom: 15px;
                            color: #333;
                        }
                        
                        .module-list ul {
                            list-style: none;
                            padding: 0;
                            margin: 0;
                        }
                        
                        .module-list li {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 8px 0;
                            border-bottom: 1px solid #f5f5f5;
                        }
                        
                        .module-list li:last-child {
                            border-bottom: none;
                        }
                        
                        .empty-state {
                            text-align: center;
                            padding: 20px;
                            color: #666;
                        }
                        
                        .empty-state p {
                            margin: 5px 0;
                        }
        
                        h4 {
                            color: #333;
                            margin: 0 0 10px 0;
                        }
                    </style>
                `);
            }
        }
    }

    // Initialize the tracker when the document is ready
    $(document).ready(() => {
        setTimeout(() => {
            ProgressTracker.initialize();
        }, 1000);
    });

})(jQuery);