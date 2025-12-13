// archia-admin.js

jQuery(document).ready(function($) {
    const form = $('#archia-manual-generate-form');
    const button = $('#archia-generate-now-btn');
    const progressBarContainer = $('.archia-progress-bar-container');
    const progressBar = $('#archia-progress-bar');
    const resultOutput = $('#archia-result-output');

    // Function to show Toastify notifications
    function showToast(message, type = 'success') {
        let backgroundColor = type === 'success' ? '#00c600' : '#ff3d00';
        let sound = type === 'success' ? archia_data.success_sound : archia_data.error_sound;

        Toastify({
            text: message,
            duration: 8000,
            close: true,
            gravity: "top", // `top` or `bottom`
            position: "right", // `left`, `center` or `right`
            backgroundColor: backgroundColor,
            stopOnFocus: true,
            escapeMarkup: false, // Allow HTML for the renewal link
            callback: function() {
                if (sound) {
                    new Audio(sound).play().catch(e => console.log("Sound failed:", e));
                }
            }
        }).showToast();
    }

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();

        // 1. Setup UI for progress
        button.prop('disabled', true).text('Generating...');
        progressBarContainer.css('display', 'block');
        progressBar.css('width', '10%').text('10%').css('background-color', '#0073aa'); // Reset color
        resultOutput.html('');

        // 2. Perform AJAX request
        $.ajax({
            url: archia_data.ajax_url,
            method: 'POST',
            data: {
                action: 'archia_manual_generate',
                nonce: archia_data.nonce,
            },
            beforeSend: function() {
                // Simulate more progress during server processing
                progressBar.css('width', '50%').text('50%');
            },
            success: function(response) {
                // 3. Handle success or error from server
                if (response.success) {
                    showToast(response.data.message, 'success');
                    progressBar.css('width', '100%').text('100%');
                } else {
                    const errorMsg = response.data.message || 'An unknown error occurred during generation.';
                    showToast(errorMsg, 'error');
                    progressBar.css('width', '100%').css('background-color', '#ff3d00').text('Error');
                }

                // Display result details
                let outputHtml = '<h3>Result</h3><pre>';
                outputHtml += JSON.stringify(response.data.results || response.data, null, 2);
                outputHtml += '</pre>';
                resultOutput.html(outputHtml);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // 4. Handle AJAX failure
                let msg = 'AJAX Error: ' + errorThrown;
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    msg = jqXHR.responseJSON.data.message;
                }
                
                showToast(msg, 'error');
                progressBar.css('width', '100%').css('background-color', '#ff3d00').text('AJAX Error');
                resultOutput.html('<h3>Error</h3><pre>AJAX request failed: ' + textStatus + '</pre>');
            },
            complete: function() {
                // 5. Cleanup UI
                setTimeout(function() {
                    progressBarContainer.slideUp();
                    button.prop('disabled', false).text('Generate Now');
                }, 3000); // Keep full bar visible for 3 seconds
            }
        });
    });
});