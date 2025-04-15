<?php
/*
Plugin Name: Tables Codermaster
Description: Un plugin para gestionar tablas y archivos asociados.
Version: 1.8
Author: Leonardo Alexander Peñaranda Angarita
*/

// Crear tablas en la base de datos
function tables_codermaster_create_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    // Tabla para las tablas
    $tables_table = $wpdb->prefix . 'tables_codermaster';
    $sql_tables = "CREATE TABLE $tables_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        table_name VARCHAR(255) NOT NULL,
        shortcode VARCHAR(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    // Tabla para los archivos asociados a cada tabla
    $files_table = $wpdb->prefix . 'tables_codermaster_files';
    $sql_files = "CREATE TABLE $files_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        table_id mediumint(9) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        date_file DATE NOT NULL,
        file_url TEXT NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_tables);
    dbDelta($sql_files);
}
register_activation_hook(__FILE__, 'tables_codermaster_create_db');

// Agregar estilos al admin y frontend
function tables_codermaster_enqueue_styles() {
    wp_enqueue_style('tables-codermaster-styles', plugins_url('styles.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'tables_codermaster_enqueue_styles');
add_action('wp_enqueue_scripts', 'tables_codermaster_enqueue_styles');

// Crear el menú en el panel de administración
function tables_codermaster_admin_menu() {
    add_menu_page(
        'Tables Codermaster',
        'Tables Codermaster',
        'manage_options',
        'tables-codermaster',
        'tables_codermaster_admin_page',
        'dashicons-media-spreadsheet',
        25
    );
    add_submenu_page(
        null,
        'Gestionar Archivos',
        'Gestionar Archivos',
        'manage_options',
        'tables-codermaster-files',
        'tables_codermaster_manage_files_page'
    );
}
add_action('admin_menu', 'tables_codermaster_admin_menu');

// Página de administración principal
function tables_codermaster_admin_page() {
    global $wpdb;
    $tables_table = $wpdb->prefix . 'tables_codermaster';

    // Agregar una nueva tabla
    if (isset($_POST['add_table'])) {
        $table_name = sanitize_text_field($_POST['table_name']);
        $id = $id + 1;
        $shortcode = '[tables_codermaster id="' .$id . '"]';
        $wpdb->insert(
            $tables_table,
            [
                'table_name' => $table_name,
                'shortcode' => $shortcode
            ]
        );
        echo '<div class="notice notice-success"><p>Tabla agregada con éxito.</p></div>';
    }

    // Obtener todas las tablas
    $tables = $wpdb->get_results("SELECT * FROM $tables_table");

    echo '<div class="wrap">';
    echo '<h1>Tables Codermaster</h1>';

    // Formulario para agregar una nueva tabla
    echo '<form method="post" class="tables-form">';
    echo '<h2>Agregar Nueva Tabla</h2>';
    echo '<label for="table_name">Nombre de la Tabla:</label>';
    echo '<input type="text" name="table_name" id="table_name" required>';
    echo '<button type="submit" name="add_table" class="button button-primary">Agregar Tabla</button>';
    echo '</form>';

    // Mostrar tablas existentes
    echo '<h2>Tablas Existentes</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Nombre de la Tabla</th><th>Shortcode</th><th>Acciones</th></tr></thead>';
    echo '<tbody>';
    foreach ($tables as $table) {
        echo '<tr>';
        echo '<td>' . esc_html($table->id) . '</td>';
        echo '<td>' . esc_html($table->table_name) . '</td>';
        echo '<td><code>' . esc_html($table->shortcode) . '</code></td>';
        echo '<td><a href="' . admin_url('admin.php?page=tables-codermaster-files&table_id=' . $table->id) . '" class="button">Gestionar Archivos</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

// Subpágina para gestionar archivos de una tabla específica
function tables_codermaster_manage_files_page() {
    global $wpdb;
    $files_table = $wpdb->prefix . 'tables_codermaster_files';
    $table_id = intval($_GET['table_id']);

    // Insertar un nuevo archivo
    if (isset($_POST['add_file'])) {
        $file_name = sanitize_text_field($_POST['file_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $date_file = esc_url_raw($_POST['date_file']);
        $file_url = esc_url_raw($_POST['file_url']);
        $wpdb->insert(
            $files_table,
            [
                'table_id' => $table_id,
                'file_name' => $file_name,
                'description' => $description,
                'date_file' => $date_file,
                'file_url' => $file_url
            ]
        );
        echo '<div class="notice notice-success"><p>Archivo agregado con éxito.</p></div>';
    }

    // Guardar cambios en nombres, descripciones y URLs
    if (isset($_POST['save'])) {
        $id = intval($_POST['save']);
        $file_name = sanitize_text_field($_POST['file_name'][$id]);
        $description = sanitize_textarea_field($_POST['description'][$id]);
        $date_file = sanitize_textarea_field($_POST['date_file'][$id]);
        $file_url = esc_url_raw($_POST['file_url'][$id]);

        // Validar la URL
        if (!filter_var($file_url, FILTER_VALIDATE_URL)) {
            echo '<div class="notice notice-error"><p>La URL ingresada no es válida.</p></div>';
            return;
        }

        $wpdb->update(
            $files_table,
            [
                'file_name' => $file_name,
                'description' => $description,
                'date_file' => $date_file,
                'file_url' => $file_url
            ],
            ['id' => $id]
        );
        echo '<div class="notice notice-success"><p>Archivo actualizado con éxito.</p></div>';
    }

    // Obtener todos los archivos de esta tabla
    $files = $wpdb->get_results($wpdb->prepare("SELECT * FROM $files_table WHERE table_id = %d", $table_id));

    echo '<div class="wrap">';
    echo '<h1>Gestión de Archivos</h1>';

    // Formulario para agregar un nuevo archivo
    echo '<form method="post" class="files-form">';
    echo '<h2>Agregar Nuevo Archivo</h2>';
    echo '<label for="file_name">Nombre del Archivo:</label>';
    echo '<input type="text" name="file_name" id="file_name" required>';
    echo '<label for="description">Descripción:</label>';
    echo '<textarea name="description" id="description"></textarea>';
    echo '<label for="date_file">Fecha del Archivo:</label>';
    echo '<textarea name="date_file" id="date_file"></textarea>';
    echo '<label for="file_url">URL del Archivo:</label>';
    echo '<input type="url" name="file_url" id="file_url" required>';
    echo '<button type="submit" name="add_file" class="button button-primary">Agregar Archivo</button>';
    echo '</form>';

    // Mostrar archivos existentes
    echo '<h2>Archivos Existentes</h2>';
    echo '<form method="post">';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Nombre del Archivo</th><th>Descripción</th><th>URL</th><th>Acciones</th></tr></thead>';
    echo '<tbody>';
    foreach ($files as $file) {
        echo '<tr>';
        echo '<td>' . esc_html($file->id) . '</td>';
        echo '<td><input type="text" name="file_name[' . $file->id . ']" value="' . esc_attr($file->file_name) . '"></td>';
        echo '<td><textarea name="description[' . $file->id . ']">' . esc_textarea($file->description) . '</textarea></td>';
        echo '<td><textarea name="date_file[' . $file->id . ']">' . esc_textarea($file->date_file) . '</textarea></td>';
        echo '<td><input type="url" name="file_url[' . $file->id . ']" value="' . esc_url($file->file_url) . '"></td>';
        echo '<td><button type="submit" name="save" value="' . $file->id . '" class="button">Guardar</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</form>';
    echo '</div>';
}

// Shortcode para mostrar una tabla específica
function tables_codermaster_shortcode($atts) {
    global $wpdb;
    $files_table = $wpdb->prefix . 'tables_codermaster_files';

    // Obtener el ID de la tabla del shortcode
    $atts = shortcode_atts(['id' => ''], $atts);
    $table_id = intval($atts['id']);

    // Obtener los archivos de esta tabla
    $files = $wpdb->get_results($wpdb->prepare("SELECT * FROM $files_table WHERE table_id = %d", $table_id));
    if (empty($files)) {
        return '<p>No hay archivos para mostrar.</p>';
    }

    // Generar la tabla
    $output = '<table class="tables-codermaster-table">';
    $output .= '<thead><tr><th>Nombre del Archivo</th><th>Descripción</th><th>Fecha de Publicación</th><th>Acción</th></tr></thead>';
    $output .= '<tbody>';
    foreach ($files as $file) {
        $output .= '<tr>';
        $output .= '<td>' . esc_html($file->file_name) . '</td>';
        $output .= '<td>' . esc_html($file->description) . '</td>';
        $output .= '<td>' . esc_html($file->date_file) . '</td>';
        $output .= '<td><a href="' . esc_url($file->file_url) . '" target="_blank" class="button">Descargar</a></td>';
        $output .= '</tr>';
    }
    $output .= '</tbody></table>';
    return $output;
}
add_shortcode('tables_codermaster', 'tables_codermaster_shortcode');