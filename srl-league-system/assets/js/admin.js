jQuery(document).ready(function($) {

    // --- Lógica de Pestañas ---
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $('.srl-tab-content').removeClass('active');
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).addClass('active');
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

        if (!champId) {
            eventSelect.html('<option value="">-- Primero elige un campeonato --</option>');
            return;
        }

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_get_events',
                nonce: srl_ajax_object.nonce,
                championship_id: champId
            },
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">-- Elige un evento --</option>';
                    if (response.data.length > 0) {
                        response.data.forEach(function(event) {
                            options += `<option value="${event.id}">${event.name}</option>`;
                        });
                        eventSelect.prop('disabled', false);
                    } else {
                        options = '<option value="">-- No hay eventos para este campeonato --</option>';
                    }
                    eventSelect.html(options);
                } else {
                    eventSelect.html('<option value="">-- Error al cargar --</option>');
                }
            }
        });
    });

    eventSelect.on('change', function() {
        const eventId = $(this).val();
        if (eventId) {
            sessionSelect.prop('disabled', false);
            // Asegurarse de restaurar las opciones si fueron borradas por el bug anterior
            if (sessionSelect.find('option').length <= 1) {
                sessionSelect.html('<option value="">-- Selecciona sesión --</option><option value="Qualifying">Clasificación (Qualy)</option><option value="Race">Carrera (Race)</option>');
            }
        } else {
            sessionSelect.prop('disabled', true).val('');
        }
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
                    responseDiv.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>`).show();
                    form[0].reset();
                    eventSelect.prop('disabled', true).html('<option value="">-- Primero elige un campeonato --</option>');
                    sessionSelect.prop('disabled', true).html('<option value="">-- Primero elige un evento --</option>');
                } else {
                    responseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>`).show();
                }
            },
            error: function() {
                responseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado.</p></div>').show();
            },
            complete: function() {
                spinner.removeClass('is-active');
            }
        });
    });

    // --- Lógica para el formulario de importación en lote ---
    const bulkForm = $('#srl-bulk-upload-form');
    const bulkSpinner = bulkForm.find('.spinner');
    const bulkResponseDiv = $('#srl-bulk-response');

    bulkForm.on('submit', function(e) {
        e.preventDefault();
        
        const championshipId = $('#srl-bulk-championship-select').val();
        if (!championshipId) {
            alert('Por favor, selecciona un campeonato.');
            return;
        }
        if (document.getElementById('srl-bulk-results-files').files.length === 0) {
            alert('Por favor, selecciona al menos un archivo.');
            return;
        }

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
                } else {
                    bulkResponseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>`).show();
                }
            },
            error: function() {
                bulkResponseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado.</p></div>').show();
            },
            complete: function() {
                bulkSpinner.removeClass('is-active');
                bulkForm.find('input[type="submit"]').prop('disabled', false);
            }
        });
    });

    // --- Lógica para el formulario de migración histórica ---
    const historyForm = $('#srl-history-upload-form');
    const historySpinner = historyForm.find('.spinner');
    const historyResponseDiv = $('#srl-history-response');

    historyForm.on('submit', function(e) {
        e.preventDefault();
        
        if (document.getElementById('srl-history-file').files.length === 0) {
            alert('Por favor, selecciona un archivo .xlsx.');
            return;
        }

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

                if (response.success) {
                    historyResponseDiv.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>` + logHtml).show();
                } else {
                    historyResponseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>` + logHtml).show();
                }
            },
            error: function() {
                historyResponseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado de servidor. Revisa el archivo de log en el plugin para más detalles.</p></div>').show();
            },
            complete: function() {
                historySpinner.removeClass('is-active');
                historyForm.find('input[type="submit"]').prop('disabled', false);
            }
        });
    });

    // --- Lógica para el botón de eliminar resultados ---
    const deleteBtn = $('#srl-delete-results-btn');
    const deleteSpinner = deleteBtn.next('.spinner');
    const deleteResponseDiv = $('#srl-delete-response');

    deleteBtn.on('click', function() {
        if ( ! confirm('¡ATENCIÓN!\n\nEstás a punto de eliminar permanentemente todos los resultados de este evento.\n\n¿Estás seguro de que quieres continuar?') ) {
            return;
        }

        const eventId = $(this).data('event-id');
        const nonce = $('#srl_event_actions_nonce').val();

        deleteSpinner.addClass('is-active');
        deleteResponseDiv.html('').hide();
        deleteBtn.prop('disabled', true);

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_delete_event_results',
                nonce: nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success) {
                    deleteResponseDiv.html(`<div style="color: #28a745;">${response.data.message}</div>`).show();
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    deleteResponseDiv.html(`<div style="color: #dc3545;">${response.data.message}</div>`).show();
                }
            },
            error: function() {
                deleteResponseDiv.html('<div style="color: #dc3545;">Ocurrió un error inesperado.</div>').show();
            },
            complete: function() {
                deleteSpinner.removeClass('is-active');
                deleteBtn.prop('disabled', false);
            }
        });
    });

    // --- Lógica para el botón de recalcular ---
    const recalcBtn = $('#srl-recalculate-stats-btn');
    const recalcSpinner = recalcBtn.next('.spinner');
    const recalcResponseDiv = $('#srl-recalculate-response');

    recalcBtn.on('click', function() {
        if ( ! confirm('¿Estás seguro de que quieres recalcular todas las estadísticas? Este proceso puede tardar un poco.') ) {
            return;
        }

        recalcSpinner.addClass('is-active');
        recalcResponseDiv.html('').hide();
        recalcBtn.prop('disabled', true);

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_recalculate_all_stats',
                nonce: srl_ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    recalcResponseDiv.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>`).show();
                } else {
                    recalcResponseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>`).show();
                }
            },
            error: function() {
                recalcResponseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado durante el recálculo.</p></div>').show();
            },
            complete: function() {
                recalcSpinner.removeClass('is-active');
                recalcBtn.prop('disabled', false);
            }
        });
    });

    // --- Lógica para el botón de limpiar huérfanos ---
    const cleanupBtn = $('#srl-cleanup-orphans-btn');
    const cleanupSpinner = cleanupBtn.next('.spinner');
    const cleanupResponseDiv = $('#srl-cleanup-response');

    cleanupBtn.on('click', function() {
        if ( ! confirm('¿Estás seguro de que quieres buscar y eliminar resultados huérfanos? Esta acción no se puede deshacer.') ) {
            return;
        }
        cleanupSpinner.addClass('is-active');
        cleanupResponseDiv.html('').hide();
        cleanupBtn.prop('disabled', true);

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_cleanup_orphan_results',
                nonce: srl_ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    cleanupResponseDiv.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>`).show();
                } else {
                    cleanupResponseDiv.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message}</p></div>`).show();
                }
            },
            error: function() {
                cleanupResponseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado.</p></div>').show();
            },
            complete: function() {
                cleanupSpinner.removeClass('is-active');
                cleanupBtn.prop('disabled', false);
            }
        });
    });
 // --- Lógica para el botón de recalcular puntos de un campeonato ---
    const recalcPointsBtn = $('#srl-recalculate-points-btn');
    const recalcPointsSpinner = recalcPointsBtn.next('.spinner');
    const recalcPointsResponseDiv = $('#srl-recalculate-points-response');

    recalcPointsBtn.on('click', function() {
        if ( ! confirm('Esto recalculará los puntos para TODOS los eventos de este campeonato usando las reglas de puntuación guardadas actualmente. ¿Estás seguro?') ) {
            return;
        }

        const championshipId = $(this).data('championship-id');
        const nonce = $('#srl_championship_actions_nonce').val();

        recalcPointsSpinner.addClass('is-active');
        recalcPointsResponseDiv.html('').hide();
        recalcPointsBtn.prop('disabled', true);

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_recalculate_championship_points',
                nonce: nonce,
                championship_id: championshipId
            },
            success: function(response) {
                // CORRECCIÓN: Añadir validación para la respuesta del servidor
                if (response && response.data && response.data.message) {
                    if (response.success) {
                        recalcPointsResponseDiv.html(`<div style="color: #28a745;">${response.data.message}</div>`).show();
                    } else {
                        recalcPointsResponseDiv.html(`<div style="color: #dc3545;">${response.data.message}</div>`).show();
                    }
                } else {
                    // Si la respuesta no tiene el formato esperado, mostrar un error genérico y registrar la respuesta completa en la consola
                    recalcPointsResponseDiv.html('<div style="color: #dc3545;">Ocurrió un error inesperado. Revisa la consola del navegador para más detalles.</div>').show();
                    console.error("Respuesta inesperada del servidor:", response);
                }
            },
            error: function() {
                recalcPointsResponseDiv.html('<div style="color: #dc3545;">Ocurrió un error de servidor.</div>').show();
            },
            complete: function() {
                recalcPointsSpinner.removeClass('is-active');
                recalcPointsBtn.prop('disabled', false);
            }
        });
    });

    // --- Lógica para la edición de resultados en el Meta Box ---
    const resultsTable = $('.srl-results-edit-table');
    const sortableContainer = document.getElementById('srl-results-sortable');

    if (sortableContainer) {
        new Sortable(sortableContainer, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const resultIds = [];
                $('#srl-results-sortable tr').each(function() {
                    resultIds.push($(this).data('result-id'));
                });

                $.ajax({
                    url: srl_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'srl_reorder_results',
                        nonce: srl_ajax_object.nonce,
                        result_ids: resultIds
                    },
                    success: function(response) {
                        if (response.success) {
                            // Mostrar mensaje de éxito temporal antes de recargar
                            const notice = $('<div class="notice notice-success is-dismissible" style="position:fixed; top:40px; right:20px; z-index:9999; box-shadow:0 2px 5px rgba(0,0,0,0.2);"><p>Posiciones actualizadas correctamente.</p></div>');
                            $('body').append(notice);
                            setTimeout(function() {
                                notice.fadeOut(function() { $(this).remove(); location.reload(); });
                            }, 1000);
                        } else {
                            alert('Error al reordenar: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error de servidor al reordenar.');
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
        row.find('.edit-result-btn').hide();
        row.find('.save-result-btn, .cancel-result-btn, .delete-result-btn').show();
    });

    resultsTable.on('click', '.cancel-result-btn', function() {
        location.reload(); // Simplest way to reset everything
    });

    resultsTable.on('click', '.save-result-btn', function() {
        const row = $(this).closest('tr');
        const resultId = row.data('result-id');
        const gridPos = row.find('.grid-input').val();
        const laps = row.find('.laps-input').val();
        const bestLap = row.find('.best-lap-input').val();
        const totalTime = row.find('.total-time-input').val();
        const penaltySeconds = row.find('.penalty-input').val();
        const isDnf = row.find('.dnf-checkbox').is(':checked') ? 1 : 0;
        const isNc = row.find('.nc-checkbox').is(':checked') ? 1 : 0;
        const isDq = row.find('.dq-checkbox').is(':checked') ? 1 : 0;
        const nonce = $('#srl_penalties_nonce').val();
        const saveBtn = $(this);

        saveBtn.prop('disabled', true).text('...');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_save_result_details',
                nonce: nonce,
                result_id: resultId,
                grid_position: gridPos,
                laps_completed: laps,
                best_lap_time: bestLap,
                total_time: totalTime,
                penalty_seconds: penaltySeconds,
                is_dnf: isDnf,
                is_nc: isNc,
                is_dq: isDq
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    saveBtn.prop('disabled', false).text('Guardar');
                }
            },
            error: function() {
                alert('Ocurrió un error de servidor.');
                saveBtn.prop('disabled', false).text('Guardar');
            }
        });
    });

    resultsTable.on('click', '.delete-result-btn', function() {
        if (!confirm('¿Seguro que quieres eliminar este resultado individual?')) return;
        const row = $(this).closest('tr');
        const resultId = row.data('result-id');
        const nonce = srl_ajax_object.nonce;

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_delete_single_result',
                nonce: nonce,
                result_id: resultId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });

    $('#srl-add-manual-driver-btn').on('click', function() {
        const driverId = $('#srl-manual-driver-id').val();
        const sessionId = $(this).data('session-id');
        if (!driverId) { alert('Selecciona un piloto.'); return; }

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_add_manual_result',
                nonce: srl_ajax_object.nonce,
                driver_id: driverId,
                session_id: sessionId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
});
