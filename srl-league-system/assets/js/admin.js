jQuery(document).ready(function($) {

    const championshipSelect = $('#srl-championship-select');
    const eventSelect = $('#srl-event-select');
    const sessionSelect = $('#srl-session-select');
    const form = $('#srl-results-upload-form');
    const responseDiv = $('#srl-ajax-response');
    const spinner = form.find('.spinner');

    // 1. Cuando se cambia el campeonato, buscar sus eventos
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

    // 2. Cuando se cambia el evento, habilitar el selector de sesión
    eventSelect.on('change', function() {
        const eventId = $(this).val();
        if (eventId) {
            sessionSelect.prop('disabled', false);
            // Lógica simple para las sesiones, se puede expandir si es necesario
            sessionSelect.html(`
                <option value="">-- Elige una sesión --</option>
                <option value="Qualifying">Clasificación (Qualy)</option>
                <option value="Race">Carrera (Race)</option>
            `);
        } else {
            sessionSelect.prop('disabled', true).html('<option value="">-- Primero elige un evento --</option>');
        }
    });

    // 3. Manejar el envío del formulario
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
                    form[0].reset(); // Limpiar el formulario
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
});
