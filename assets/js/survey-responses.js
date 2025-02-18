(function($) {
    'use strict';

    class SurveyResponses {
        constructor() {
            console.log('Initializing SurveyResponses...');
            
            if (!window.accessControl) {
                console.log('Access control not initialized, waiting...');
                setTimeout(() => this.initialize(), 500);
                return;
            }

            this.token = window.accessControl.token;
            console.log('Token found:', this.token);
            
            this.elements = {
                viewer: $('#survey-responses-viewer'),
                loading: $('#responses-loading'),
                content: $('#responses-section'),
                container: $('#responses-container'),
                error: $('#responses-error')
            };

            if (this.token) {
                this.loadResponses();
            } else {
                this.showError('Please log in to view your responses.');
            }
        }

        loadResponses() {
            console.log('Loading responses...');
            this.elements.loading.show();
            this.elements.content.hide();
            this.elements.error.hide();

            $.ajax({
                url: surveyConfig.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_survey_responses',
                    token: this.token,
                    nonce: surveyConfig.nonce
                },
                success: (response) => {
                    console.log('Response received:', response);
                    this.handleResponse(response);
                },
                error: (xhr, status, error) => {
                    console.error('Error loading responses:', {xhr, status, error});
                    this.showError('Error loading responses. Please try again later.');
                }
            });
        }

        handleResponse(response) {
            this.elements.loading.hide();
            
            if (response.success && Array.isArray(response.data)) {
                console.log('Processing responses:', response.data);
                
                if (response.data.length > 0) {
                    // Group responses by module hierarchy
                    const organizedResponses = this.organizeResponses(response.data);
                    const content = this.generateModulesContent(organizedResponses);
                    this.elements.container.html(content);
                } else {
                    this.elements.container.html(this.generateEmptyState());
                }
                
                this.elements.content.show();
            } else {
                this.showError('Invalid response format received.');
            }
        }

        organizeResponses(responses) {
            const organized = {};
            
            responses.forEach(item => {
                const parentModule = item.parent_module || 'General';
                const module = item.module || 'Uncategorized';
                
                if (!organized[parentModule]) {
                    organized[parentModule] = { modules: {} };
                }
                
                if (!organized[parentModule].modules[module]) {
                    organized[parentModule].modules[module] = {
                        responses: []
                    };
                }
                
                organized[parentModule].modules[module].responses.push(item);
            });
            
            return organized;
        }

        generateModulesContent(organized) {
            let html = '<div class="modules-container">';
            
            Object.entries(organized).forEach(([parentModule, data]) => {
                html += `
                    <div class="module-section">
                        <h3 class="module-header">${parentModule}</h3>
                        ${this.generateModuleContent(data.modules)}
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }

        generateModuleContent(modules) {
            let html = '';
            
            Object.entries(modules).forEach(([moduleName, moduleData]) => {
                if (moduleData.responses.length > 0) {
                    html += `
                        <div class="module-subsection">
                            <h4 class="submodule-header">${moduleName}</h4>
                            <div class="responses-list">
                                ${moduleData.responses.map(response => this.generateResponseItem(response)).join('')}
                            </div>
                        </div>
                    `;
                }
            });
            
            return html;
        }

        generateResponseItem(response) {
            return `
                <div class="response-item">
                    <div class="question">${this.escapeHtml(response.question)}</div>
                    <div class="answer">${this.formatResponse(response.response)}</div>
                    <div class="timestamp">Submitted on ${this.formatDate(response.created_at)}</div>
                </div>
            `;
        }

        generateEmptyState() {
            return `
                <div class="empty-state">
                    <p>You haven't submitted any responses yet.</p>
                    <p>Complete some modules to see your responses here.</p>
                </div>
            `;
        }

        formatResponse(response) {
            // Handle array responses (e.g., from checkboxes)
            if (response.includes(',')) {
                return response.split(',')
                    .map(item => this.escapeHtml(item.trim()))
                    .join('<br>');
            }
            return this.escapeHtml(response);
        }

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        showError(message) {
            this.elements.loading.hide();
            this.elements.content.hide();
            this.elements.error
                .html(`<p class="error-message">${this.escapeHtml(message)}</p>`)
                .show();
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        // Give access control time to initialize
        setTimeout(() => {
            if (!window.surveyResponses) {
                try {
                    window.surveyResponses = new SurveyResponses();
                } catch (error) {
                    console.error('Failed to initialize survey responses:', error);
                }
            }
        }, 1000);
    });

})(jQuery);