define([
    'jquery',
    'Magento_Ui/js/modal/prompt',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, prompt, alert) {
    'use strict';

    return function (config, element) {
        var $button = $(element),
            $result = $('#' + config.resultId);

        $button.on('click', function () {
            var timestamp = new Date().toISOString().replace('T', ' ').replace(/\..+$/, ' UTC');

            prompt({
                title: $.mage.__('Generate Commerce MCP Access Token'),
                content: $.mage.__('Enter a unique client name. The token will be shown only once.'),
                value: config.defaultName + ' ' + timestamp,
                validation: true,
                validationRules: ['required-entry', 'validate-no-html-tags'],
                attributesField: {
                    name: 'client_name',
                    maxlength: '128',
                    'data-validate': '{required:true, \"validate-no-html-tags\":true}'
                },
                actions: {
                    confirm: function (name) {
                        if (!name) {
                            return;
                        }

                        generateToken(name);
                    }
                }
            });
        });

        function generateToken(name) {
            $result.empty();
            $button.prop('disabled', true);

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                showLoader: true,
                data: {
                    name: name,
                    form_key: window.FORM_KEY
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    alert({
                        content: response && response.message ?
                            response.message :
                            $.mage.__('Unable to generate an access token.')
                    });
                    return;
                }

                renderToken(response);
            }).fail(function () {
                alert({
                    content: $.mage.__('Unable to generate an access token. Please refresh the page and try again.')
                });
            }).always(function () {
                $button.prop('disabled', false);
            });
        }

        function renderToken(response) {
            var $message = $('<div/>').addClass('message message-success success'),
                $content = $('<div/>'),
                $token = $('<textarea/>', {
                    readonly: 'readonly',
                    rows: 3,
                    css: {
                        width: '100%',
                        fontFamily: 'monospace',
                        marginTop: '8px'
                    }
                }).val(response.token);

            $('<p/>').text(response.message).appendTo($content);
            $('<p/>').text($.mage.__('Client: ') + response.client_name).appendTo($content);
            $token.appendTo($content);
            $('<p/>')
                .addClass('note')
                .text($.mage.__('Copy this token now. Magento stores only its hash.'))
                .appendTo($content);
            $content.appendTo($message);
            $result.empty().append($message);
            $token.trigger('focus').trigger('select');
        }
    };
});
