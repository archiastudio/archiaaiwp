// archia-admin.js

jQuery(document).ready(function($) {
    const form = $('#archia-manual-generate-form');
    const button = $('#archia-generate-now-btn');
    const progressBarContainer = $('.archia-progress-bar-container');
    const progressBar = $('#archia-progress-bar');
    const progressText = $('#archia-progress-text'); // **New element for progress text**
    const resultOutput = $('#archia-result-output');

    let progressSimulationInterval; // To hold the interval ID for cleanup

    // Function to show Toastify notifications (unchanged)
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
            escapeMarkup: false,
            callback: function() {
                if (sound) {
                    new Audio(sound).play().catch(e => console.log("Sound failed:", e));
                }
            }
        }).showToast();
    }

    // Function to update the progress bar and text
    function updateProgress(percent, text) {
        progressBar.css('width', percent + '%').text(percent + '%');
        progressText.text(text); // Update the new text element
    }

    // Function to simulate the fake progress steps
    function startProgressSimulation() {
        const steps = [
            { percent: 5, text: 'Connecting to API...' },
            { percent: 10, text: 'Getting title...' },
            { percent: 20, text: 'Generating image...' },
            { percent: 40, text: 'Image uploaded...' },
            { percent: 60, text: 'Writing content...' },
            { percent: 80, text: 'Humanizing content...' },
        ];
        
        let stepIndex = 0;
        
        // Use an interval to simulate progress over time
        progressSimulationInterval = setInterval(() => {
            if (stepIndex < steps.length) {
                const step = steps[stepIndex];
                updateProgress(step.percent, step.text);
                stepIndex++;
            } else {
                // Stop the simulation once all steps are done,
                // but keep the progress bar running if the AJAX is still busy.
                // It will be reset in the AJAX success/error/complete handlers.
                clearInterval(progressSimulationInterval);
            }
        }, 800); // Wait 800ms between each step
    }

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();

        // 1. Setup UI for progress
        button.prop('disabled', true).text('Generating...');
        progressBarContainer.slideDown(); // Use slideDown for better effect
        progressBar.css('width', '0%').text('0%').css('background-color', '#0073aa'); // Reset color and width
        progressText.text('Starting process...'); // Initial text
        resultOutput.html('');
        
        // Start the fake progress simulation immediately
        startProgressSimulation();

        // 2. Perform AJAX request
        $.ajax({
            url: archia_data.ajax_url,
            method: 'POST',
            data: {
                action: 'archia_manual_generate',
                nonce: archia_data.nonce,
            },
            // beforeSend is NOT used for simple percentage updates anymore, 
            // as the simulation is handling the progress visualization.
            
            success: function(response) {
                // Stop the progress simulation interval in case it's still running
                clearInterval(progressSimulationInterval); 

                // 3. Handle success or error from server
                if (response.success) {
                    showToast(response.data.message, 'success');
                    updateProgress(100, 'Generation Complete!'); // Final successful update
                    progressBar.css('background-color', '#00c600'); // Green for success
                } else {
                    const errorMsg = response.data.message || 'An unknown error occurred during generation.';
                    showToast(errorMsg, 'error');
                    updateProgress(100, 'Error Occurred');
                    progressBar.css('background-color', '#ff3d00'); // Red for error
                }

                // Display result details
                let outputHtml = '<h3>Result</h3><pre>';
                outputHtml += JSON.stringify(response.data.results || response.data, null, 2);
                outputHtml += '</pre>';
                resultOutput.html(outputHtml);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Stop the progress simulation interval
                clearInterval(progressSimulationInterval); 

                // 4. Handle AJAX failure
                let msg = 'AJAX Error: ' + errorThrown;
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    msg = jqXHR.responseJSON.data.message;
                }
                
                showToast(msg, 'error');
                updateProgress(100, 'AJAX Request Failed');
                progressBar.css('background-color', '#ff3d00');
                resultOutput.html('<h3>Error</h3><pre>AJAX request failed: ' + textStatus + '</pre>');
            },
            complete: function() {
                // 5. Cleanup UI
                setTimeout(function() {
                    progressBarContainer.slideUp();
                    button.prop('disabled', false).text('Generate Now');
                    // Ensure the interval is cleared, though it should be cleared in success/error
                    clearInterval(progressSimulationInterval); 
                }, 3000); // Keep full bar visible for 3 seconds
            }
        });
    });
});