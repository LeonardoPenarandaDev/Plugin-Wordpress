<?php
/**
 * Plugin Name: Tables Codermaster
 * Description: Plugin para generar múltiples tablas dinámicas con nombres y descripciones personalizables.
 * Version: 2.0
 * Author: Leonardo Alexander Peñaranda Angarita
 * Text Domain: tables-codermaster
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================
// INSTALACIÓN Y ACTUALIZACIÓN
// =============================================

function tables_codermaster_create_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $tables_table = $wpdb->prefix . 'tables_codermaster';
    $sql_tables = "CREATE TABLE $tables_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        table_name VARCHAR(255) NOT NULL,
        shortcode VARCHAR(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $files_table = $wpdb->prefix . 'tables_codermaster_files';
    $sql_files = "CREATE TABLE $files_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        table_id mediumint(9) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        date_submit TEXT NOT NULL,
        file_url TEXT NOT NULL,
        orden int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_tables);
    dbDelta($sql_files);
}
register_activation_hook(__FILE__, 'tables_codermaster_create_db');

/**
 * Auto-upgrade: cuando se reemplazan los archivos sin desactivar/reactivar
 */
function tables_codermaster_check_upgrade() {
    $current_version = get_option('tables_codermaster_version', '0');
    if (version_compare($current_version, '2.0', '<')) {
        tables_codermaster_create_db();
        tables_codermaster_init_orden();
        update_option('tables_codermaster_version', '2.0');
    }
}
add_action('admin_init', 'tables_codermaster_check_upgrade');

/**
 * Asignar orden a archivos existentes que no lo tienen (orden = 0)
 */
function tables_codermaster_init_orden() {
    global $wpdb;
    $files_table = $wpdb->prefix . 'tables_codermaster_files';

    $unordered = $wpdb->get_results(
        "SELECT id, table_id FROM $files_table WHERE orden = 0 ORDER BY id ASC"
    );

    $counters = [];
    foreach ($unordered as $file) {
        if (!isset($counters[$file->table_id])) {
            $max = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(orden) FROM $files_table WHERE table_id = %d AND orden > 0",
                $file->table_id
            ));
            $counters[$file->table_id] = $max ? intval($max) + 1 : 1;
        }
        $wpdb->update(
            $files_table,
            ['orden' => $counters[$file->table_id]],
            ['id' => $file->id]
        );
        $counters[$file->table_id]++;
    }
}

// =============================================
// ESTILOS
// =============================================

function tables_codermaster_enqueue_styles() {
    wp_enqueue_style('tables-codermaster-styles', plugin_dir_url(__FILE__) . 'styles.css', [], '2.0');
}
add_action('admin_enqueue_scripts', 'tables_codermaster_enqueue_styles');
add_action('wp_enqueue_scripts', 'tables_codermaster_enqueue_styles');

// =============================================
// MENÚ ADMIN
// =============================================

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
}
add_action('admin_menu', 'tables_codermaster_admin_menu');

function tables_codermaster_add_files_page() {
    add_submenu_page(
        null,
        'Gestionar Archivos',
        'Gestionar Archivos',
        'manage_options',
        'tables-codermaster-files',
        'tables_codermaster_manage_files_page'
    );
}
add_action('admin_menu', 'tables_codermaster_add_files_page');

// =============================================
// PÁGINA PRINCIPAL - GESTIÓN DE TABLAS
// =============================================

function tables_codermaster_admin_page() {
    global $wpdb;
    $tables_table = $wpdb->prefix . 'tables_codermaster';
    $files_table = $wpdb->prefix . 'tables_codermaster_files';
    $mensaje = '';

    // --- PROCESAR ACCIONES PRIMERO ---

    // Agregar tabla
    if (isset($_POST['add_table']) && check_admin_referer('tc_add_table')) {
        $table_name = sanitize_text_field($_POST['table_name']);
        $wpdb->insert($tables_table, ['table_name' => $table_name, 'shortcode' => '']);
        $inserted_id = $wpdb->insert_id;
        $shortcode = '[tables_codermaster id="' . $inserted_id . '"]';
        $wpdb->update($tables_table, ['shortcode' => $shortcode], ['id' => $inserted_id]);
        $mensaje = 'Tabla agregada con éxito. Shortcode: <code>' . esc_html($shortcode) . '</code>';
    }

    // Editar nombre de tabla
    if (isset($_POST['update_table']) && check_admin_referer('tc_update_table')) {
        $table_id = intval($_POST['table_id']);
        $table_name = sanitize_text_field($_POST['table_name']);
        $wpdb->update($tables_table, ['table_name' => $table_name], ['id' => $table_id]);
        $mensaje = 'Nombre de la tabla actualizado correctamente.';
    }

    // Eliminar tabla
    if (isset($_GET['action']) && $_GET['action'] === 'delete_table'
        && isset($_GET['table_id']) && isset($_GET['_wpnonce'])
        && wp_verify_nonce($_GET['_wpnonce'], 'tc_delete_table_' . intval($_GET['table_id']))) {
        $del_id = intval($_GET['table_id']);
        $wpdb->delete($tables_table, ['id' => $del_id]);
        $wpdb->delete($files_table, ['table_id' => $del_id]);
        $mensaje = 'Tabla y sus archivos eliminados correctamente.';
    }

    // --- OBTENER DATOS ---
    $tables = $wpdb->get_results("SELECT * FROM $tables_table ORDER BY id ASC");
    $edit_table_id = isset($_GET['edit_table_id']) ? intval($_GET['edit_table_id']) : 0;

    // --- RENDERIZAR ---
    ?>
    <div class="wrap">
        <h1>Tables Codermaster</h1>

        <?php if ($mensaje): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post($mensaje); ?></p></div>
        <?php endif; ?>

        <?php if ($edit_table_id):
            $edit_table = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tables_table WHERE id = %d", $edit_table_id));
            if ($edit_table): ?>
                <h2>Editar Nombre de la Tabla</h2>
                <form method="post">
                    <?php wp_nonce_field('tc_update_table'); ?>
                    <input type="hidden" name="table_id" value="<?php echo esc_attr($edit_table->id); ?>">
                    <table class="form-table">
                        <tr>
                            <th><label for="table_name">Nuevo Nombre</label></th>
                            <td><input type="text" name="table_name" value="<?php echo esc_attr($edit_table->table_name); ?>" class="regular-text" required></td>
                        </tr>
                    </table>
                    <button type="submit" name="update_table" class="button button-primary">Actualizar</button>
                    <a href="?page=tables-codermaster" class="button">Cancelar</a>
                </form>
            <?php endif;
        else: ?>
            <h2>Agregar Nueva Tabla</h2>
            <form method="post">
                <?php wp_nonce_field('tc_add_table'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="table_name">Nombre de la Tabla</label></th>
                        <td><input type="text" name="table_name" id="table_name" class="regular-text" required></td>
                    </tr>
                </table>
                <button type="submit" name="add_table" class="button button-primary">Agregar Tabla</button>
            </form>

            <h2>Tablas Existentes</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de la Tabla</th>
                        <th>Shortcode</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tables)): ?>
                        <tr><td colspan="4">No hay tablas creadas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><?php echo esc_html($table->id); ?></td>
                            <td><?php echo esc_html($table->table_name); ?></td>
                            <td><code><?php echo esc_html($table->shortcode); ?></code></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=tables-codermaster-files&table_id=' . $table->id); ?>" class="button">Gestionar Archivos</a>
                                <a href="?page=tables-codermaster&edit_table_id=<?php echo esc_attr($table->id); ?>" class="button tc-btn-edit">Editar Nombre</a>
                                <?php $del_url = wp_nonce_url(
                                    '?page=tables-codermaster&action=delete_table&table_id=' . $table->id,
                                    'tc_delete_table_' . $table->id
                                ); ?>
                                <a href="<?php echo esc_url($del_url); ?>"
                                   class="button tc-btn-delete"
                                   onclick="return confirm('¿Eliminar esta tabla y todos sus archivos?');">Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// =============================================
// SUBPÁGINA - GESTIÓN DE ARCHIVOS
// =============================================

function tables_codermaster_manage_files_page() {
    global $wpdb;
    $files_table = $wpdb->prefix . 'tables_codermaster_files';
    $tables_table = $wpdb->prefix . 'tables_codermaster';
    $table_id = intval($_GET['table_id']);
    $mensaje = '';

    // Verificar que la tabla existe
    $table_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tables_table WHERE id = %d", $table_id));
    if (!$table_info) {
        echo '<div class="wrap"><p>Tabla no encontrada.</p></div>';
        return;
    }

    $base_url = admin_url('admin.php?page=tables-codermaster-files&table_id=' . $table_id);

    // --- PROCESAR ACCIONES PRIMERO ---

    // Agregar archivo
    if (isset($_POST['add_file']) && check_admin_referer('tc_add_file')) {
        $file_name = sanitize_text_field($_POST['file_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $date_submit = sanitize_text_field($_POST['date_submit']);
        $file_url = esc_url_raw($_POST['file_url']);
        $posicion = (isset($_POST['posicion']) && $_POST['posicion'] !== '') ? intval($_POST['posicion']) : null;

        if ($posicion !== null && $posicion > 0) {
            // Desplazar archivos con orden >= posicion
            $wpdb->query($wpdb->prepare(
                "UPDATE $files_table SET orden = orden + 1 WHERE table_id = %d AND orden >= %d",
                $table_id, $posicion
            ));
            $orden = $posicion;
        } else {
            // Agregar al final
            $max_orden = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(orden) FROM $files_table WHERE table_id = %d",
                $table_id
            ));
            $orden = ($max_orden ? intval($max_orden) : 0) + 1;
        }

        $wpdb->insert($files_table, [
            'table_id' => $table_id,
            'file_name' => $file_name,
            'description' => $description,
            'date_submit' => $date_submit,
            'file_url' => $file_url,
            'orden' => $orden
        ]);
        $mensaje = 'Archivo agregado con éxito.';
    }

    // Actualizar archivo
    if (isset($_POST['save']) && check_admin_referer('tc_edit_file')) {
        $id = intval($_POST['id']);
        $wpdb->update($files_table, [
            'file_name' => sanitize_text_field($_POST['file_name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'date_submit' => sanitize_text_field($_POST['date_submit']),
            'file_url' => esc_url_raw($_POST['file_url'])
        ], ['id' => $id]);
        $mensaje = 'Archivo actualizado con éxito.';
    }

    // Eliminar archivo
    if (isset($_GET['action']) && $_GET['action'] === 'delete_file'
        && isset($_GET['file_id']) && isset($_GET['_wpnonce'])
        && wp_verify_nonce($_GET['_wpnonce'], 'tc_delete_file_' . intval($_GET['file_id']))) {
        $del_id = intval($_GET['file_id']);
        $wpdb->delete($files_table, ['id' => $del_id]);
        $mensaje = 'Archivo eliminado correctamente.';
    }

    // Mover archivo (arriba/abajo)
    if (isset($_GET['action']) && in_array($_GET['action'], ['move_up', 'move_down'])
        && isset($_GET['file_id']) && isset($_GET['_wpnonce'])
        && wp_verify_nonce($_GET['_wpnonce'], 'tc_move_file_' . intval($_GET['file_id']))) {
        $file_id = intval($_GET['file_id']);
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT id, orden FROM $files_table WHERE id = %d", $file_id
        ));

        if ($current) {
            if ($_GET['action'] === 'move_up') {
                $swap = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, orden FROM $files_table WHERE table_id = %d AND orden < %d ORDER BY orden DESC LIMIT 1",
                    $table_id, $current->orden
                ));
            } else {
                $swap = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, orden FROM $files_table WHERE table_id = %d AND orden > %d ORDER BY orden ASC LIMIT 1",
                    $table_id, $current->orden
                ));
            }

            if ($swap) {
                $wpdb->update($files_table, ['orden' => $swap->orden], ['id' => $current->id]);
                $wpdb->update($files_table, ['orden' => $current->orden], ['id' => $swap->id]);
            }
        }
    }

    // --- OBTENER DATOS FRESCOS ---
    $files = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $files_table WHERE table_id = %d ORDER BY orden ASC, id ASC",
        $table_id
    ));

    $edit_file_id = isset($_GET['edit_file_id']) ? intval($_GET['edit_file_id']) : 0;

    // --- RENDERIZAR ---
    ?>
    <div class="wrap">
        <h1><?php echo esc_html($table_info->table_name); ?> - Gestión de Archivos</h1>

        <?php if ($mensaje): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($mensaje); ?></p></div>
        <?php endif; ?>

        <h2>Agregar Nuevo Archivo</h2>
        <form method="post">
            <?php wp_nonce_field('tc_add_file'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="file_name">Nombre del Archivo</label></th>
                    <td><input type="text" name="file_name" id="file_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="description">Descripción</label></th>
                    <td><textarea name="description" id="description" rows="2" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="date_submit">Fecha de Subido</label></th>
                    <td><input type="date" name="date_submit" id="date_submit" required></td>
                </tr>
                <tr>
                    <th><label for="file_url">URL del Archivo</label></th>
                    <td><input type="url" name="file_url" id="file_url" class="large-text" required></td>
                </tr>
                <tr>
                    <th><label for="posicion">Insertar en posición</label></th>
                    <td>
                        <select name="posicion" id="posicion">
                            <option value="">Al final</option>
                            <option value="1">Al inicio</option>
                            <?php foreach ($files as $f): ?>
                                <option value="<?php echo esc_attr(intval($f->orden) + 1); ?>">
                                    Después de: <?php echo esc_html($f->file_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <button type="submit" name="add_file" class="button button-primary">Agregar Archivo</button>
        </form>

        <h2>Archivos Existentes</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Nombre del Archivo</th>
                    <th>Descripción</th>
                    <th>Fecha de Subido</th>
                    <th>URL</th>
                    <th>Acciones</th>
                    <th style="width:80px;">Orden</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($files)): ?>
                    <tr><td colspan="7">No hay archivos en esta tabla.</td></tr>
                <?php else: ?>
                    <?php
                    $total = count($files);
                    foreach ($files as $pos => $file):
                        if ($edit_file_id === intval($file->id)):
                    ?>
                    <tr>
                        <td><?php echo $pos + 1; ?></td>
                        <td colspan="6">
                            <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <?php wp_nonce_field('tc_edit_file'); ?>
                                <input type="hidden" name="id" value="<?php echo esc_attr($file->id); ?>">
                                <input type="text" name="file_name" value="<?php echo esc_attr($file->file_name); ?>" required placeholder="Nombre">
                                <input type="text" name="description" value="<?php echo esc_attr($file->description); ?>" placeholder="Descripción">
                                <input type="date" name="date_submit" value="<?php echo esc_attr($file->date_submit); ?>" required>
                                <input type="url" name="file_url" value="<?php echo esc_attr($file->file_url); ?>" required placeholder="URL" style="min-width:200px;">
                                <button type="submit" name="save" class="button button-primary">Guardar</button>
                                <a href="<?php echo esc_url($base_url); ?>" class="button">Cancelar</a>
                            </form>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><?php echo $pos + 1; ?></td>
                        <td><?php echo esc_html($file->file_name); ?></td>
                        <td><?php echo esc_html($file->description); ?></td>
                        <td><?php echo esc_html($file->date_submit); ?></td>
                        <td><a href="<?php echo esc_url($file->file_url); ?>" target="_blank">Ver archivo</a></td>
                        <td>
                            <a href="<?php echo esc_url($base_url . '&edit_file_id=' . $file->id); ?>" class="button tc-btn-edit">Editar</a>
                            <?php $del_url = wp_nonce_url($base_url . '&action=delete_file&file_id=' . $file->id, 'tc_delete_file_' . $file->id); ?>
                            <a href="<?php echo esc_url($del_url); ?>"
                               class="button tc-btn-delete"
                               onclick="return confirm('¿Eliminar este archivo?');">Eliminar</a>
                        </td>
                        <td class="tc-orden-cell">
                            <?php if ($pos > 0):
                                $up_url = wp_nonce_url($base_url . '&action=move_up&file_id=' . $file->id, 'tc_move_file_' . $file->id);
                            ?>
                                <a href="<?php echo esc_url($up_url); ?>" class="button tc-btn-order" title="Subir">&#9650;</a>
                            <?php endif; ?>
                            <?php if ($pos < $total - 1):
                                $down_url = wp_nonce_url($base_url . '&action=move_down&file_id=' . $file->id, 'tc_move_file_' . $file->id);
                            ?>
                                <a href="<?php echo esc_url($down_url); ?>" class="button tc-btn-order" title="Bajar">&#9660;</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif;
                    endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top:15px;">
            <a href="<?php echo admin_url('admin.php?page=tables-codermaster'); ?>" class="button">&larr; Volver a Tablas</a>
        </p>
    </div>
    <?php
}

// =============================================
// SHORTCODE (frontend)
// =============================================

function tables_codermaster_shortcode($atts) {
    global $wpdb;
    $files_table = $wpdb->prefix . 'tables_codermaster_files';

    $atts = shortcode_atts(['id' => ''], $atts);
    $table_id = intval($atts['id']);

    $files = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $files_table WHERE table_id = %d ORDER BY orden ASC, id ASC",
        $table_id
    ));

    if (empty($files)) {
        return '<p>No hay archivos para mostrar.</p>';
    }

    $output = '<table class="tables-codermaster-table">';
    $output .= '<thead><tr>';
    $output .= '<th>Nombre del Archivo</th>';
    $output .= '<th>Descripción</th>';
    $output .= '<th>Fecha de Subido</th>';
    $output .= '<th>Acción</th>';
    $output .= '</tr></thead>';
    $output .= '<tbody>';

    foreach ($files as $file) {
        $output .= '<tr>';
        $output .= '<td>' . esc_html($file->file_name) . '</td>';
        $output .= '<td>' . esc_html($file->description) . '</td>';
        $output .= '<td>' . esc_html($file->date_submit) . '</td>';
        $output .= '<td><a href="' . esc_url($file->file_url) . '" target="_blank" class="button">Descargar</a></td>';
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';
    return $output;
}
add_shortcode('tables_codermaster', 'tables_codermaster_shortcode');
