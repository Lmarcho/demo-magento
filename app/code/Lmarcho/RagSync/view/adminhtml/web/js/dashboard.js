/**
 * Lmarcho RagSync Module - Dashboard JavaScript
 */
define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    return function (config, element) {
        var $dashboard = $(element);
        var testConnectionUrl = config.testConnectionUrl;
        var isConfigured = config.isConfigured;

        /**
         * Initialize dashboard
         */
        function init() {
            bindEvents();

            // Auto-test connection on load if configured
            if (isConfigured) {
                testConnection();
            }
        }

        /**
         * Bind click events
         */
        function bindEvents() {
            // Test connection button
            $dashboard.on('click', '.test-connection', function () {
                testConnection();
            });

            // Process queue button
            $dashboard.on('click', '.process-queue', function () {
                var url = $(this).data('url');
                executeAction(url, $t('Processing queue...'), $t('Queue processed successfully!'));
            });

            // Retry failed button
            $dashboard.on('click', '.retry-failed', function () {
                var url = $(this).data('url');
                executeAction(url, $t('Retrying failed items...'), $t('Failed items queued for retry!'));
            });

            // Clear sent button
            $dashboard.on('click', '.clear-sent', function () {
                var url = $(this).data('url');
                if (confirm($t('Are you sure you want to clear all sent items?'))) {
                    executeAction(url, $t('Clearing sent items...'), $t('Sent items cleared!'));
                }
            });

            // Sync entity button
            $dashboard.on('click', '.sync-entity', function () {
                var url = $(this).data('url');
                var type = $(this).data('type');
                var typeName = type.replace('_', ' ');
                executeAction(url, $t('Queuing %1 for sync...').replace('%1', typeName),
                    $t('%1 queued for sync!').replace('%1', typeName));
            });
        }

        /**
         * Test connection to webhook
         */
        function testConnection() {
            var $connectionInfo = $('#connection-info');
            var $statusIndicator = $connectionInfo.find('.status-indicator');
            var $statusText = $connectionInfo.find('.status-text');
            var $latencyRow = $connectionInfo.find('.latency-row');
            var $latencyValue = $connectionInfo.find('.latency-value');

            // Set loading state
            $statusIndicator.removeClass('connected disconnected').addClass('loading');
            $statusText.text($t('Testing connection...'));

            $.ajax({
                url: testConnectionUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                success: function (response) {
                    $statusIndicator.removeClass('loading');

                    if (response.success) {
                        $statusIndicator.addClass('connected');
                        $statusText.text($t('Connected'));

                        if (response.latency) {
                            $latencyRow.show();
                            $latencyValue.text(response.latency + 'ms');
                        }
                    } else {
                        $statusIndicator.addClass('disconnected');
                        $statusText.text(response.message || $t('Connection failed'));
                        $latencyRow.hide();
                    }
                },
                error: function () {
                    $statusIndicator.removeClass('loading').addClass('disconnected');
                    $statusText.text($t('Connection test failed'));
                    $latencyRow.hide();
                }
            });
        }

        /**
         * Execute AJAX action
         */
        function executeAction(url, loadingMessage, successMessage) {
            // Show loading state
            showNotification(loadingMessage, 'info');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                success: function (response) {
                    if (response.success) {
                        showNotification(response.message || successMessage, 'success');

                        // Reload page after short delay to show updated stats
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification(response.message || $t('Action failed'), 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showNotification($t('An error occurred: ') + error, 'error');
                }
            });
        }

        /**
         * Show notification
         */
        function showNotification(message, type) {
            // Remove existing notifications
            $('.ragsync-notification').remove();

            var $notification = $('<div class="ragsync-notification message message-' + type + '">' +
                '<span>' + message + '</span>' +
                '</div>');

            $dashboard.prepend($notification);

            // Auto-hide after 5 seconds for success/info
            if (type !== 'error') {
                setTimeout(function () {
                    $notification.fadeOut(function () {
                        $(this).remove();
                    });
                }, 5000);
            }
        }

        // Initialize
        init();
    };
});
