<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'sirec_add_admin_menu');
require_once SIREC_PLUGIN_DIR . 'includes/class-users-list-table.php';

add_action('wp_ajax_sirec_search_users', 'sirec_handle_users_search');

function sirec_handle_users_search() {
    check_ajax_referer('sirec_invitation_nonce', 'nonce');
    
    if (!current_user_can('edit_iiiccab')) {
        wp_send_json_error('No tienes permisos para realizar esta acción.');
    }
    
    $users_table = new SIREC_Users_List_Table();
    $users_table->prepare_items();
    
    ob_start();
    $users_table->display();
    $table_html = ob_get_clean();
    
    wp_send_json_success($table_html);
}


function sirec_add_admin_menu() {
    add_menu_page(
        'Gestión de Solicitudes', 
        'Solicitudes SIREC', 
        'edit_iiiccab', 
        'sirec-applications', 
        'sirec_applications_page',
        'dashicons-clipboard',
        30 
    );

    add_submenu_page(
        'sirec-applications',
        'Nueva Invitación',
        'Nueva Invitación',
        'edit_iiiccab',
        'sirec-new-invitation',
        'sirec_new_invitation_page'
    );
}

function sirec_applications_page() {
    if (!current_user_can('edit_iiiccab')) {
        wp_die(__('No tienes permiso para acceder a esta página.'));
    }
    
    include SIREC_PLUGIN_DIR . 'templates/applications-list.php';
}

function sirec_new_invitation_page() {
    if (!current_user_can('edit_iiiccab')) {
        wp_die(__('No tienes permiso para acceder a esta página.'));
    }
    
    include SIREC_PLUGIN_DIR . 'templates/new-invitation.php';
}