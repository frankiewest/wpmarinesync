jQuery(document).ready(function($) {
    const $runFeedButton = $('#run-feed');
    const $feedStatus = $('.marinesync-feed-status');
    const $statusList = $('.marinesync-status-list');
    const $lastRunTime = $('#last-run-time');
    const $nextRunTime = $('#next-run-time');
    const $totalBoats = $('#total-boats');
    const $lastUpdateTime = $('#last-update-time');
    
    // Add export boats button handler
    const $exportBoatsButton = $('#export-boats');
    const $exportMessage = $('#export-message');

    // Function to update the status display
    function updateStatusDisplay(data) {
        if (data.success) {
            $feedStatus.removeClass('notice-error').addClass('notice-success');
            $feedStatus.html(data.message);
            
            // Update status list
            if (data.data) {
                $lastRunTime.text(data.data.last_run || 'Never');
                $nextRunTime.text(data.data.next_run || 'Not scheduled');
                $totalBoats.text(data.data.total_boats || '0');
                $lastUpdateTime.text(data.data.last_update || 'Never');
            }
        } else {
            $feedStatus.removeClass('notice-success').addClass('notice-error');
            $feedStatus.html(data.message);
        }
    }

    // Function to check feed status
    function checkFeedStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'marinesync_check_feed_status',
                nonce: marinesyncAdmin.nonce
            },
            success: function(response) {
                updateStatusDisplay(response);
            },
            error: function(xhr, status, error) {
                $feedStatus.removeClass('notice-success').addClass('notice-error');
                $feedStatus.html('Error checking feed status. Please try again. Error: (' + error + '). XHR: (' + xhr + '). Status: (' + status + ')');
            }
        });
    }

    // Handle manual feed run
    $runFeedButton.on('click', function(e) {
        e.preventDefault();
        
        if ($(this).is(':disabled')) {
            return;
        }

        $(this).prop('disabled', true).addClass('marinesync-loading');
        $feedStatus.removeClass('notice-success notice-error').addClass('notice-info');
        $feedStatus.html('Running feed...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'marinesync_run_feed',
                nonce: marinesyncAdmin.nonce
            },
            success: function(response) {
                updateStatusDisplay(response);
            },
            error: function(xhr, status, error) {
                $feedStatus.removeClass('notice-success').addClass('notice-error');
                $feedStatus.html('Error checking feed status. Please try again. Error: (' + error + '). XHR: (' + xhr + '). Status: (' + status + ')');
            },
            complete: function() {
                $runFeedButton.prop('disabled', false).removeClass('marinesync-loading');
            }
        });
    });
    
    // Handle export boats button
    if ($exportBoatsButton.length > 0) {
        $exportBoatsButton.on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            
            // Disable button and show loading message
            $button.prop('disabled', true);
            $exportMessage.html('<div class="notice notice-info"><p>Generating export file, please wait...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'marinesync_export_boats',
                    nonce: marinesyncAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $exportMessage.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        
                        // Create download link
                        if(response.data.url) {
                            $exportMessage.append('<p><a href="' + response.data.url + '" class="button" target="_blank">Download Export File</a></p>');
                        }
                    } else {
                        $exportMessage.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $exportMessage.html('<div class="notice notice-error"><p>Export failed. Please try again.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    // Check feed status on page load
    checkFeedStatus();

    // Refresh status every 30 seconds
    setInterval(checkFeedStatus, 30000);

    // Handle form submission
    $('.marinesync-admin form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        
        $submitButton.prop('disabled', true).addClass('marinesync-loading');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'marinesync_save_settings',
                nonce: marinesyncAdmin.nonce,
                settings: $form.serialize()
            },
            success: function(response) {
                if (response.success) {
                    $form.before('<div class="notice notice-success"><p>Settings saved successfully.</p></div>');
                    setTimeout(function() {
                        $('.notice-success').fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    $form.before('<div class="notice notice-error"><p>' + response.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $form.before('<div class="notice notice-error"><p>Error saving settings. Please try again.</p></div> Error: (' + error + '). XHR: (' + xhr + '). Status: (' + status + ')');
            },
            complete: function() {
                $submitButton.prop('disabled', false).removeClass('marinesync-loading');
            }
        });
    });
}); 