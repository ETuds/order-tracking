jQuery(document).ready(function ($) {
    
    var adminurl = objects.ajaxurl;
    
    $('#trackorder_form').validate({
        rules: {

            track_order_num: {
                required: true,
            }
        },

        messages: {
            track_order_num: "Please enter your Order ID",
        },
        errorPlacement: function (error, element) {
            element.after(error);
            $('.form-control').each(function () {
                if ($(this).hasClass('error')) {
                    $(this).parent().find('.field-icon-valid').hide();
                    $(this).parent().find('.field-icon-invalid').show();
                }

            });
        },
        highlight: function (element) {
            // add a class "errorClass" to the element
            $(element).addClass('error');
            $(element).removeClass('valid');
            $('.form-control').each(function () {
                if ($(this).hasClass('error')) {
                    $(this).parent().find('.field-icon-valid').hide();
                    $(this).parent().find('.field-icon-invalid').show();
                }

            });
        },
        unhighlight: function (element) {
            // class "errorClass" remove from the element
            $(element).removeClass('error');
            $(element).addClass('valid');
            $('.form-control').each(function () {
                if ($(this).hasClass('valid')) {
                    $(this).parent().find('.field-icon-invalid').hide();
                    $(this).parent().find('.field-icon-valid').hide();
                    $(this).parent().find('label.error').remove();
                    $(this).parent().find('.error').removeClass();
                }

            });
        },
        success: function (label, element) {
            $('.form-control').each(function () {
                if ($(this).hasClass('valid')) {
                    $(this).parent().find('.field-icon-invalid').hide();
                    $(this).parent().find('.field-icon-valid').hide();
                    $(this).parent().find('label.error').remove();
                    $(this).parent().find('.error').removeClass();
                }

            });
        },
        submitHandler: function (form) {
            var data = [];
            data['order_id'] = $('#track_order_num').val();
            data['nonce_security'] = $('#trackorder_security').val();
            trackOrder(data);
        }

    });

    function trackOrder(data) {

        var datarequest = {
            action: 'trackOrderFormAjax',
            order_id: data['order_id'],
            nonce_security: data['nonce_security'],
        }
        
            $.ajax({
                url: adminurl,
                type: 'POST',
                dataType: 'json',
                data: datarequest,
                success: function (response) {
                    console.log(response);
                    if (response.notice == 'failed') {
                        $('#track_order_num').addClass('error');
                        $('.field-icon-invalid').show();
                        $('.field-icon-valid').hide();
                        $('#track_order_num').after('<label for="track_order_num" class="trackorder-error error">'+response.message+'</label>');
                    }
                    if (response.notice == 'success') {
                        renderTracking(data['order_id'])
                    }
                }
            });
    }
    
    function renderTracking(order_id) {
        var datarequest = {
            action: 'renderTracking',
            order_id: order_id
        }

            $.ajax({
                url: adminurl,
                type: 'POST',
                data: datarequest,
                success: function (response) {
                        
                    $('#track-order-form-container').html(response);
                    
                }
            });
        }
});
