<?php
/**
 * Renderiza la página de administración de pilotos.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

function srl_render_drivers_admin_page() {
    global $wpdb;
    $table_drivers = $wpdb->prefix . 'srl_drivers';

    // Manejar acciones (Guardar/Editar/Eliminar)
    if ( isset($_POST['srl_driver_action']) ) {
        check_admin_referer('srl_driver_action_nonce');

        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
        $full_name = sanitize_text_field($_POST['full_name']);
        $steam_id = sanitize_text_field($_POST['steam_id']);
        $nationality = sanitize_text_field($_POST['nationality']);
        $photo_url = esc_url_raw($_POST['photo_url']);
        $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;

        if ( $_POST['srl_driver_action'] === 'save' ) {
            $data = [
                'full_name' => $full_name,
                'steam_id' => $steam_id,
                'nationality' => $nationality,
                'photo_url' => $photo_url,
                'photo_id' => $photo_id
            ];
            if ( $driver_id > 0 ) {
                $wpdb->update($table_drivers, $data, ['id' => $driver_id]);
                echo '<div class="updated"><p>Piloto actualizado correctamente.</p></div>';
            } else {
                $wpdb->insert($table_drivers, $data);
                echo '<div class="updated"><p>Piloto creado correctamente.</p></div>';
            }
        } elseif ( $_POST['srl_driver_action'] === 'delete' && $driver_id > 0 ) {
            $wpdb->delete($table_drivers, ['id' => $driver_id]);
            echo '<div class="updated"><p>Piloto eliminado.</p></div>';
        }
    }

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $edit_driver = null;

    if ( $action === 'edit' && isset($_GET['id']) ) {
        $edit_driver = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_drivers WHERE id = %d", intval($_GET['id'])));
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?>
            <?php if ( $action === 'list' ) : ?>
                <a href="<?php echo admin_url('admin.php?page=srl-drivers&action=add'); ?>" class="page-title-action">Añadir Nuevo</a>
                <button type="button" id="srl-open-merge-tool" class="page-title-action">Herramientas de Limpieza / Fusionar</button>
            <?php endif; ?>
        </h1>

        <?php if ( $action === 'list' ) :
            $drivers = $wpdb->get_results("SELECT * FROM $table_drivers ORDER BY full_name ASC");
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Foto</th>
                        <th>Nombre Completo</th>
                        <th>Steam ID</th>
                        <th>Nacionalidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $drivers ) : foreach ( $drivers as $driver ) : ?>
                        <tr>
                            <td><?php echo $driver->id; ?></td>
                            <td>
                                <?php
                                $img_src = $driver->photo_url;
                                if ( $driver->photo_id ) {
                                    $img_src = wp_get_attachment_image_url($driver->photo_id, 'thumbnail') ?: $img_src;
                                }
                                if ( $img_src ) : ?>
                                    <img src="<?php echo esc_url($img_src); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <?php else : ?>
                                    <span class="dashicons dashicons-admin-users" style="font-size: 40px; width: 40px; height: 40px; color: #ccc;"></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><a href="<?php echo admin_url('admin.php?page=srl-drivers&action=edit&id=' . $driver->id); ?>"><?php echo esc_html($driver->full_name); ?></a></strong></td>
                            <td><?php echo esc_html($driver->steam_id); ?></td>
                            <td><?php echo esc_html($driver->nationality); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=srl-drivers&action=edit&id=' . $driver->id); ?>">Editar</a> |
                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este piloto?');">
                                    <?php wp_nonce_field('srl_driver_action_nonce'); ?>
                                    <input type="hidden" name="driver_id" value="<?php echo $driver->id; ?>">
                                    <input type="hidden" name="srl_driver_action" value="delete">
                                    <button type="submit" class="button-link deletion">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="6">No se encontraron pilotos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else : // Add/Edit View ?>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <form method="post">
                            <?php wp_nonce_field('srl_driver_action_nonce'); ?>
                            <input type="hidden" name="srl_driver_action" value="save">
                            <input type="hidden" name="driver_id" value="<?php echo $edit_driver ? $edit_driver->id : 0; ?>">

                            <div class="stuffbox" style="padding: 20px;">
                                <h2>Información del Piloto</h2>
                                <table class="form-table">
                                    <tr>
                                        <th><label for="full_name">Nombre Completo</label></th>
                                        <td><input type="text" name="full_name" id="full_name" value="<?php echo $edit_driver ? esc_attr($edit_driver->full_name) : ''; ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label for="steam_id">Steam ID / GUID</label></th>
                                        <td><input type="text" name="steam_id" id="steam_id" value="<?php echo $edit_driver ? esc_attr($edit_driver->steam_id) : ''; ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="nationality">Nacionalidad</label></th>
                                        <td><input type="text" name="nationality" id="nationality" value="<?php echo $edit_driver ? esc_attr($edit_driver->nationality) : ''; ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="photo">Foto de Perfil</label></th>
                                        <td>
                                            <div id="srl-driver-photo-preview" style="margin-bottom: 10px;">
                                                <?php
                                                $preview_url = $edit_driver ? $edit_driver->photo_url : '';
                                                if ( $edit_driver && $edit_driver->photo_id ) {
                                                    $preview_url = wp_get_attachment_image_url($edit_driver->photo_id, 'thumbnail') ?: $preview_url;
                                                }
                                                if ( $preview_url ) : ?>
                                                    <img src="<?php echo esc_url($preview_url); ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                                                <?php endif; ?>
                                            </div>
                                            <input type="hidden" name="photo_id" id="srl-driver-photo-id" value="<?php echo $edit_driver ? $edit_driver->photo_id : 0; ?>">
                                            <input type="text" name="photo_url" id="srl-driver-photo-url" value="<?php echo $edit_driver ? esc_attr($edit_driver->photo_url) : ''; ?>" class="regular-text" placeholder="URL de la imagen">
                                            <button type="button" id="srl-driver-photo-btn" class="button">Seleccionar de Medios</button>
                                        </td>
                                    </tr>
                                </table>
                                <p class="submit">
                                    <input type="submit" class="button button-primary" value="<?php echo $edit_driver ? 'Actualizar Piloto' : 'Crear Piloto'; ?>">
                                    <a href="<?php echo admin_url('admin.php?page=srl-drivers'); ?>" class="button">Cancelar</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
            jQuery(document).ready(function($){
                var frame;
                $('#srl-driver-photo-btn').on('click', function(e) {
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({ title: 'Seleccionar Foto de Piloto', button: { text: 'Usar esta foto' }, multiple: false });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#srl-driver-photo-id').val(attachment.id);
                        $('#srl-driver-photo-url').val(attachment.url);
                        $('#srl-driver-photo-preview').html('<img src="'+attachment.url+'" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">');
                    });
                    frame.open();
                });
            });
            </script>
        <?php endif; ?>
    </div>

    <!-- Modal de Fusión de Pilotos -->
    <div id="srl-merge-drivers-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000;">
        <div style="background:#fff; width:90%; max-width:800px; margin:50px auto; padding:20px; border-radius:5px; position:relative; max-height: 85vh; overflow-y: auto;">
            <button type="button" class="srl-close-modal" style="position:absolute; top:10px; right:10px; border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
            <h2>Herramientas de Limpieza de Pilotos</h2>

            <div class="srl-merge-tool-tabs">
                <button type="button" class="button srl-merge-tab-btn active" data-tab="find-duplicates">Buscar Duplicados</button>
                <button type="button" class="button srl-merge-tab-btn" data-tab="manual-merge">Fusión Manual</button>
            </div>

            <hr>

            <!-- Pestaña: Buscar Duplicados -->
            <div id="srl-tab-find-duplicates" class="srl-merge-tab-content">
                <p>Busca pilotos con nombres idénticos o similares que no tengan Steam ID en conflicto.</p>
                <button type="button" id="srl-find-duplicates-btn" class="button button-primary">Buscar Ahora</button>
                <div id="srl-duplicates-results" style="margin-top:20px;"></div>
            </div>

            <!-- Pestaña: Fusión Manual -->
            <div id="srl-tab-manual-merge" class="srl-merge-tab-content" style="display:none;">
                <p>Selecciona dos pilotos para fusionar. <strong>El Piloto A conservará su perfil y recibirá los datos del Piloto B. El Piloto B será eliminado.</strong></p>
                <table class="form-table">
                    <tr>
                        <th><label>Piloto Principal (A - Se mantiene)</label></th>
                        <td>
                            <select id="srl-merge-driver-a" style="width:100%;">
                                <option value="">-- Selecciona Piloto A --</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Piloto a Eliminar (B - Se fusiona en A)</label></th>
                        <td>
                            <select id="srl-merge-driver-b" style="width:100%;">
                                <option value="">-- Selecciona Piloto B --</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <button type="button" id="srl-preview-merge-btn" class="button button-secondary">Previsualizar Fusión</button>
                <div id="srl-merge-preview-container" style="margin-top:20px;"></div>
            </div>

            <div id="srl-merge-process-response" style="margin-top:20px;"></div>
        </div>
    </div>

    <style>
        .srl-merge-tool-tabs { margin-bottom: 15px; }
        .srl-merge-tab-btn.active { background: #0073aa; color: #fff; border-color: #0073aa; }
        .srl-duplicate-group { border: 1px solid #ccd0d4; padding: 10px; margin-bottom: 10px; background: #f9f9f9; }
        .srl-duplicate-group h4 { margin-top: 0; }
        .srl-preview-box { border: 1px dashed #e60000; padding: 15px; background: #fff5f5; }
    </style>
    <?php
}
