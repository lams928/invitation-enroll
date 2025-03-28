<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_sirec_send_invitations', 'sirec_handle_send_invitations');

function sirec_handle_send_invitations() {
    check_ajax_referer('sirec_invitation_nonce', 'nonce');
    
    error_log('Iniciando proceso de envío de invitaciones');
    
    if (!current_user_can('edit_iiiccab')) {
        wp_send_json_error('No tienes permisos para enviar invitaciones.');
    }
    
    $course_id = intval($_POST['course_id']);
    $custom_message = sanitize_textarea_field($_POST['message']);
    $selected_roles = isset($_POST['selected_roles']) ? (array)$_POST['selected_roles'] : [];
    $selected_users = isset($_POST['selected_users']) ? array_map('intval', (array)$_POST['selected_users']) : [];
    
    if(empty($selected_roles) && empty($selected_users)) {
        wp_send_json_error('Debes seleccionar al menos un rol o usuario.');
    }
    
    $users = [];
    foreach($selected_roles as $role) {
        $role_users = get_users(['role' => $role]);
        $users = array_merge($users, $role_users);
    }
    
    if(!empty($selected_users)) {
        foreach($selected_users as $user_id) {
            $user = get_user_by('id', $user_id);
            if($user) {
                $users[] = $user;
            }
        }
    }
    
    $users = array_unique($users, SORT_REGULAR);
    
    $notification_sent_count = 0;
    $email_sent_count = 0;
    $notification_errors = [];
    $email_errors = [];
    
    foreach($users as $user) {
        $notification_sent = sirec_send_sirec_notification($user->ID, $course_id);
        if($notification_sent) {
            $notification_sent_count++;
        } else {
            $notification_errors[] = "Error enviando notificación a " . $user->user_email;
        }
        
        $email_sent = sirec_send_invitation_email($user, $course_id, $custom_message);
        if($email_sent) {
            $email_sent_count++;
        } else {
            $email_errors[] = "Error enviando email a " . $user->user_email;
        }
    }
    
    $response = [
        'success' => ($notification_sent_count > 0 || $email_sent_count > 0),
        'notification_stats' => [
            'sent_count' => $notification_sent_count,
            'total_attempts' => count($users),
            'errors' => $notification_errors,
            'message' => sprintf(
                'Notificaciones: %d enviadas exitosamente de %d intentos. %s',
                $notification_sent_count,
                count($users),
                !empty($notification_errors) ? 'Errores: ' . implode(', ', $notification_errors) : ''
            )
        ],
        'email_stats' => [
            'sent_count' => $email_sent_count,
            'total_attempts' => count($users),
            'errors' => $email_errors,
            'message' => sprintf(
                'Correos electrónicos: %d enviados exitosamente de %d intentos. %s',
                $email_sent_count,
                count($users),
                !empty($email_errors) ? 'Errores: ' . implode(', ', $email_errors) : ''
            )
        ]
    ];
    
    wp_send_json_success($response);
}

function sirec_send_invitation_email($user, $course_id, $custom_message) {
    $token = wp_generate_password(32, false);
    
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'sirec_invitation_tokens',
        array(
            'token' => $token,
            'user_id' => $user->ID,
            'course_id' => $course_id,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'used' => 0
        )
    );

    $course = get_post($course_id);
    $subject = sprintf('Invitación al curso: %s', $course->post_title);
    
    $form_url = home_url('/invitacion-curso/?token=' . $token);
    
    $message = sprintf(
        'Hola %s,<br><br>'.
        'Has sido invitado/a a participar en el curso "%s".<br><br>',
        $user->display_name,
        $course->post_title
    );
    
    if(!empty($custom_message)) {
        $message .= $custom_message . '<br><br>';
    }
    
    $message .= sprintf(
        'Para inscribirte, por favor completa el formulario de solicitud usando este enlace único:<br>'.
        '<a href="%s">Completar formulario de solicitud</a><br><br>'.
        'Este enlace es personal y expirará en 7 días.<br><br>'.
        'Saludos cordiales,<br>'.
        'Equipo SIREC',
        $form_url
    );
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

function sirec_send_sirec_notification($user_id, $course_id) {
    if (!function_exists('bp_is_active') || !bp_is_active('notifications')) {
        error_log('BuddyBoss notifications no está disponible o activo');
        return false;
    }

    global $wpdb;
    $token = $wpdb->get_var($wpdb->prepare(
        "SELECT token FROM {$wpdb->prefix}sirec_invitation_tokens 
        WHERE user_id = %d AND course_id = %d AND used = 0 
        ORDER BY created_at DESC LIMIT 1",
        $user_id,
        $course_id
    ));

    $notification_id = bp_notifications_add_notification(array(
        'user_id'           => $user_id,
        'item_id'           => $course_id,
        'secondary_item_id' => get_current_user_id(),
        'component_name'    => 'sirec_courses',
        'component_action'  => 'new_course_invitation',
        'date_notified'     => bp_core_current_time(),
        'is_new'           => 1,
    ));

    if (!$notification_id) {
        error_log('Error al crear la notificación en BuddyBoss para el usuario ' . $user_id);
        return false;
    }

    if (bp_is_active('activity')) {
        $course_title = get_the_title($course_id);
        $form_url = home_url('/invitacion-curso/?token=' . $token);
        
        bp_activity_add(array(
            'user_id'      => $user_id,
            'component'    => 'sirec_courses',
            'type'         => 'new_course_invitation',
            'primary_link' => $form_url, 
            'item_id'      => $course_id,
            'action'       => sprintf(
                __('Has recibido una invitación para el curso: <a href="%s">%s</a>', 'sirec'),
                esc_url($form_url),
                esc_html($course_title)
            ),
            'hide_sitewide' => false
        ));
    }

    wp_cache_delete($user_id, 'bp_notifications_unread_count');
    
    do_action('sirec_after_course_invitation_notification', $user_id, $course_id, $notification_id);

    return true;
}