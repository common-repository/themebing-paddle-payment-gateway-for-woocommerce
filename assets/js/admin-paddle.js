jQuery(document).ready(function(){

	jQuery('#manualEntry').on('click', function(event) {
		event.preventDefault();
		/* Act on the event */
		jQuery('#woocommerce_paddle_payment_gateway_vendor_id').closest('tr').show();
		jQuery('#woocommerce_paddle_payment_gateway_vendor_auth_code').closest('tr').show();
		jQuery(this).closest('tr').hide();
	});

	jQuery('#woocommerce_paddle_payment_gateway_vendor_id').closest('tr').hide();
	jQuery('#woocommerce_paddle_payment_gateway_vendor_auth_code').closest('tr').hide();

    jQuery('.open_paddle_integration_window').on('click', function(event) {
    	event.preventDefault();
    	/* Act on the event */
    	window.open(integration_popup.url,'integrationwindow','location=no,status=0,scrollbars=0,width=500,height=500');

    	// handle message sent from popup
		jQuery(window).on('message', function(event) {
			jQuery(this).closest('tr').hide();
			var arrayOfData = event.originalEvent.data.split(' ');
			jQuery('#woocommerce_paddle_payment_gateway_vendor_id').val(arrayOfData[0]);
			jQuery('#woocommerce_paddle_payment_gateway_vendor_auth_code').val(arrayOfData[1]);
			jQuery('#manualEntry').click();
		});
    });

});