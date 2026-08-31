jQuery(document).ready(function($) {
    const modal = $('#srl-achievements-modal');
    const modalTitle = $('#srl-modal-title');
    const modalBody = $('#srl-modal-body');
    const modalClose = $('.srl-modal-close');

    // Abrir el modal al hacer clic en una tarjeta interactiva
    $('.srl-stat-card.interactive').on('click', function() {
        const statType = $(this).data('stat');
        const driverId = $(this).data('driver-id');
        const statLabel = $(this).find('.stat-label').text();

        modalTitle.text(`Detalle de ${statLabel}`);
        modalBody.html('<p class="loading">Cargando...</p>');
        modal.fadeIn(200);

        // Petición AJAX para obtener los detalles
        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_get_achievement_details',
                nonce: srl_ajax_object.nonce,
                driver_id: driverId,
                stat_type: statType
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let listHtml = '<ul>';
                    response.data.forEach(function(item) {
                        listHtml += `<li><a href="${item.url}">${item.name}</a></li>`;
                    });
                    listHtml += '</ul>';
                    modalBody.html(listHtml);
                } else {
                    modalBody.html('<p>No se encontraron eventos para este logro.</p>');
                }
            },
            error: function() {
                modalBody.html('<p>Ocurrió un error al cargar los datos.</p>');
            }
        });
    });

    // Cerrar el modal
    function closeModal() {
        modal.fadeOut(200);
    }

    modalClose.on('click', closeModal);
    modal.on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    $(document).on('keyup', function(e) {
        if (e.key === "Escape") {
            closeModal();
        }
    });

    // --- Inicializar el ordenamiento de tablas ---
    $('.srl-sortable-table').each(function() {
        if (typeof Tablesort !== 'undefined') {
            new Tablesort(this);
        }
    });

    // Toggles para los resultados detallados
    $(document).on('click', '#srl-toggle-detailed', function() {
        const container = $('#srl-detailed-standings-container');
        const button = $(this);

        container.slideToggle(300, function() {
            if (container.is(':visible')) {
                $('html, body').animate({
                    scrollTop: container.offset().top - 100
                }, 500);
            }
        });

        button.toggleClass('active');
        if (button.hasClass('active')) {
            button.text('OCULTAR DETALLES');
        } else {
            button.text('RESULTADOS DETALLADOS');
        }
    });

    $(document).on('click', '.srl-toggle-view', function() {
        const view = $(this).data('view');
        const container = $(this).closest('#srl-detailed-standings-container');

        container.find('.srl-toggle-view').removeClass('active');
        $(this).addClass('active');

        if (view === 'points') {
            container.find('.srl-val-points').show();
            container.find('.srl-val-position').hide();
        } else {
            container.find('.srl-val-points').hide();
            container.find('.srl-val-position').show();
        }
    });

    // --- Searchable Driver Comboboxes ---
    $('.srl-driver-combobox').each(function() {
        const combobox = $(this);
        const input = combobox.find('.srl-combobox-input');
        const dropdown = combobox.find('.srl-combobox-dropdown');
        const badge = combobox.find('.srl-combobox-selected-badge');
        const nameSpan = combobox.find('.srl-selected-name');
        const hiddenInput = combobox.find('input[type="hidden"]');
        const searchBox = combobox.find('.srl-combobox-search-box');
        const items = dropdown.find('.srl-combobox-item');

        // Filter items on input
        input.on('focus input', function() {
            const query = $(this).val().toLowerCase().trim();
            dropdown.show();
            let matches = 0;

            items.each(function() {
                const name = $(this).data('name').toString().toLowerCase();
                if (name.indexOf(query) !== -1) {
                    $(this).show();
                    matches++;
                } else {
                    $(this).hide();
                }
            });

            combobox.find('.srl-no-matches').remove();
            if (matches === 0) {
                dropdown.append('<div class="srl-no-matches" style="padding: 10px; color: #888; font-size: 13px; text-align: center;">No se encontraron pilotos</div>');
            }
        });

        // Select item
        dropdown.on('click', '.srl-combobox-item', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');

            hiddenInput.val(id).trigger('change');
            nameSpan.text(name);
            dropdown.hide();
            searchBox.hide();
            badge.show();
        });

        // Clear selection
        badge.on('click', '.srl-combobox-clear-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hiddenInput.val('').trigger('change');
            badge.hide();
            input.val('');
            searchBox.show();
            input.focus();
        });
    });

    // Close combobox dropdowns on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.srl-driver-combobox').length) {
            $('.srl-combobox-dropdown').hide();
        }
    });

    // --- Evidence Drag & Drop / Direct Uploader ---
    const dropzone = $('#srl-evidence-dropzone');
    const fileInput = $('#srl-evidence-file-input');
    const progressContainer = dropzone.find('.srl-upload-progress-container');
    const progressFill = dropzone.find('.srl-upload-progress-fill');
    const statusText = dropzone.find('.srl-upload-status-text');
    const evidenceTextarea = $('#evidence_urls');

    if (dropzone.length) {
        // Prevent click events on the hidden file input from bubbling back
        fileInput.on('click', function(e) {
            e.stopPropagation();
        });

        // Trigger file browser when clicking the dropzone (unless clicking inside progress bar)
        dropzone.on('click', function(e) {
            if ($(e.target).is(fileInput) || $(e.target).closest('.srl-upload-progress-container').length > 0) {
                return;
            }
            fileInput[0].click();
        });

        dropzone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.addClass('dragover').css('border-color', '#e60000').css('background', 'rgba(230,0,0,0.05)');
        });

        dropzone.on('dragleave drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.removeClass('dragover').css('border-color', '#444').css('background', 'rgba(255,255,255,0.02)');
        });

        dropzone.on('drop', function(e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                uploadEvidenceFile(files[0]);
            }
        });

        fileInput.on('change', function() {
            if (this.files && this.files.length > 0) {
                uploadEvidenceFile(this.files[0]);
                $(this).val('');
            }
        });

        function uploadEvidenceFile(file) {
            if (!file) return;

            const serverMaxBytes = (typeof srl_ajax_object !== 'undefined' && srl_ajax_object.max_upload_size) ? parseInt(srl_ajax_object.max_upload_size, 10) : (20 * 1024 * 1024);
            const serverMaxFormatted = (typeof srl_ajax_object !== 'undefined' && srl_ajax_object.max_upload_size_formatted) ? srl_ajax_object.max_upload_size_formatted : '20 MB';

            if (file.size > serverMaxBytes) {
                progressContainer.show();
                progressFill.css('width', '0%');
                statusText.html('<span style="color: #dc3545;">✖ El archivo seleccionado (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) supera el límite de subida del servidor (' + serverMaxFormatted + '). Pega un enlace de video (YouTube/Drive/Twitch) abajo o sube un archivo más liviano.</span>');
                return;
            }

            const allowedExts = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'png', 'jpg', 'jpeg'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (allowedExts.indexOf(fileExt) === -1) {
                progressContainer.show();
                progressFill.css('width', '0%');
                statusText.html('<span style="color: #dc3545;">✖ Formato no permitido (.' + fileExt + '). Formatos admitidos: ' + allowedExts.join(', ') + '</span>');
                return;
            }

            const currentNonce = $('#protest_nonce').val() || (typeof srl_ajax_object !== 'undefined' && srl_ajax_object.nonce ? srl_ajax_object.nonce : '');

            const formData = new FormData();
            formData.append('action', 'srl_upload_evidence_file');
            formData.append('nonce', currentNonce);
            formData.append('protest_nonce', currentNonce);
            formData.append('evidence_file', file);

            progressContainer.show();
            progressFill.css('width', '0%');
            statusText.text('Subiendo ' + file.name + '...');

            $.ajax({
                url: srl_ajax_object.ajax_url + '?action=srl_upload_evidence_file&nonce=' + encodeURIComponent(currentNonce),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            progressFill.css('width', percentComplete + '%');
                            statusText.text('Subiendo ' + file.name + ' (' + percentComplete + '%)...');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.url) {
                        progressFill.css('width', '100%');
                        const storageLabel = response.data.storage === 'r2' ? 'Cloudflare R2' : 'Servidor';
                        statusText.html('<span style="color: #28a745;">✔ Subido correctamente (' + storageLabel + '): <strong>' + response.data.filename + '</strong></span>');

                        // Append URL to evidence textarea
                        const currentVal = evidenceTextarea.val().trim();
                        if (currentVal) {
                            evidenceTextarea.val(currentVal + '\n' + response.data.url);
                        } else {
                            evidenceTextarea.val(response.data.url);
                        }
                    } else {
                        const errMsg = (response && response.data && response.data.message) ? response.data.message : 'Error al procesar el archivo en el servidor.';
                        statusText.html('<span style="color: #dc3545;">✖ ' + errMsg + '</span>');
                    }
                },
                error: function(xhr) {
                    let errMsg = 'Error de red al subir el archivo.';
                    if (xhr.status === 400) {
                        errMsg = 'El servidor rechazó la subida (400 Bad Request). El archivo excede el tamaño máximo permitido por PHP (post_max_size / upload_max_filesize).';
                    } else if (xhr.status === 413) {
                        errMsg = 'El archivo supera el tamaño máximo permitido por el servidor web (413 Payload Too Large).';
                    } else if (xhr.status === 403) {
                        errMsg = 'Sesión expirada (403 Forbidden). Por favor recarga la página.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errMsg = xhr.responseJSON.data.message;
                    }
                    statusText.html('<span style="color: #dc3545;">✖ ' + errMsg + '</span>');
                }
            });
        }
    }

    // --- Reclamos Cascading Championship -> Event ---
    const eventSelect = $('#srl_protest_event_select');
    const customEventWrapper = $('#srl-custom-event-wrapper');
    const customEventInput = $('#srl_custom_event_name');

    $('#srl_protest_champ_select').on('change', function () {
        const champId = $(this).val();
        
        if (!eventSelect.length) return; // If always_free_text mode, select isn't in DOM

        customEventWrapper.hide();
        customEventInput.val('').prop('required', false);

        if (!champId) {
            eventSelect.html('<option value="">-- Primero selecciona campeonato --</option>').prop('disabled', true);
            return;
        }

        eventSelect.html('<option value="">Cargando eventos...</option>').prop('disabled', true);

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_get_events',
                nonce: srl_ajax_object.nonce,
                championship_id: champId,
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">-- Selecciona el Evento --</option>';
                    response.data.forEach(function (ev) {
                        options += '<option value="' + ev.id + '">' + ev.name + '</option>';
                    });
                    options += '<option value="custom">✏️ Escribir otro / No figura en la lista...</option>';
                    eventSelect.html(options).prop('disabled', false);
                } else {
                    // No pre-created events yet - auto reveal text input
                    eventSelect.html('<option value="custom" selected>✏️ Escribe el nombre del Gran Premio abajo...</option>').prop('disabled', false);
                    customEventWrapper.show();
                    customEventInput.prop('required', true).focus();
                }
            },
            error: function () {
                // On error, fallback gracefully to custom text input so user is never blocked
                eventSelect.html('<option value="custom" selected>✏️ Escribe el nombre del Gran Premio abajo...</option>').prop('disabled', false);
                customEventWrapper.show();
                customEventInput.prop('required', true).focus();
            }
        });
    });

    eventSelect.on('change', function () {
        if ($(this).val() === 'custom') {
            customEventWrapper.show();
            customEventInput.prop('required', true).focus();
        } else {
            customEventWrapper.hide();
            customEventInput.val('').prop('required', false);
        }
    });

    // --- Reclamo Form Submit ---
    $('#srl-public-protest-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = $('#srl-submit-protest-btn');
        const responseDiv = $('#srl-protest-response');

        // Validation for drivers
        const protestingDriver = $('#protesting_driver_id').val();
        const accusedDriver = $('#accused_driver_id').val();

        if (!protestingDriver || !accusedDriver) {
            responseDiv.html('<div class="srl-notice srl-notice-error" style="background: #721c24; color: #f8d7da; padding: 12px; border-radius: 4px; margin-top: 10px;">Debes seleccionar tanto tu piloto como el piloto acusado mediante el buscador.</div>');
            return;
        }

        if (protestingDriver === accusedDriver) {
            responseDiv.html('<div class="srl-notice srl-notice-error" style="background: #721c24; color: #f8d7da; padding: 12px; border-radius: 4px; margin-top: 10px;">El piloto demandante y el acusado no pueden ser la misma persona.</div>');
            return;
        }

        submitBtn.prop('disabled', true).text('Enviando reclamo...');
        responseDiv.html('');

        const currentNonce = $('#protest_nonce').val() || (typeof srl_ajax_object !== 'undefined' && srl_ajax_object.nonce ? srl_ajax_object.nonce : '');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=srl_submit_protest_form&nonce=' + encodeURIComponent(currentNonce),
            success: function (res) {
                submitBtn.prop('disabled', false).text('Enviar Reclamo al Comisariato');
                if (res.success) {
                    responseDiv.html('<div class="srl-notice srl-notice-success" style="background: #155724; color: #d4edda; padding: 12px; border-radius: 4px; margin-top: 10px;">' + res.data.message + '</div>');
                    form[0].reset();
                    $('.srl-combobox-selected-badge').hide();
                    $('.srl-combobox-search-box').show();
                    $('.srl-combobox-input').val('');
                    $('#protesting_driver_id').val('');
                    $('#accused_driver_id').val('');
                    $('#srl_protest_event_select').prop('disabled', true);
                    customEventWrapper.hide();
                    customEventInput.val('').prop('required', false);
                    if (progressContainer) progressContainer.hide();
                } else {
                    responseDiv.html('<div class="srl-notice srl-notice-error" style="background: #721c24; color: #f8d7da; padding: 12px; border-radius: 4px; margin-top: 10px;">' + res.data.message + '</div>');
                }
            },
            error: function () {
                submitBtn.prop('disabled', false).text('Enviar Reclamo al Comisariato');
                responseDiv.html('<div class="srl-notice srl-notice-error" style="background: #721c24; color: #f8d7da; padding: 12px; border-radius: 4px; margin-top: 10px;">Error de comunicación con el servidor.</div>');
            }
        });
    });
});
