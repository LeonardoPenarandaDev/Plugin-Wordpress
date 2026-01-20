<?php
/*
Plugin Name: Tables Codermaster
Description: Un plugin para gestionar tablas y archivos asociados.
Version: 1.8
Author: Leonardo Alexander Peñaranda Angarita
*/

// Crear tablas en la base de datos
if (!defined('ABSPATH')) {
    exit;
}

// Registrar el shortcode para mostrar las tablas
function tables_codermaster_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => '',
        'titulo' => 'Lista de Archivos'
    ], $atts);
    
    $tablas = get_option('tables_codermaster_tablas', []);
    $archivos = get_option('tables_codermaster_files', []);
    
    $tabla_nombre = isset($tablas[$atts['id']]) ? esc_html($tablas[$atts['id']]) : 'Tabla Desconocida';
    
    ob_start();
    ?>
    <h2><?php echo esc_html($tabla_nombre); ?></h2>
    <table class="tables-codermaster-table">
        <thead>
            <tr>
                <th>Nombre del Archivo</th>
                <th>Descripción</th>
                <th>Fecha de Publicación</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($archivos as $archivo) {
                if ($archivo['tabla_id'] === $atts['id']) {
                    echo '<tr>';
                    echo '<td>' . esc_html($archivo['nombre']) . '</td>';
                    echo '<td>' . esc_html($archivo['descripcion']) . '</td>';
                    echo '<td>' . esc_html($archivo['fecha_publicacion']) . '</td>';
                    echo '<td><a class="button" href="' . esc_url($archivo['url']) . '" download>Descargar</a></td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}
add_shortcode('tables_codermaster', 'tables_codermaster_shortcode');

// Guardar archivos con tabla asociada
function tables_codermaster_guardar_archivo($nombre, $descripcion, $url, $fecha_publicacion, $tabla_id) {
    $archivos = get_option('tables_codermaster_files', []);
    $archivos[] = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'url' => $url,
        'fecha_publicacion' => $fecha_publicacion,
        'tabla_id' => $tabla_id
    ];
    update_option('tables_codermaster_files', $archivos);
}

// Guardar nueva tabla
function tables_codermaster_guardar_tabla($nombre) {
    $tablas = get_option('tables_codermaster_tablas', []);
    $id = uniqid();
    $tablas[$id] = $nombre;
    update_option('tables_codermaster_tablas', $tablas);
    return $id;
}

// Cargar estilos
function tables_codermaster_enqueue_styles() {
    wp_enqueue_style('tables-codermaster-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('admin_enqueue_scripts', 'tables_codermaster_enqueue_styles');
add_action('wp_enqueue_scripts', 'tables_codermaster_enqueue_styles');

// Agregar un menú en el panel de administración
function tables_codermaster_menu() {
    add_menu_page(__('Tables Codermaster', 'tables-codermaster'), __('Tables Codermaster', 'tables-codermaster'), 'manage_options', 'tables-codermaster', 'tables_codermaster_admin_page');
}
add_action('admin_menu', 'tables_codermaster_menu');

// Página de administración
function tables_codermaster_admin_page() {
    $tablas = get_option('tables_codermaster_tablas', []);
    $archivos = get_option('tables_codermaster_files', []);
    $tabla_id = isset($_GET['tabla_id']) ? sanitize_text_field($_GET['tabla_id']) : '';
    ?>
    <div class="wrap">
        <h1><?php _e('Gestión de Tablas', 'tables-codermaster'); ?></h1>
        <?php 
        $edit_tabla_id = isset($_GET['edit_tabla_id']) ? sanitize_text_field($_GET['edit_tabla_id']) : '';
        if ($edit_tabla_id && isset($tablas[$edit_tabla_id])): ?>
            <h2><?php _e('Editar Nombre de la Tabla', 'tables-codermaster'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('tables_codermaster_guardar_tabla', 'tables_codermaster_nonce'); ?>
                <input type="hidden" name="edit_tabla_id" value="<?php echo esc_attr($edit_tabla_id); ?>">
                <label for="nuevo_nombre_tabla"><?php _e('Nuevo Nombre:', 'tables-codermaster'); ?></label>
                <input type="text" name="nuevo_nombre_tabla" value="<?php echo esc_attr($tablas[$edit_tabla_id]); ?>" required>
                <button type="submit" name="actualizar_tabla"><?php _e('Actualizar', 'tables-codermaster'); ?></button>
                <a href="?page=tables-codermaster" class="button" style="margin-left:10px;">&larr; <?php _e('Cancelar', 'tables-codermaster'); ?></a>
            </form>
        <?php elseif ($tabla_id && isset($tablas[$tabla_id])): ?>
            <h2><?php echo esc_html($tablas[$tabla_id]); ?></h2>
            <form method="post">
                <?php wp_nonce_field('tables_codermaster_guardar_tabla', 'tables_codermaster_nonce'); ?>
                <input type="hidden" name="tabla_id" value="<?php echo esc_attr($tabla_id); ?>">
                <label for="nombre"><?php _e('Nombre del Archivo:', 'tables-codermaster'); ?></label>
                <input type="text" name="nombre" required>
                <label for="descripcion"><?php _e('Descripción:', 'tables-codermaster'); ?></label>
                <input type="text" name="descripcion" required>
                <label for="url"><?php _e('URL del Archivo:', 'tables-codermaster'); ?></label>
                <input type="url" name="url" required>
                <label for="fecha_publicacion"><?php _e('Fecha de Publicación:', 'tables-codermaster'); ?></label>
                <input type="date" name="fecha_publicacion" required>
                <button type="submit" name="guardar_archivo"><?php _e('Guardar Archivo', 'tables-codermaster'); ?></button>
            </form>
            <h3><?php _e('Archivos de la tabla', 'tables-codermaster'); ?></h3>
            <table class="tables-codermaster-table">
                <thead>
                    <tr>
                        <th><?php _e('Nombre del Archivo', 'tables-codermaster'); ?></th>
                        <th><?php _e('Descripción', 'tables-codermaster'); ?></th>
                        <th><?php _e('Fecha de Publicación', 'tables-codermaster'); ?></th>
                        <th><?php _e('Acción', 'tables-codermaster'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $edit_archivo_idx = isset($_GET['edit_archivo_idx']) ? intval($_GET['edit_archivo_idx']) : null;
                    foreach ($archivos as $idx => $archivo) {
                        if ($archivo['tabla_id'] === $tabla_id) {
                            if ($edit_archivo_idx === $idx) {
                                echo '<tr><form method="post">';
                                wp_nonce_field('tables_codermaster_editar_archivo', 'tables_codermaster_nonce');
                                echo '<td><input type="text" name="edit_nombre" value="' . esc_attr($archivo['nombre']) . '" required></td>';
                                echo '<td><input type="text" name="edit_descripcion" value="' . esc_attr($archivo['descripcion']) . '" required></td>';
                                echo '<td><input type="date" name="edit_fecha_publicacion" value="' . esc_attr($archivo['fecha_publicacion']) . '" required></td>';
                                echo '<td>';
                                echo '<input type="url" name="edit_url" value="' . esc_attr($archivo['url']) . '" required style="width:70%;">';
                                echo '<input type="hidden" name="edit_archivo_idx" value="' . esc_attr($idx) . '">';
                                echo '<button type="submit" name="actualizar_archivo" class="button" style="margin-left:5px;">' . __('Guardar', 'tables-codermaster') . '</button>';
                                echo '<a href="?page=tables-codermaster&tabla_id=' . esc_attr($tabla_id) . '" class="button" style="margin-left:5px;">' . __('Cancelar', 'tables-codermaster') . '</a>';
                                echo '</td>';
                                echo '</form></tr>';
                            } else {
                                echo '<tr>';
                                echo '<td>' . esc_html($archivo['nombre']) . '</td>';
                                echo '<td>' . esc_html($archivo['descripcion']) . '</td>';
                                echo '<td>' . esc_html($archivo['fecha_publicacion']) . '</td>';
                                echo '<td>';
                                echo '<a class="button" href="' . esc_url($archivo['url']) . '" download>' . __('Descargar', 'tables-codermaster') . '</a>';
                                echo '<a href="?page=tables-codermaster&tabla_id=' . esc_attr($tabla_id) . '&edit_archivo_idx=' . esc_attr($idx) . '" class="button" style="background:#f39c12; margin-left:5px; color:#fff;">' . __('Editar', 'tables-codermaster') . '</a>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                    } ?>
                </tbody>
            </table>
            <p><a href="?page=tables-codermaster" class="button">&larr; <?php _e('Volver a Tablas', 'tables-codermaster'); ?></a></p>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('tables_codermaster_guardar_tabla', 'tables_codermaster_nonce'); ?>
                <label for="nombre_tabla"><?php _e('Nombre de la Tabla:', 'tables-codermaster'); ?></label>
                <input type="text" name="nombre_tabla" required>
                <button type="submit" name="guardar_tabla"><?php _e('Agregar Tabla', 'tables-codermaster'); ?></button>
            </form>
            <h2><?php _e('Tablas Existentes', 'tables-codermaster'); ?></h2>
            <table class="tables-codermaster-table">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'tables-codermaster'); ?></th>
                        <th><?php _e('Nombre de la Tabla', 'tables-codermaster'); ?></th>
                        <th><?php _e('Shortcode', 'tables-codermaster'); ?></th>
                        <th><?php _e('Acciones', 'tables-codermaster'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tablas as $id => $nombre) { ?>
                        <tr>
                            <td><?php echo esc_html($id); ?></td>
                            <td><?php echo esc_html($nombre); ?></td>
                            <td>[tables_codermaster id="<?php echo esc_html($id); ?>"]</td>
                            <td>
                                <a href="?page=tables-codermaster&tabla_id=<?php echo esc_html($id); ?>" class="button"><?php _e('Gestionar Archivos', 'tables-codermaster'); ?></a>
                                <a href="?page=tables-codermaster&edit_tabla_id=<?php echo esc_html($id); ?>" class="button" style="background:#f39c12; margin-left:5px; color:#fff;">
                                    <?php _e('Editar Nombre', 'tables-codermaster'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    if (isset($_POST['guardar_tabla']) && isset($_POST['tables_codermaster_nonce']) && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_guardar_tabla')) {
        $nombre_tabla = sanitize_text_field($_POST['nombre_tabla']);
        $id = tables_codermaster_guardar_tabla($nombre_tabla);
        echo '<div class="updated"><p>' . sprintf(__('Tabla creada con éxito. Shortcode: [tables_codermaster id="%s"]', 'tables-codermaster'), esc_html($id)) . '</p></div>';
    }
    if (isset($_POST['actualizar_tabla']) && isset($_POST['tables_codermaster_nonce']) && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_guardar_tabla')) {
        $edit_tabla_id = sanitize_text_field($_POST['edit_tabla_id']);
        $nuevo_nombre = sanitize_text_field($_POST['nuevo_nombre_tabla']);
        if ($edit_tabla_id && $nuevo_nombre && isset($tablas[$edit_tabla_id])) {
            $tablas[$edit_tabla_id] = $nuevo_nombre;
            update_option('tables_codermaster_tablas', $tablas);
            echo '<div class="updated"><p>' . __('Nombre de la tabla actualizado correctamente.', 'tables-codermaster') . '</p></div>';
        }
    }
    if (isset($_POST['actualizar_archivo']) && isset($_POST['tables_codermaster_nonce']) && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_editar_archivo')) {
        $edit_archivo_idx = intval($_POST['edit_archivo_idx']);
        $edit_nombre = sanitize_text_field($_POST['edit_nombre']);
        $edit_descripcion = sanitize_text_field($_POST['edit_descripcion']);
        $edit_fecha_publicacion = sanitize_text_field($_POST['edit_fecha_publicacion']);
        $edit_url = esc_url_raw($_POST['edit_url']);
        $archivos = get_option('tables_codermaster_files', []);
        if (isset($archivos[$edit_archivo_idx])) {
            $archivos[$edit_archivo_idx]['nombre'] = $edit_nombre;
            $archivos[$edit_archivo_idx]['descripcion'] = $edit_descripcion;
            $archivos[$edit_archivo_idx]['fecha_publicacion'] = $edit_fecha_publicacion;
            $archivos[$edit_archivo_idx]['url'] = $edit_url;
            update_option('tables_codermaster_files', $archivos);
            echo '<div class="updated"><p>' . __('Archivo actualizado correctamente.', 'tables-codermaster') . '</p></div>';
        }
    }
    if (isset($_POST['guardar_archivo']) && isset($_POST['tables_codermaster_nonce']) && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_guardar_tabla')) {
        $nombre = sanitize_text_field($_POST['nombre']);
        $descripcion = sanitize_text_field($_POST['descripcion']);
        $url = esc_url_raw($_POST['url']);
        $fecha_publicacion = sanitize_text_field($_POST['fecha_publicacion']);
        $tabla_id = sanitize_text_field($_POST['tabla_id']);
        tables_codermaster_guardar_archivo($nombre, $descripcion, $url, $fecha_publicacion, $tabla_id);
        echo '<div class="updated"><p>' . __('Archivo guardado con éxito.', 'tables-codermaster') . '</p></div>';
    }
}