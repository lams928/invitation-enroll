<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Nueva Invitación a Curso</h1>
    
    <div class="sirec-invitation-form">
        <form id="sirec-send-invitation" method="post">
            <?php wp_nonce_field('sirec_invitation_nonce', 'invitation_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="course_id">Seleccionar Curso</label></th>
                    <td>
                        <select name="course_id" id="course_id" required>
                            <option value="">Seleccione un curso...</option>
                            <?php
                            $courses = sirec_get_edwiser_courses();
                            if (!empty($courses)) {
                                foreach($courses as $course) {
                                    printf(
                                        '<option value="%d">%s</option>',
                                        $course->ID,
                                        esc_html($course->post_title)
                                    );
                                }
                            } else {
                                echo '<option value="">No hay cursos disponibles</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3>Invitaciones por Roles</h3></th>
                </tr>
                <tr>
                    <th><label for="selected_roles">Seleccionar Roles</label></th>
                    <td>
                        <?php
                        $roles = get_editable_roles();
                        foreach($roles as $role_key => $role) {
                            if($role_key !== 'administrator') {
                                ?>
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="checkbox" 
                                        name="selected_roles[]" 
                                        value="<?php echo esc_attr($role_key); ?>">
                                    <?php echo esc_html($role['name']); ?>
                                </label>
                                <?php
                            }
                        }
                        ?>
                        <p class="description">Selecciona los roles a los que deseas enviar la invitación</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3>Invitaciones Individuales</h3></th>
                </tr>
                <tr>
                    <th><label for="selected_users">Seleccionar Usuarios</label></th>
                    <td>
                        <?php
                        if (!class_exists('SIREC_Users_List_Table')) {
                            require_once SIREC_PLUGIN_DIR . 'includes/class-users-list-table.php';
                        }
                        
                        try {
                            // Crear instancia de la tabla
                            $users_table = new SIREC_Users_List_Table();
                            $users_table->prepare_items();
                            
                            // Formulario de búsqueda
                            ?>
                            <div class="tablenav top">
                                <div class="alignleft actions">
                                    <input type="search" id="user-search-input" name="s" 
                                        value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>"
                                        placeholder="Buscar usuarios...">
                                    <span class="user-search-spinner spinner"></span>
                                </div>
                                <br class="clear">
                            </div>

                            <?php
                            // Mostrar los filtros de rol
                            echo '<div class="wrap">';
                            echo '<div class="tablenav top">';
                            $users_table->views();
                            echo '</div>';
                            
                            // Mostrar la tabla
                            $users_table->display();
                            echo '</div>';
                            
                        } catch (Exception $e) {
                            echo '<div class="notice notice-error"><p>Error al cargar la tabla de usuarios: ' . esc_html($e->getMessage()) . '</p></div>';
                        }
                        ?>
                    </td>
                </tr>

                <tr>
                    <th><label for="invitation_message">Mensaje Personalizado</label></th>
                    <td>
                        <textarea name="invitation_message" id="invitation_message" rows="5" cols="50"
                            placeholder="Mensaje opcional que se incluirá en la invitación..."></textarea>
                    </td>
                </tr>
            </table>
            
            <div class="submit-button">
                <input type="submit" class="button button-primary" value="Enviar Invitaciones">
                <span class="spinner" style="float:none;"></span>
            </div>
        </form>
        
        <div id="invitation-results" style="margin-top:20px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    //aajx
    let searchTimer;
    const $searchInput = $('#user-search-input');
    const $spinner = $('.user-search-spinner');
    const $usersList = $('.wp-list-table tbody');

    function performSearch() {
        const searchTerm = $searchInput.val();
        $spinner.addClass('is-active');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_search_users',
                nonce: sirecAjax.nonce,
                search: searchTerm,
                role: $('select[name="role"]').val()
            },
            success: function(response) {
                if (response.success) {
                    $usersList.html(response.data.html);
                } else {
                    console.error('Error en la búsqueda:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    }

    $searchInput.on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(performSearch, 500);
    });

    
    $('#select-all-users').on('change', function() {
        $('input[name="selected_users[]"]').prop('checked', $(this).prop('checked'));
    });

    $('#sirec-send-invitation').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $spinner = $form.find('.spinner');
        const $submit = $form.find(':submit');
        const $results = $('#invitation-results');
        
        if(!$('input[name="selected_roles[]"]:checked').length && 
           !$('input[name="selected_users[]"]:checked').length) {
            alert('Por favor, selecciona al menos un rol o usuario.');
            return;
        }
        
        $spinner.addClass('is-active');
        $submit.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_send_invitations',
                nonce: $('#invitation_nonce').val(),
                course_id: $('#course_id').val(),
                message: $('#invitation_message').val(),
                selected_roles: $('input[name="selected_roles[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                selected_users: $('input[name="selected_users[]"]:checked').map(function() {
                    return $(this).val();
                }).get()
            },
            success: function(response) {
                if(response.success) {
                    let resultHtml = '<div class="notice notice-info">';
                    resultHtml += '<p><strong>Notificaciones:</strong><br>';
                    resultHtml += response.data.notification_stats.message + '</p>';
                    resultHtml += '<p><strong>Correos Electrónicos:</strong><br>';
                    resultHtml += response.data.email_stats.message + '</p>';
                    resultHtml += '</div>';
                    
                    $('#invitation-results').html(resultHtml);
                } else {
                    $('#invitation-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $results.html('<div class="notice notice-error"><p>Error al enviar invitaciones</p></div>');
            },
            complete: function() {
                $spinner.removeClass('is-active');
                $submit.prop('disabled', false);
            }
        });
    });
});
</script>