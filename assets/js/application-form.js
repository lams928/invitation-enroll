jQuery(document).ready(function($) {
    $('.sirec-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        
        // Disable submit button
        submitButton.prop('disabled', true);
        
        $.ajax({
            url: sirecAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_submit_application',
                nonce: sirecAjax.nonce,
                token: $('input[name="token"]').val(),
                first_name: $('#first_name').val(),
                last_name: $('#last_name').val(),
                birth_date: $('#birth_date').val(),
                birth_country: $('#birth_country').val(),
                residence_country: $('#residence_country').val(),
                profession: $('#profession').val(),
                participation_reason: $('#participation_reason').val()
            },
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    $('#successModal').show();
                    
                    // Handle return home button with hardcoded URL
                    $('#returnHomeBtn').off('click').on('click', function() {
                        window.location.href = '/'; // This will redirect to the home page
                    });
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('Error al procesar la solicitud. Por favor, intente nuevamente.');
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });
});