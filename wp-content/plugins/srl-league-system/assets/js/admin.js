jQuery(document).ready(function($) {

    // --- Persistent Notification System ---
    function srlSetPendingNotice(message, type = 'success') {
        sessionStorage.setItem('srl_pending_notice', JSON.stringify({ message, type }));
    }

    function srlCheckPendingNotices() {
        const pending = sessionStorage.getItem('srl_pending_notice');
        if (pending) {
            const { message, type } = JSON.parse(pending);
            const noticeHtml = `<div class="notice notice-${type} is-dismissible srl-auto-notice"><p>${message}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Descartar este aviso.</span></button></div>`;
            $('.wrap h1').first().after(noticeHtml);
            sessionStorage.removeItem('srl_pending_notice');

            // Auto-dismiss after 5 seconds if not already clicked
            setTimeout(function() {
                $('.srl-auto-notice').fadeOut(function() { $(this).remove(); });
            }, 5000);
        }
    }
    srlCheckPendingNotices();

    // Helper for showing immediate or pending notices
    function srlShowNotice(message, type = 'success', reload = true) {
        if (reload) {
            srlSetPendingNotice(message, type);
            location.reload();
        } else {
            const notice = $(`<div class="notice notice-${type} is-dismissible" style="margin-top:15px;"><p>${message}</p></div>`);
            $('.wrap h1').first().after(notice);
        }
    }

    // --- Lógica de Pestañas ---
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $('.srl-tab-content').removeClass('active');
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).addClass('active');
    });

    // --- Image Upload Logic for Settings ---
    $('.srl-upload-button').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const targetId = button.data('target');
        const custom_uploader = wp.media({
            title: 'Seleccionar Imagen',
            button: { text: 'Usar esta imagen' },
            multiple: false
        }).on('select', function() {
            const attachment = custom_uploader.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.url);
            // Update preview if exists
            button.parent().find('img').attr('src', attachment.url);
        }).open();
    });

    // --- Lógica del formulario de subida manual ---
    const championshipSelect = $('#srl-championship-select');
    const eventSelect = $('#srl-event-select');
    const sessionSelect = $('#srl-session-select');
    const form = $('#srl-results-upload-form');
    const responseDiv = $('#srl-ajax-response');
    const spinner = form.find('.spinner');

    championshipSelect.on('change', function() {
        const champId = $(this).val();
        eventSelect.prop('disabled', true).html('<option value="">Cargando eventos...</option>');
        sessionSelect.prop('disabled', true).html('<option value="">-- Primero elige un evento --</option>');
        if (!champId) { eventSelect.html('<option value="">-- Primero elige un campeonato --</option>'); return; }
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_get_events', nonce: srl_ajax_object.nonce, championship_id: champId },
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">-- Elige un evento --</option>';
                    if (response.data.length > 0) {
                        response.data.forEach(function(event) { options += `<option value="${event.id}">${event.name}</option>`; });
                        eventSelect.prop('disabled', false);
                    } else { options = '<option value="">-- No hay eventos para este campeonato --</option>'; }
                    eventSelect.html(options);
                } else { eventSelect.html('<option value="">-- Error al cargar --</option>'); }
            }
        });
    });

    eventSelect.on('change', function() {
        const eventId = $(this).val();
        if (eventId) {
            sessionSelect.prop('disabled', false);
            if (sessionSelect.find('option').length <= 1) {
                sessionSelect.html('<option value="">-- Selecciona sesión --</option><option value="Qualifying">Clasificación (Qualy)</option><option value="Race">Carrera (Race)</option>');
            }
        } else { sessionSelect.prop('disabled', true).val(''); }
    });

    form.on('submit', function(e) {
        e.preventDefault();
        spinner.addClass('is-active');
        responseDiv.html('').hide();
        const formData = new FormData(this);
        formData.append('action', 'srl_upload_results_file');
        formData.append('nonce', srl_ajax_object.nonce);
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    srlShowNotice(response.data.message, 'success', true);
                } else {
                    responseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>`).show();
                }
            },
            error: function() { responseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado.</p></div>').show(); },
            complete: function() { spinner.removeClass('is-active'); }
        });
    });

    // --- Lógica para el formulario de importación en lote ---
    const bulkForm = $('#srl-bulk-upload-form');
    const bulkSpinner = bulkForm.find('.spinner');
    const bulkResponseDiv = $('#srl-bulk-response');

    bulkForm.on('submit', function(e) {
        e.preventDefault();
        const championshipId = $('#srl-bulk-championship-select').val();
        if (!championshipId) { alert('Por favor, selecciona un campeonato.'); return; }
        if (document.getElementById('srl-bulk-results-files').files.length === 0) { alert('Por favor, selecciona al menos un archivo.'); return; }
        bulkSpinner.addClass('is-active');
        bulkResponseDiv.html('').hide();
        $(this).find('input[type="submit"]').prop('disabled', true);
        const formData = new FormData(this);
        formData.append('action', 'srl_bulk_upload_results');
        formData.append('nonce', srl_ajax_object.nonce);
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    let logHtml = `<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div><h4>Registro de importación:</h4><ul style="font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ccd0d4; padding: 10px;">`;
                    response.data.log.forEach(function(line) {
                        const isError = line.startsWith('Error:');
                        logHtml += `<li style="color: ${isError ? '#dc3545' : '#28a745'};">${line}</li>`;
                    });
                    logHtml += '</ul>';
                    bulkResponseDiv.html(logHtml).show();
                    bulkForm[0].reset();
                } else { bulkResponseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>`).show(); }
            },
            error: function() { bulkResponseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado.</p></div>').show(); },
            complete: function() { bulkSpinner.removeClass('is-active'); bulkForm.find('input[type="submit"]').prop('disabled', false); }
        });
    });

    // --- Lógica para el formulario de migración histórica ---
    const historyForm = $('#srl-history-upload-form');
    const historySpinner = historyForm.find('.spinner');
    const historyResponseDiv = $('#srl-history-response');

    historyForm.on('submit', function(e) {
        e.preventDefault();
        if (document.getElementById('srl-history-file').files.length === 0) { alert('Por favor, selecciona un archivo .xlsx.'); return; }
        historySpinner.addClass('is-active');
        historyResponseDiv.html('<p>Procesando archivo... Esto puede tardar varios minutos. Por favor, no cierres esta ventana.</p>').show();
        $(this).find('input[type="submit"]').prop('disabled', true);
        const formData = new FormData(this);
        formData.append('action', 'srl_import_history_file');
        formData.append('nonce', srl_ajax_object.nonce);
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let logHtml = `<h4>Registro de Migración:</h4><div style="font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; background: #fff; border: 1px solid #ccd0d4; padding: 10px; white-space: pre-wrap;">`;
                if (typeof response.data.log === 'string') {
                    const logLines = response.data.log.split('\n');
                    logLines.forEach(function(line) {
                        if (line.trim() !== '') {
                            const isError = line.toLowerCase().includes('error:') || line.toLowerCase().includes('advertencia:');
                            logHtml += `<div style="color: ${isError ? '#dc3545' : '#000'};">${line}</div>`;
                        }
                    });
                }
                logHtml += '</div>';
                if (response.success) { historyResponseDiv.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>` + logHtml).show(); }
                else { historyResponseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>` + logHtml).show(); }
            },
            error: function() { historyResponseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado de servidor.</p></div>').show(); },
            complete: function() { historySpinner.removeClass('is-active'); historyForm.find('input[type="submit"]').prop('disabled', false); }
        });
    });

    // --- Lógica para el botón de eliminar resultados del evento ---
    $('#srl-delete-results-btn').on('click', function() {
        if (!confirm('¡ATENCIÓN!\n\nEstás a punto de eliminar permanentemente todos los resultados de este evento.\n\n¿Estás seguro?')) return;
        const btn = $(this);
        const spinner = btn.next('.spinner');
        spinner.addClass('is-active');
        btn.prop('disabled', true);
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_delete_event_results', nonce: $('#srl_event_actions_nonce').val(), event_id: btn.data('event-id') },
            success: function(response) {
                if (response.success) { srlShowNotice(response.data.message, 'success', true); }
                else { alert('Error: ' + response.data.message); }
            },
            complete: function() { spinner.removeClass('is-active'); btn.prop('disabled', false); }
        });
    });

    // --- Lógica para el botón de recalcular estadísticas globales ---
    $('#srl-recalculate-stats-btn').on('click', function() {
        if (!confirm('¿Estás seguro de que quieres recalcular todas las estadísticas?')) return;
        const btn = $(this);
        const spinner = btn.next('.spinner');
        spinner.addClass('is-active');
        btn.prop('disabled', true);
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_recalculate_all_stats', nonce: srl_ajax_object.nonce },
            success: function(response) {
                if (response.success) { srlShowNotice(response.data.message, 'success', false); }
                else { alert('Error: ' + response.data.message); }
            },
            complete: function() { spinner.removeClass('is-active'); btn.prop('disabled', false); }
        });
    });

    // --- Recalcular puntos de campeonato ---
    $('#srl-recalculate-points-btn').on('click', function() {
        if (!confirm('Esto recalculará puntos para TODOS los eventos. ¿Seguro?')) return;
        const btn = $(this);
        const spinner = btn.next('.spinner');
        spinner.addClass('is-active');
        btn.prop('disabled', true);
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_recalculate_championship_points', nonce: $('#srl_championship_actions_nonce').val(), championship_id: btn.data('championship-id') },
            success: function(response) {
                if (response.success) { srlShowNotice(response.data.message, 'success', false); }
                else { alert('Error: ' + response.data.message); }
            },
            complete: function() { spinner.removeClass('is-active'); btn.prop('disabled', false); }
        });
    });

    // --- Lógica para el Meta Box de Resultados ---
    const resultsTable = $('.srl-results-edit-table');
    const sortableContainer = document.getElementById('srl-results-sortable');

    if (sortableContainer && typeof Sortable !== 'undefined') {
        new Sortable(sortableContainer, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const resultIds = [];
                $('#srl-results-sortable tr').each(function() { resultIds.push($(this).data('result-id')); });
                $.ajax({
                    url: srl_ajax_object.ajax_url,
                    type: 'POST',
                    data: { action: 'srl_reorder_results', nonce: srl_ajax_object.nonce, result_ids: resultIds },
                    success: function(response) {
                        if (response.success) { srlShowNotice('Posiciones actualizadas correctamente.', 'success', true); }
                        else { alert('Error: ' + response.data.message); }
                    }
                });
            }
        });
    }

    resultsTable.on('click', '.edit-result-btn', function() {
        const row = $(this).closest('tr');
        row.find('.view-value').hide();
        row.find('.edit-input').show();
        row.find('.dnf-checkbox, .nc-checkbox, .dq-checkbox').prop('disabled', false);
        $(this).hide();
        row.find('.save-result-btn, .cancel-result-btn, .delete-result-btn').show();
    });

    resultsTable.on('click', '.cancel-result-btn', function() { location.reload(); });

    resultsTable.on('click', '.save-result-btn', function() {
        const row = $(this).closest('tr');
        const saveBtn = $(this);
        saveBtn.prop('disabled', true).text('...');
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_save_result_details',
                nonce: $('#srl_penalties_nonce').val(),
                result_id: row.data('result-id'),
                grid_position: row.find('.grid-input').val(),
                laps_completed: row.find('.laps-input').val(),
                best_lap_time: row.find('.best-lap-input').val(),
                total_time: row.find('.total-time-input').val(),
                penalty_seconds: row.find('.penalty-input').val(),
                is_dnf: row.find('.dnf-checkbox').is(':checked') ? 1 : 0,
                is_nc: row.find('.nc-checkbox').is(':checked') ? 1 : 0,
                is_dq: row.find('.dq-checkbox').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) { srlShowNotice('Resultado actualizado.', 'success', true); }
                else { alert('Error: ' + response.data.message); saveBtn.prop('disabled', false).text('Guardar'); }
            },
            error: function() { alert('Error de servidor.'); saveBtn.prop('disabled', false).text('Guardar'); }
        });
    });

    resultsTable.on('click', '.delete-result-btn', function() {
        if (!confirm('¿Eliminar este resultado?')) return;
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_delete_single_result', nonce: srl_ajax_object.nonce, result_id: $(this).closest('tr').data('result-id') },
            success: function(response) {
                if (response.success) { srlShowNotice('Resultado eliminado.', 'success', true); }
                else { alert('Error: ' + response.data.message); }
            }
        });
    });

    $('#srl-add-manual-driver-btn').on('click', function() {
        const driverId = $('#srl-manual-driver-id').val();
        if (!driverId) { alert('Selecciona un piloto.'); return; }
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_add_manual_result', nonce: srl_ajax_object.nonce, driver_id: driverId, session_id: $(this).data('session-id') },
            success: function(response) {
                if (response.success) { srlShowNotice('Piloto añadido.', 'success', true); }
                else { alert('Error: ' + response.data.message); }
            }
        });
    });

    $('#srl-create-manual-session-btn').on('click', function() {
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'srl_create_manual_session', nonce: srl_ajax_object.nonce, event_id: $(this).data('event-id') },
            success: function(response) {
                if (response.success) { srlShowNotice('Sesión de carrera creada.', 'success', true); }
                else { alert('Error: ' + response.data.message); }
            }
        });
    });
});
