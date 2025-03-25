<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class SIREC_Users_List_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'usuario',
            'plural'   => 'usuarios',
            'ajax'     => false
        ]);
    }

    public function prepare_items() {
        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $role = isset($_REQUEST['role']) ? $_REQUEST['role'] : '';
        
        $args = [
            'number' => $per_page,
            'offset' => ($this->get_pagenum() - 1) * $per_page,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'orderby' => isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'display_name',
            'order' => isset($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC'
        ];
        
        if (!empty($role)) {
            $args['role'] = $role;
        }
        
        $users_query = new WP_User_Query($args);
        $this->items = $users_query->get_results();
        
        $total_items = $users_query->get_total();
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'username'     => 'Usuario',
            'name'         => 'Nombre',
            'email'        => 'Correo',
            'role'         => 'Rol',
            'registered'   => 'Fecha de registro'
        ];
    }

    public function get_sortable_columns() {
        return [
            'username'   => ['user_login', true],
            'name'       => ['display_name', true],
            'email'      => ['user_email', true],
            'registered' => ['user_registered', true]
        ];
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="selected_users[]" value="%s" />',
            $item->ID
        );
    }

    protected function column_username($item) {
        return esc_html($item->user_login);
    }

    protected function column_name($item) {
        return esc_html($item->display_name);
    }

    protected function column_email($item) {
        return sprintf('<a href="mailto:%1$s">%1$s</a>', esc_html($item->user_email));
    }

    protected function column_role($item) {
        global $wp_roles;
        $roles = array_map(function($role) use ($wp_roles) {
            return translate_user_role($wp_roles->roles[$role]['name']);
        }, $item->roles);
        return implode(', ', $roles);
    }

    protected function column_registered($item) {
        return mysql2date(get_option('date_format'), $item->user_registered);
    }

    protected function get_views() {
        global $wp_roles;
        
        $views = [];
        $current = isset($_REQUEST['role']) ? $_REQUEST['role'] : 'all';
        
        $all_url = remove_query_arg('role');
        $class = $current === 'all' ? ' class="current"' : '';
        $views['all'] = sprintf(
            '<a href="%s"%s>Todos</a>',
            $all_url,
            $class
        );
        
        foreach ($wp_roles->roles as $role => $details) {
            $count = count(get_users(['role' => $role]));
            if ($count) {
                $url = add_query_arg('role', $role);
                $class = $current === $role ? ' class="current"' : '';
                $views[$role] = sprintf(
                    '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                    $url,
                    $class,
                    translate_user_role($details['name']),
                    $count
                );
            }
        }
        
        return $views;
    }
}