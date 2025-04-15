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

// Agregar un menú en el panel de administración
function tables_codermaster_menu() {
    add_menu_page('Tables Codermaster', 'Tables Codermaster', 'manage_options', 'tables-codermaster', 'tables_codermaster_admin_page');
}
add_action('admin_menu', 'tables_codermaster_menu');

// Página de administración
function tables_codermaster_admin_page() {
    $tablas = get_option('tables_codermaster_tablas', []);
    ?>
    <div class="wrap">
        <h1>Gestión de Tablas</h1>
        <form method="post">
            <label for="nombre_tabla">Nombre de la Tabla:</label>
            <input type="text" name="nombre_tabla" required>
            <button type="submit" name="guardar_tabla">Agregar Tabla</button>
        </form>
        <h2>Tablas Existentes</h2>
        <table class="tables-codermaster-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre de la Tabla</th>
                    <th>Shortcode</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tablas as $id => $nombre) { ?>
                    <tr>
                        <td><?php echo esc_html($id); ?></td>
                        <td><?php echo esc_html($nombre); ?></td>
                        <td>[tables_codermaster id="<?php echo esc_html($id); ?>"]</td>
                        <td><a href="?page=tables-codermaster&tabla_id=<?php echo esc_html($id); ?>" class="button">Gestionar Archivos</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    if (isset($_POST['guardar_tabla'])) {
        $id = tables_codermaster_guardar_tabla($_POST['nombre_tabla']);
        echo '<div class="updated"><p>Tabla creada con éxito. Shortcode: [tables_codermaster id="' . esc_html($id) . '"]</p></div>';
    }
    if (isset($_POST['guardar_archivo'])) {
        tables_codermaster_guardar_archivo($_POST['nombre'], $_POST['descripcion'], $_POST['url'], $_POST['fecha_publicacion'], $_POST['tabla_id']);
        echo '<div class="updated"><p>Archivo guardado con éxito.</p></div>';
    }
}