/**
 * Admin JavaScript for the OpenRouted plugin.
 *
 * @since      1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // No longer need dropdown toggle as we have a single unified dropdown
        
        // Run scheduled check now button
        $('#run-cron-now').on('click', function() {
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            var $result = $button.parent().find('.cron-result');
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.css('visibility', 'visible');
            $result.html('');
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_run_scheduled_check',
                    nonce: openrouted_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        // Reload page after 2 seconds to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        $button.prop('disabled', false);
                    }
                }
            });
        });
        
        // Refresh cron schedule button
        $('#refresh-cron-schedule').on('click', function() {
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            var $result = $button.parent().find('.refresh-result');
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.css('visibility', 'visible');
            $result.html('');
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_refresh_cron_schedule',
                    nonce: openrouted_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        // Reload page after 2 seconds to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                    $button.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Server error occurred. Please try again.</p></div>');
                    $button.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                },
                complete: function() {
                    $spinner.css('visibility', 'hidden');
                }
            });
        });
        
        // Media Library - Generate Alt Tag
        $(document).on('click', '.generate-alt-tag', function() {
            const $button = $(this);
            const $container = $button.closest('.openrouted-controls');
            const $spinner = $container.find('.spinner');
            const $result = $container.find('.generate-result');
            const imageId = $button.data('id');
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_generate_alt_tag',
                    nonce: openrouted_ajax.nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message with suggested alt tag
                        $result.html(
                            '<div class="openrouted-suggestion">' +
                            '<p><strong>' + 'AI Suggestion:' + '</strong> ' + response.data.alt_text + '</p>' +
                            '<button type="button" class="button apply-alt-tag" data-id="' + response.data.id + '">Apply</button> ' +
                            '<button type="button" class="button delete-alt-tag" data-id="' + response.data.id + '">Reject</button>' +
                            '</div>'
                        );
                        $button.hide();
                    } else {
                        // Show error message
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Server error occurred. Please try again.</p></div>');
                    $button.prop('disabled', false);
                },
                complete: function() {
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Media Library - Apply Alt Tag
        $(document).on('click', '.apply-alt-tag', function() {
            const $button = $(this);
            const $container = $button.closest('.openrouted-controls');
            const $suggestion = $button.closest('.openrouted-suggestion');
            const id = $button.data('id');
            
            // Disable buttons
            $button.prop('disabled', true);
            $suggestion.find('button').prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_apply_alt_tag',
                    nonce: openrouted_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        // Add success message and refresh the page to show updated alt text
                        $suggestion.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        $suggestion.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $suggestion.html('<div class="notice notice-error inline"><p>Server error occurred. Please try again.</p></div>');
                }
            });
        });
        
        // Media Library - Delete Alt Tag
        $(document).on('click', '.delete-alt-tag', function() {
            const $button = $(this);
            const $container = $button.closest('.openrouted-controls');
            const $suggestion = $button.closest('.openrouted-suggestion');
            const id = $button.data('id');
            
            // Disable buttons
            $button.prop('disabled', true);
            $suggestion.find('button').prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_delete_alt_tag',
                    nonce: openrouted_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message and remove suggestion
                        $suggestion.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        setTimeout(function() {
                            $suggestion.fadeOut(function() {
                                $suggestion.remove();
                                $('.generate-alt-tag').show().prop('disabled', false);
                            });
                        }, 1500);
                    } else {
                        // Show error message
                        $suggestion.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $suggestion.html('<div class="notice notice-error inline"><p>Server error occurred. Please try again.</p></div>');
                }
            });
        });
        
        // Tab Navigation
        $('.tab-button').on('click', function() {
            const tab = $(this).data('tab');
            
            // Update active tab button
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Show the corresponding tab content
            $('.tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');
            
            // If on the logs tab, load the activity log
            if (tab === 'logs') {
                loadActivityLog();
                // Setup auto-refresh for logs tab
                setupLogAutoRefresh();
            } else {
                // Clear auto-refresh when leaving logs tab
                clearLogAutoRefresh();
            }
        });
        
        // Dashboard - Apply Alt Tag
        $(document).on('click', '.apply-dashboard-alt-tag', function() {
            const $button = $(this);
            const $row = $button.closest('tr');
            const id = $button.data('id');
            
            // Disable buttons
            $button.prop('disabled', true);
            $row.find('button').prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_apply_alt_tag',
                    nonce: openrouted_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        // Add a success message before removing the row
                        $row.addClass('highlight-success').find('td:last').append(
                            '<div class="notice notice-success inline"><p>Applied successfully!</p></div>'
                        );
                        
                        // Update row to show applied after a brief delay
                        setTimeout(function() {
                            $row.fadeOut(function() {
                                $row.remove();
                                
                                // If no more rows, show empty message
                                if ($('#pending-table-container table tbody tr').length === 0) {
                                    $('#pending-tab').html('<p>No pending alt tags.</p>');
                                }
                                
                                // Update counts
                                updateAltTagCounts();
                                
                                // Refresh the activity log if it's visible
                                if ($('#logs-tab').hasClass('active')) {
                                    loadActivityLog();
                                }
                                
                                // Update applied tab data if it's already loaded
                                if ($('#applied-tab').hasClass('active') || $('#applied-table-container table').length > 0) {
                                    // Reload applied tab data without reloading the page
                                    $.ajax({
                                        url: openrouted_ajax.ajax_url,
                                        type: 'POST',
                                        data: {
                                            action: 'openrouted_get_more_alt_tags',
                                            nonce: openrouted_ajax.nonce,
                                            status: 'applied',
                                            offset: 0
                                        },
                                        success: function(response) {
                                            if (response.success && response.data.alt_tags && response.data.alt_tags.length > 0) {
                                                const $tbody = $('#applied-table-container table tbody');
                                                // Clear existing content and add the new data at the top
                                                if ($tbody.length > 0) {
                                                    // Prepend the new row to the table
                                                    const newItem = response.data.alt_tags[0]; // Assuming the most recent is first
                                                    if (newItem) {
                                                        const newRow = createAltTagRow(newItem, 'applied');
                                                        $tbody.prepend(newRow);
                                                    }
                                                }
                                            }
                                        }
                                    });
                                }
                            });
                        }, 1000);
                    } else {
                        alert(response.data.message);
                        $row.find('button').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Server error occurred. Please try again.');
                    $row.find('button').prop('disabled', false);
                }
            });
        });
        
        // Dashboard - Delete Alt Tag
        $(document).on('click', '.delete-dashboard-alt-tag', function() {
            const $button = $(this);
            const $row = $button.closest('tr');
            const id = $button.data('id');
            
            // Disable buttons
            $button.prop('disabled', true);
            $row.find('button').prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_delete_alt_tag',
                    nonce: openrouted_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        // Remove row
                        $row.fadeOut(function() {
                            $row.remove();
                            
                            // If no more rows, show empty message
                            if ($('#pending-table-container table tbody tr').length === 0) {
                                $('#pending-tab').html('<p>No pending alt tags.</p>');
                            }
                            
                            // Update counts
                            updateAltTagCounts();
                        });
                    } else {
                        alert(response.data.message);
                        $row.find('button').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Server error occurred. Please try again.');
                    $row.find('button').prop('disabled', false);
                }
            });
        });
        
        // Dashboard - Run Bulk Generator
        $('#run-bulk-generator').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();
            const limit = $('#generate-limit').val();
            
            // Disable button and show processing
            $button.prop('disabled', true).text('Processing...');
            
            // Show results area
            $('#generator-results').show();
            $('#generator-message').html('<p>Generating alt tags. This may take a minute...</p>');
            
            // Send AJAX request
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_run_bulk_generator',
                    nonce: openrouted_ajax.nonce,
                    limit: limit
                },
                success: function(response) {
                    if (response.success) {
                        $('#generator-message').html('<p class="success">' + response.data.message + '</p>');
                        
                        // Refresh the page after a moment to show new alt tags
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#generator-message').html('<p class="error">Error: ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $('#generator-message').html('<p class="error">Server error occurred. Please try again.</p>');
                },
                complete: function() {
                    // Re-enable button and restore text
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Load more pending alt tags
        $(document).on('click', '.load-more-pending', function() {
            const $button = $(this);
            const $container = $('#pending-table-container');
            const currentCount = $container.find('tbody tr').length;
            
            $button.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_load_more_alt_tags',
                    nonce: openrouted_ajax.nonce,
                    status: 'pending',
                    offset: currentCount
                },
                success: function(response) {
                    if (response.success && response.data.alt_tags) {
                        if (response.data.alt_tags.length > 0) {
                            // Append new rows
                            const $tbody = $container.find('tbody');
                            
                            response.data.alt_tags.forEach(function(alt_tag) {
                                $tbody.append(createAltTagRow(alt_tag, 'pending'));
                            });
                            
                            // Show load more button if there are more to load
                            if (response.data.has_more) {
                                $button.prop('disabled', false).text('Load More');
                            } else {
                                $button.hide();
                            }
                        } else {
                            $button.hide();
                        }
                    } else {
                        alert('Error loading more alt tags.');
                        $button.prop('disabled', false).text('Load More');
                    }
                },
                error: function() {
                    alert('Server error occurred. Please try again.');
                    $button.prop('disabled', false).text('Load More');
                }
            });
        });
        
        // Load more applied alt tags
        $(document).on('click', '.load-more-applied', function() {
            const $button = $(this);
            const $container = $('#applied-table-container');
            const currentCount = $container.find('tbody tr').length;
            
            $button.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_load_more_alt_tags',
                    nonce: openrouted_ajax.nonce,
                    status: 'applied',
                    offset: currentCount
                },
                success: function(response) {
                    if (response.success && response.data.alt_tags) {
                        if (response.data.alt_tags.length > 0) {
                            // Append new rows
                            const $tbody = $container.find('tbody');
                            
                            response.data.alt_tags.forEach(function(alt_tag) {
                                $tbody.append(createAltTagRow(alt_tag, 'applied'));
                            });
                            
                            // Show load more button if there are more to load
                            if (response.data.has_more) {
                                $button.prop('disabled', false).text('Load More');
                            } else {
                                $button.hide();
                            }
                        } else {
                            $button.hide();
                        }
                    } else {
                        alert('Error loading more alt tags.');
                        $button.prop('disabled', false).text('Load More');
                    }
                },
                error: function() {
                    alert('Server error occurred. Please try again.');
                    $button.prop('disabled', false).text('Load More');
                }
            });
        });
        
        // View alt tag details
        $(document).on('click', '.view-details', function() {
            const id = $(this).data('id');
            viewAltTagDetails(id);
        });
        
        // Close modal
        $('.alt-tag-modal-close').on('click', function() {
            $('#alt-tag-details-modal').hide();
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('alt-tag-modal')) {
                $('.alt-tag-modal').hide();
            }
        });
        
        // Tab navigation within modal
        $(document).on('click', '.alt-tag-details-tabs .tab-button', function() {
            const tab = $(this).data('tab');
            
            // Update active tab button
            $('.alt-tag-details-tabs .tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Show the corresponding tab content
            $('.alt-tag-details-tabs .tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');
        });
        
        // Refresh activity log
        $(document).on('click', '.refresh-log', function() {
            loadActivityLog();
        });
        
        // Apply All Pending Alt Tags
        $(document).on('click', '#apply-all-pending', function() {
            const $button = $(this);
            const $rows = $('#pending-table-container tbody tr');
            
            if ($rows.length === 0) {
                return;
            }
            
            // Confirm with user
            if (!confirm('Are you sure you want to apply ALL pending alt tags? This will update ' + $rows.length + ' images.')) {
                return;
            }
            
            // Disable the button
            $button.prop('disabled', true).text('Applying all...');
            
            // Get all IDs
            const ids = [];
            $rows.each(function() {
                ids.push($(this).data('id'));
            });
            
            // Keep track of progress
            let processed = 0;
            let success = 0;
            
            // Process each alt tag one by one
            function processNext() {
                if (processed >= ids.length) {
                    // All done
                    updateAltTagCounts();
                    $button.prop('disabled', false).text('Apply All Pending Alt Tags');
                    
                    if (success > 0) {
                        alert('Successfully applied ' + success + ' alt tags out of ' + ids.length);
                        
                        // Reload the page to show updated data
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        alert('Failed to apply any alt tags.');
                    }
                    return;
                }
                
                const id = ids[processed];
                const $row = $('tr[data-id="' + id + '"]');
                
                $.ajax({
                    url: openrouted_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'openrouted_apply_alt_tag',
                        nonce: openrouted_ajax.nonce,
                        id: id
                    },
                    success: function(response) {
                        processed++;
                        if (response.success) {
                            success++;
                            $row.fadeOut(function() {
                                $row.remove();
                            });
                        }
                        // Update progress
                        $button.text('Applying all... (' + processed + '/' + ids.length + ')');
                        // Process the next item
                        processNext();
                    },
                    error: function() {
                        processed++;
                        // Process the next item even on error
                        processNext();
                    }
                });
            }
            
            // Start processing
            processNext();
        });
        
        // Auto-refresh timer for activity log
        let logRefreshTimer = null;
        
        function setupLogAutoRefresh() {
            // Clear any existing timer
            clearLogAutoRefresh();
            
            // Set up a new timer that refreshes every 10 seconds
            logRefreshTimer = setInterval(function() {
                if ($('#logs-tab').hasClass('active')) {
                    loadActivityLog();
                } else {
                    // If we're not on the logs tab anymore, clear the timer
                    clearLogAutoRefresh();
                }
            }, 10000); // 10 seconds
        }
        
        function clearLogAutoRefresh() {
            if (logRefreshTimer !== null) {
                clearInterval(logRefreshTimer);
                logRefreshTimer = null;
            }
        }
        
        // Helper functions
        
        function updateAltTagCounts() {
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_get_counts',
                    nonce: openrouted_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.counts) {
                        // Update the counts in the tabs and stats
                        const counts = response.data.counts;
                        
                        // Update stat counts
                        $('.stat-count').each(function() {
                            const $stat = $(this);
                            if ($stat.closest('.stat-item').find('.stat-label').text().includes('Pending')) {
                                $stat.text(counts.pending || 0);
                            } else if ($stat.closest('.stat-item').find('.stat-label').text().includes('Applied')) {
                                $stat.text(counts.applied || 0);
                            }
                        });
                        
                        // Update tab counts
                        $('.tab-button[data-tab="pending"] .count').text('(' + (counts.pending || 0) + ')');
                        $('.tab-button[data-tab="applied"] .count').text('(' + (counts.applied || 0) + ')');
                    }
                }
            });
        }
        
        function loadActivityLog() {
            const $container = $('#activity-log-container');
            
            // Add loading indicator while preserving the current content (if any)
            if ($container.find('table').length === 0) {
                $container.html('<p>Loading activity log...</p>');
            } else {
                $container.append('<div class="loading-overlay"><p>Refreshing...</p></div>');
            }
            
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_get_activity_log',
                    nonce: openrouted_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.log) {
                        if (response.data.log.length > 0) {
                            let html = '<div class="activity-log-header">';
                            html += '<h3>' + 'Activity Log' + '</h3>';
                            html += '<p class="activity-log-info">' + 'Auto-refreshes every 10 seconds while this tab is active' + '</p>';
                            html += '<div class="last-updated">Last updated: ' + new Date().toLocaleTimeString() + '</div>';
                            html += '</div>';
                            
                            html += '<table class="widefat striped activity-log-table">';
                            html += '<thead><tr><th>Time</th><th>Action</th><th>Details</th></tr></thead><tbody>';
                            
                            response.data.log.forEach(function(entry) {
                                // Determine CSS class based on action type
                                let actionClass = '';
                                if (entry.action === 'Generated') {
                                    actionClass = 'log-generated';
                                } else if (entry.action === 'Applied') {
                                    actionClass = 'log-applied';
                                } else if (entry.action === 'Rejected') {
                                    actionClass = 'log-rejected';
                                }
                                
                                html += '<tr class="' + actionClass + '">';
                                html += '<td>' + entry.time + '</td>';
                                html += '<td><span class="log-action-' + entry.action.toLowerCase() + '">' + entry.action + '</span></td>';
                                html += '<td>' + entry.details + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                            
                            // Add load more button if there are more than 50 entries
                            if (response.data.log.length >= 50) {
                                html += '<p class="openrouted-dashboard-action">';
                                html += '<button class="button load-more-logs">Load More</button>';
                                html += '</p>';
                            }
                            
                            $container.html(html);
                        } else {
                            $container.html('<p>No activity recorded yet.</p>');
                        }
                    } else {
                        // If error but we have existing content, keep it and show error in a non-destructive way
                        if ($container.find('table').length > 0) {
                            $container.find('.loading-overlay').remove();
                            $container.prepend('<div class="notice notice-error inline"><p>Error refreshing activity log.</p></div>');
                            setTimeout(function() {
                                $container.find('.notice').fadeOut();
                            }, 3000);
                        } else {
                            $container.html('<p>Error loading activity log.</p>');
                        }
                    }
                },
                error: function() {
                    // If error but we have existing content, keep it and show error in a non-destructive way
                    if ($container.find('table').length > 0) {
                        $container.find('.loading-overlay').remove();
                        $container.prepend('<div class="notice notice-error inline"><p>Server error occurred while refreshing activity log.</p></div>');
                        setTimeout(function() {
                            $container.find('.notice').fadeOut();
                        }, 3000);
                    } else {
                        $container.html('<p>Server error occurred while loading activity log.</p>');
                    }
                }
            });
        }
        
        function viewAltTagDetails(id) {
            // Show the modal and loading state
            $('#alt-tag-details-modal').show();
            $('#modal-alt-text').html('Loading...');
            
            // Reset tabs
            $('.alt-tag-details-tabs .tab-button[data-tab="request"]').click();
            
            $.ajax({
                url: openrouted_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'openrouted_get_alt_tag_details',
                    nonce: openrouted_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success && response.data.details) {
                        const details = response.data.details;
                        
                        // Fill in the details
                        $('#modal-alt-text').text(details.alt_text);
                        
                        // Set image
                        if (details.image_url) {
                            $('#modal-image').html('<img src="' + details.image_url + '" alt="">');
                        } else {
                            $('#modal-image').html('<p>Image not available</p>');
                        }
                        
                        // Set model
                        $('#modal-model').text(details.model || 'Unknown');
                        
                        // Set request/response
                        try {
                            const requestData = JSON.parse(details.initial_payload || '{}');
                            $('#modal-request pre').text(JSON.stringify(requestData, null, 2));
                        } catch (e) {
                            $('#modal-request pre').text(details.initial_payload || 'No request data available');
                        }
                        
                        try {
                            const responseData = JSON.parse(details.response || '{}');
                            $('#modal-response pre').text(JSON.stringify(responseData, null, 2));
                        } catch (e) {
                            $('#modal-response pre').text(details.response || 'No response data available');
                        }
                        
                        // Set duration
                        $('#modal-duration').text((details.duration || 0) + ' seconds');
                        
                        // Set status
                        let statusText = details.status;
                        if (details.status === 'applied' && details.applied_timestamp) {
                            statusText += ' on ' + details.applied_timestamp;
                        }
                        $('#modal-status').text(statusText);
                    } else {
                        alert('Error loading alt tag details.');
                    }
                },
                error: function() {
                    alert('Server error occurred while loading alt tag details.');
                }
            });
        }
        
        function createAltTagRow(alt_tag, status) {
            let row = '<tr data-id="' + alt_tag.id + '">';
            
            // Image column
            row += '<td>';
            if (alt_tag.thumb_url) {
                row += '<a href="' + alt_tag.edit_url + '" target="_blank">';
                row += '<img src="' + alt_tag.thumb_url + '" width="60" height="60" alt="">';
                row += '</a>';
            }
            row += '</td>';
            
            // Alt text column
            row += '<td>' + alt_tag.alt_text + '</td>';
            
            // Generated timestamp column
            row += '<td>' + alt_tag.timestamp + '</td>';
            
            // Applied timestamp column (only for applied status)
            if (status === 'applied') {
                row += '<td>' + (alt_tag.applied_timestamp || '') + '</td>';
            }
            
            // Model column
            row += '<td>' + (alt_tag.model || '') + '</td>';
            
            // Actions column
            row += '<td>';
            if (status === 'pending') {
                row += '<button class="button button-primary apply-dashboard-alt-tag" data-id="' + alt_tag.id + '">Apply</button> ';
                row += '<button class="button delete-dashboard-alt-tag" data-id="' + alt_tag.id + '">Reject</button> ';
            }
            row += '<button class="button view-details" data-id="' + alt_tag.id + '">Details</button>';
            row += '</td>';
            
            row += '</tr>';
            return row;
        }
    });
    
})(jQuery);