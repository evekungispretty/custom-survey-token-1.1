<?php
// templates/view-responses.php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="survey-responses-viewer" class="survey-responses-viewer progress-card">
    <div class="card-header">
        <h3>My Quiz Responses</h3>
    </div>


    <!-- Rest of your existing template -->
    <div id="responses-loading" class="card-content">
        <div class="loading-indicator">Loading your responses...</div>
    </div>
    <div id="responses-section" class="card-content" style="display: none;">
        <div id="responses-container"></div>
    </div>
    <div id="responses-error" class="card-content empty-state" style="display: none;">
        <p>No responses found or token not available. Please ensure you're logged in.</p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Debug initialization
    console.log('Template script initialized');
    $('#debug-script').text('Template script running');
    $('#debug-token').text(sessionStorage.getItem('survey_token') || 'Not found');
});
</script>