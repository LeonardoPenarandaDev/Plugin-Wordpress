<?php
/*
Plugin Name: Tables Codermaster
Description: Un plugin para gestionar tablas y archivos asociados.
Version: 2.0
Author: Leonardo Alexander Peñaranda Angarita
Text Domain: tables-codermaster
*/

if (!defined('ABSPATH')) {
    exit;
}

// =============================================
// FUNCIONES DE DATOS
// =============================================

/**
 * Asegurar que todos los archivos tengan campo 'orden' (compatibilidad con datos anteriores)
 */
function tables_codermaster_ensure_orden() {
    $archivos = get_option('tables_codermaster_files', []);
    $changed = false;
    $counters = [];

    foreach ($archivos as $idx => &$archivo) {
        $tid = $archivo['tabla_id'];
        if (!isset($archivo['orden'])) {
            if (!isset($counters[$tid])) {
                $counters[$tid] = 1;
            }
            $archivo['orden'] = $counters[$tid]++;
            $changed = true;
        } else {
            if (!isset($counters[$tid]) || $archivo['orden'] >= $counters[$tid]) {
                $counters[$tid] = $archivo['orden'] + 1;
            }
        }
    }
    unset($archivo);

    if ($changed) {
        update_option('tables_codermaster_files', $archivos);
    }
}

/**
 * Obtener archivos de una tabla, ordenados por 'orden'
 */
function tables_codermaster_get_archivos_tabla($tabla_id) {
    tables_codermaster_ensure_orden();
    $archivos = get_option('tables_codermaster_files', []);
    $filtered = [];

    foreach ($archivos as $idx => $archivo) {
        if ($archivo['tabla_id'] === $tabla_id) {
            $archivo['_idx'] = $idx;
            $filtered[] = $archivo;
        }
    }

    usort($filtered, function ($a, $b) {
        return ($a['orden'] ?? 0) - ($b['orden'] ?? 0);
    });

    return $filtered;
}

/**
 * Guardar nuevo archivo con posición opcional
 */
function tables_codermaster_guardar_archivo($nombre, $descripcion, $url, $fecha_publicacion, $tabla_id, $posicion = null) {
    tables_codermaster_ensure_orden();
    $archivos = get_option('tables_codermaster_files', []);

    if ($posicion !== null && $posicion > 0) {
        foreach ($archivos as &$a) {
            if ($a['tabla_id'] === $tabla_id && isset($a['orden']) && $a['orden'] >= $posicion) {
                $a['orden']++;
            }
        }
        unset($a);
        $orden = $posicion;
    } else {
        $max = 0;
        foreach ($archivos as $a) {
            if ($a['tabla_id'] === $tabla_id && isset($a['orden']) && $a['orden'] > $max) {
                $max = $a['orden'];
            }
        }
        $orden = $max + 1;
    }

    $archivos[] = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'url' => $url,
        'fecha_publicacion' => $fecha_publicacion,
        'tabla_id' => $tabla_id,
        'orden' => $orden
    ];
    update_option('tables_codermaster_files', $archivos);
}

/**
 * Guardar nueva tabla
 */
function tables_codermaster_guardar_tabla($nombre) {
    $tablas = get_option('tables_codermaster_tablas', []);
    $id = uniqid();
    $tablas[$id] = $nombre;
    update_option('tables_codermaster_tablas', $tablas);
    return $id;
}

/**
 * Mover archivo arriba o abajo dentro de su tabla
 */
function tables_codermaster_mover_archivo($archivo_idx, $direccion, $tabla_id) {
    tables_codermaster_ensure_orden();
    $archivos = get_option('tables_codermaster_files', []);

    $tabla_archivos = [];
    foreach ($archivos as $idx => $a) {
        if ($a['tabla_id'] === $tabla_id) {
            $tabla_archivos[] = ['idx' => $idx, 'orden' => $a['orden']];
        }
    }
    usort($tabla_archivos, function ($a, $b) {
        return $a['orden'] - $b['orden'];
    });

    $pos = null;
    foreach ($tabla_archivos as $i => $ta) {
        if ($ta['idx'] == $archivo_idx) {
            $pos = $i;
            break;
        }
    }

    if ($pos === null) {
        return;
    }

    if ($direccion === 'up' && $pos > 0) {
        $swap_idx = $tabla_archivos[$pos - 1]['idx'];
        $temp = $archivos[$archivo_idx]['orden'];
        $archivos[$archivo_idx]['orden'] = $archivos[$swap_idx]['orden'];
        $archivos[$swap_idx]['orden'] = $temp;
    } elseif ($direccion === 'down' && $pos < count($tabla_archivos) - 1) {
        $swap_idx = $tabla_archivos[$pos + 1]['idx'];
        $temp = $archivos[$archivo_idx]['orden'];
        $archivos[$archivo_idx]['orden'] = $archivos[$swap_idx]['orden'];
        $archivos[$swap_idx]['orden'] = $temp;
    }

    update_option('tables_codermaster_files', $archivos);
}

// =============================================
// ESTILOS
// =============================================

function tables_codermaster_enqueue_styles() {
    wp_enqueue_style('tables-codermaster-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('admin_enqueue_scripts', 'tables_codermaster_enqueue_styles');
add_action('wp_enqueue_scripts', 'tables_codermaster_enqueue_styles');

// =============================================
// MENÚ ADMIN
// =============================================

function tables_codermaster_menu() {
    add_menu_page(
        __('Tables Codermaster', 'tables-codermaster'),
        __('Tables Codermaster', 'tables-codermaster'),
        'manage_options',
        'tables-codermaster',
        'tables_codermaster_admin_page'
    );
}
add_action('admin_menu', 'tables_codermaster_menu');

// =============================================
// PÁGINA DE ADMINISTRACIÓN
// =============================================

function tables_codermaster_admin_page() {
    // ---- PROCESAR ACCIONES PRIMERO (antes de renderizar) ----
    $mensaje = '';

    // Guardar nueva tabla
    if (isset($_POST['guardar_tabla']) && isset($_POST['tables_codermaster_nonce'])
        && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_guardar_tabla')) {
        $nombre_tabla = sanitize_text_field($_POST['nombre_tabla']);
        $id = tables_codermaster_guardar_tabla($nombre_tabla);
        $mensaje = sprintf(__('Tabla creada con éxito. Shortcode: [tables_codermaster id="%s"]', 'tables-codermaster'), esc_html($id));
    }

    // Actualizar nombre de tabla
    if (isset($_POST['actualizar_tabla']) && isset($_POST['tables_codermaster_nonce'])
        && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_guardar_tabla')) {
        $edit_tid = sanitize_text_field($_POST['edit_tabla_id']);
        $nuevo_nombre = sanitize_text_field($_POST['nuevo_nombre_tabla']);
        $tablas_tmp = get_option('tables_codermaster_tablas', []);
        if ($edit_tid && $nuevo_nombre && isset($tablas_tmp[$edit_tid])) {
            $tablas_tmp[$edit_tid] = $nuevo_nombre;
            update_option('tables_codermaster_tablas', $tablas_tmp);
            $mensaje = __('Nombre de la tabla actualizado correctamente.', 'tables-codermaster');
        }
    }

    // Guardar archivo
    if (isset($_POST['guardar_archivo']) && isset($_POST['tables_codermaster_nonce'])
        && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_guardar_tabla')) {
        $nombre = sanitize_text_field($_POST['nombre']);
        $descripcion = sanitize_text_field($_POST['descripcion']);
        $url = esc_url_raw($_POST['url']);
        $fecha_publicacion = sanitize_text_field($_POST['fecha_publicacion']);
        $tabla_id_post = sanitize_text_field($_POST['tabla_id']);
        $posicion = (isset($_POST['posicion']) && $_POST['posicion'] !== '') ? intval($_POST['posicion']) : null;
        tables_codermaster_guardar_archivo($nombre, $descripcion, $url, $fecha_publicacion, $tabla_id_post, $posicion);
        $mensaje = __('Archivo guardado con éxito.', 'tables-codermaster');
    }

    // Actualizar archivo
    if (isset($_POST['actualizar_archivo']) && isset($_POST['tables_codermaster_nonce'])
        && wp_verify_nonce($_POST['tables_codermaster_nonce'], 'tables_codermaster_editar_archivo')) {
        $edit_idx = intval($_POST['edit_archivo_idx']);
        $archivos_tmp = get_option('tables_codermaster_files', []);
        if (isset($archivos_tmp[$edit_idx])) {
            $archivos_tmp[$edit_idx]['nombre'] = sanitize_text_field($_POST['edit_nombre']);
            $archivos_tmp[$edit_idx]['descripcion'] = sanitize_text_field($_POST['edit_descripcion']);
            $archivos_tmp[$edit_idx]['fecha_publicacion'] = sanitize_text_field($_POST['edit_fecha_publicacion']);
            $archivos_tmp[$edit_idx]['url'] = esc_url_raw($_POST['edit_url']);
            update_option('tables_codermaster_files', $archivos_tmp);
            $mensaje = __('Archivo actualizado correctamente.', 'tables-codermaster');
        }
    }

    // Mover archivo (arriba/abajo via GET)
    if (isset($_GET['action']) && in_array($_GET['action'], ['move_up', 'move_down'])
        && isset($_GET['archivo_idx']) && isset($_GET['tabla_id']) && isset($_GET['_wpnonce'])) {
        $archivo_idx = intval($_GET['archivo_idx']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'tables_codermaster_mover_' . $archivo_idx)) {
            $direccion = ($_GET['action'] === 'move_up') ? 'up' : 'down';
            tables_codermaster_mover_archivo($archivo_idx, $direccion, sanitize_text_field($_GET['tabla_id']));
        }
    }

    // Eliminar archivo
    if (isset($_GET['action']) && $_GET['action'] === 'delete_archivo'
        && isset($_GET['archivo_idx']) && isset($_GET['tabla_id']) && isset($_GET['_wpnonce'])) {
        $del_idx = intval($_GET['archivo_idx']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'tables_codermaster_delete_' . $del_idx)) {
            $archivos_tmp = get_option('tables_codermaster_files', []);
            if (isset($archivos_tmp[$del_idx])) {
                array_splice($archivos_tmp, $del_idx, 1);
                update_option('tables_codermaster_files', $archivos_tmp);
                $mensaje = __('Archivo eliminado correctamente.', 'tables-codermaster');
            }
        }
    }

    // Eliminar tabla
    if (isset($_GET['action']) && $_GET['action'] === 'delete_tabla'
        && isset($_GET['delete_tabla_id']) && isset($_GET['_wpnonce'])) {
        $del_tid = sanitize_text_field($_GET['delete_tabla_id']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'tables_codermaster_delete_tabla_' . $del_tid)) {
            $tablas_tmp = get_option('tables_codermaster_tablas', []);
            if (isset($tablas_tmp[$del_tid])) {
                unset($tablas_tmp[$del_tid]);
                update_option('tables_codermaster_tablas', $tablas_tmp);
                $archivos_tmp = get_option('tables_codermaster_files', []);
                $archivos_tmp = array_values(array_filter($archivos_tmp, function ($a) use ($del_tid) {
                    return $a['tabla_id'] !== $del_tid;
                }));
                update_option('tables_codermaster_files', $archivos_tmp);
                $mensaje = __('Tabla y sus archivos eliminados correctamente.', 'tables-codermaster');
            }
        }
    }

    // ---- OBTENER DATOS FRESCOS ----
    $tablas = get_option('tables_codermaster_tablas', []);
    $tabla_id = isset($_GET['tabla_id']) ? sanitize_text_field($_GET['tabla_id']) : '';

    // ---- RENDERIZAR HTML ----
    ?>
    <div class="wrap">
        <h1><?php _e('Gestión de Tablas', 'tables-codermaster'); ?></h1>

        <?php if ($mensaje): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($mensaje); ?></p></div>
        <?php endif; ?>

        <?php
        $edit_tabla_id = isset($_GET['edit_tabla_id']) ? sanitize_text_field($_GET['edit_tabla_id']) : '';

        if ($edit_tabla_id && isset($tablas[$edit_tabla_id])): ?>
            <!-- ========== EDITAR NOMBRE DE TABLA ========== -->
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
            <!-- ========== GESTIONAR ARCHIVOS DE UNA TABLA ========== -->
            <h2><?php echo esc_html($tablas[$tabla_id]); ?></h2>

            <?php $archivos_tabla = tables_codermaster_get_archivos_tabla($tabla_id); ?>

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

                <label for="posicion"><?php _e('Insertar en posición:', 'tables-codermaster'); ?></label>
                <select name="posicion">
                    <option value=""><?php _e('Al final', 'tables-codermaster'); ?></option>
                    <option value="1"><?php _e('Al inicio', 'tables-codermaster'); ?></option>
                    <?php foreach ($archivos_tabla as $i => $at): ?>
                        <option value="<?php echo esc_attr($at['orden'] + 1); ?>">
                            <?php echo sprintf(__('Después de: %s', 'tables-codermaster'), esc_html($at['nombre'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="guardar_archivo"><?php _e('Guardar Archivo', 'tables-codermaster'); ?></button>
            </form>

            <h3><?php _e('Archivos de la tabla', 'tables-codermaster'); ?></h3>
            <table class="tables-codermaster-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th><?php _e('Nombre del Archivo', 'tables-codermaster'); ?></th>
                        <th><?php _e('Descripción', 'tables-codermaster'); ?></th>
                        <th><?php _e('Fecha de Publicación', 'tables-codermaster'); ?></th>
                        <th><?php _e('Acciones', 'tables-codermaster'); ?></th>
                        <th style="width:80px;"><?php _e('Orden', 'tables-codermaster'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $edit_archivo_idx = isset($_GET['edit_archivo_idx']) ? intval($_GET['edit_archivo_idx']) : null;
                    $total = count($archivos_tabla);

                    foreach ($archivos_tabla as $pos => $archivo):
                        $idx = $archivo['_idx'];

                        if ($edit_archivo_idx === $idx): ?>
                            <tr>
                                <td><?php echo $pos + 1; ?></td>
                                <td colspan="5">
                                    <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <?php wp_nonce_field('tables_codermaster_editar_archivo', 'tables_codermaster_nonce'); ?>
                                        <input type="hidden" name="edit_archivo_idx" value="<?php echo esc_attr($idx); ?>">
                                        <input type="text" name="edit_nombre" value="<?php echo esc_attr($archivo['nombre']); ?>" required placeholder="<?php _e('Nombre', 'tables-codermaster'); ?>">
                                        <input type="text" name="edit_descripcion" value="<?php echo esc_attr($archivo['descripcion']); ?>" required placeholder="<?php _e('Descripción', 'tables-codermaster'); ?>">
                                        <input type="date" name="edit_fecha_publicacion" value="<?php echo esc_attr($archivo['fecha_publicacion']); ?>" required>
                                        <input type="url" name="edit_url" value="<?php echo esc_attr($archivo['url']); ?>" required placeholder="URL">
                                        <button type="submit" name="actualizar_archivo" class="button button-primary"><?php _e('Guardar', 'tables-codermaster'); ?></button>
                                        <a href="?page=tables-codermaster&tabla_id=<?php echo esc_attr($tabla_id); ?>" class="button"><?php _e('Cancelar', 'tables-codermaster'); ?></a>
                                    </form>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td><?php echo $pos + 1; ?></td>
                                <td><?php echo esc_html($archivo['nombre']); ?></td>
                                <td><?php echo esc_html($archivo['descripcion']); ?></td>
                                <td><?php echo esc_html($archivo['fecha_publicacion']); ?></td>
                                <td>
                                    <a class="button" href="<?php echo esc_url($archivo['url']); ?>" download><?php _e('Descargar', 'tables-codermaster'); ?></a>
                                    <a href="?page=tables-codermaster&tabla_id=<?php echo esc_attr($tabla_id); ?>&edit_archivo_idx=<?php echo esc_attr($idx); ?>" class="button tc-btn-edit"><?php _e('Editar', 'tables-codermaster'); ?></a>
                                    <?php $nonce_del = wp_create_nonce('tables_codermaster_delete_' . $idx); ?>
                                    <a href="?page=tables-codermaster&tabla_id=<?php echo esc_attr($tabla_id); ?>&action=delete_archivo&archivo_idx=<?php echo esc_attr($idx); ?>&_wpnonce=<?php echo $nonce_del; ?>"
                                       class="button tc-btn-delete"
                                       onclick="return confirm('<?php _e('¿Estás seguro de eliminar este archivo?', 'tables-codermaster'); ?>');"><?php _e('Eliminar', 'tables-codermaster'); ?></a>
                                </td>
                                <td class="tc-orden-cell">
                                    <?php if ($pos > 0):
                                        $nonce_up = wp_create_nonce('tables_codermaster_mover_' . $idx); ?>
                                        <a href="?page=tables-codermaster&tabla_id=<?php echo esc_attr($tabla_id); ?>&action=move_up&archivo_idx=<?php echo esc_attr($idx); ?>&_wpnonce=<?php echo $nonce_up; ?>"
                                           class="button tc-btn-order" title="<?php _e('Subir', 'tables-codermaster'); ?>">&#9650;</a>
                                    <?php endif; ?>
                                    <?php if ($pos < $total - 1):
                                        $nonce_down = wp_create_nonce('tables_codermaster_mover_' . $idx); ?>
                                        <a href="?page=tables-codermaster&tabla_id=<?php echo esc_attr($tabla_id); ?>&action=move_down&archivo_idx=<?php echo esc_attr($idx); ?>&_wpnonce=<?php echo $nonce_down; ?>"
                                           class="button tc-btn-order" title="<?php _e('Bajar', 'tables-codermaster'); ?>">&#9660;</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif;
                    endforeach; ?>
                </tbody>
            </table>
            <p><a href="?page=tables-codermaster" class="button">&larr; <?php _e('Volver a Tablas', 'tables-codermaster'); ?></a></p>

        <?php else: ?>
            <!-- ========== LISTA DE TABLAS ========== -->
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
                    <?php foreach ($tablas as $id => $nombre): ?>
                        <tr>
                            <td><?php echo esc_html($id); ?></td>
                            <td><?php echo esc_html($nombre); ?></td>
                            <td><code>[tables_codermaster id="<?php echo esc_html($id); ?>"]</code></td>
                            <td>
                                <a href="?page=tables-codermaster&tabla_id=<?php echo esc_attr($id); ?>" class="button"><?php _e('Gestionar Archivos', 'tables-codermaster'); ?></a>
                                <a href="?page=tables-codermaster&edit_tabla_id=<?php echo esc_attr($id); ?>" class="button tc-btn-edit"><?php _e('Editar Nombre', 'tables-codermaster'); ?></a>
                                <?php $nonce_del_tabla = wp_create_nonce('tables_codermaster_delete_tabla_' . $id); ?>
                                <a href="?page=tables-codermaster&action=delete_tabla&delete_tabla_id=<?php echo esc_attr($id); ?>&_wpnonce=<?php echo $nonce_del_tabla; ?>"
                                   class="button tc-btn-delete"
                                   onclick="return confirm('<?php _e('¿Eliminar esta tabla y todos sus archivos?', 'tables-codermaster'); ?>');"><?php _e('Eliminar', 'tables-codermaster'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// =============================================
// SHORTCODE (frontend)
// =============================================

function tables_codermaster_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => '',
        'titulo' => 'Lista de Archivos'
    ], $atts);

    $tablas = get_option('tables_codermaster_tablas', []);
    $tabla_nombre = isset($tablas[$atts['id']]) ? esc_html($tablas[$atts['id']]) : 'Tabla Desconocida';

    $archivos_tabla = tables_codermaster_get_archivos_tabla($atts['id']);

    ob_start();
    ?>
    <h2><?php echo esc_html($tabla_nombre); ?></h2>
    <table class="tables-codermaster-table">
        <thead>
            <tr>
                <th>Nombre del Archivo</th>
                <th>Descripción</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($archivos_tabla as $archivo): ?>
                <tr>
                    <td><?php echo esc_html($archivo['nombre']); ?></td>
                    <td><?php echo esc_html($archivo['descripcion']); ?></td>
                    <td><a class="button" href="<?php echo esc_url($archivo['url']); ?>" download>Descargar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}
add_shortcode('tables_codermaster', 'tables_codermaster_shortcode');
