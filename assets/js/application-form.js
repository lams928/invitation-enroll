jQuery(document).ready(function($) {
    function loadUserData() {
        $.ajax({
            url: sirecAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_get_user_data',
                nonce: sirecAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#first_name').val(response.data.first_name);
                    $('#last_name').val(response.data.last_name);
                }
            },
            error: function() {
                console.log('Error al cargar datos del usuario');
            }
        });
    }

    loadUserData();

    $('.sirec-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        
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
                    $('#successModal').show();
                    
                    $('#returnHomeBtn').off('click').on('click', function() {
                        window.location.href = '/';
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