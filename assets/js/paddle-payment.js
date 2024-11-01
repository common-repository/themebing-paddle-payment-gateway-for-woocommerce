jQuery(document).ready(function() {

    jQuery('form.woocommerce-checkout').on('click', ':submit', function(event) {
        if (jQuery('#payment_method_paddle_payment_gateway').is(':checked')) {

            event.preventDefault();

            Paddle.Setup({
                vendor: parseInt(wcAjaxObj.vendor_id)
            });

            /* Act on the event */
            Paddle.Spinner.show();
            jQuery.ajax({
                dataType: "json",
                method: "POST",
                // hit "ajax_process_checkout()"
                url: wcAjaxObj.process_checkout,
                data: jQuery('form.woocommerce-checkout').serializeArray(),
                success: function(response) {
                    // WC will send the error contents in a normal request
                    if (response.result == "success") {
                        Paddle.Checkout.open({
                            email: response.email,
                            country: response.country,
                            override: response.checkout_url
                        });
                    } else {
                        if (response.reload === 'true') {
                            window.location.reload();
                            return;
                        }
                        // Remove old errors
                        jQuery('.woocommerce-error, .woocommerce-message').remove();
                        // Add new errors
                        if (response.messages) {
                            jQuery('form.woocommerce-checkout').prepend(response.messages);
                        }

                        // Cancel processing
                        jQuery('form.woocommerce-checkout').removeClass('processing').unblock();

                        // Lose focus for all fields
                        jQuery('form.woocommerce-checkout').find('.input-text, select').blur();
                        Paddle.Spinner.hide();
                    }
                },
                error: function(jqxhr, status) {
                    // We got a 500 or something if we hit here. Shouldn't normally happen
                    alert("We were unable to process your order, please try again in a few minutes.");
                }
            });
        }
    });
});