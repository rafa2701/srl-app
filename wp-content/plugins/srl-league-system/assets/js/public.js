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

            const isR2Enabled = (typeof srl_ajax_object !== 'undefined' && srl_ajax_object.r2_enabled);
            const maxAllowedBytes = isR2Enabled ? (100 * 1024 * 1024) : ((typeof srl_ajax_object !== 'undefined' && srl_ajax_object.max_upload_size) ? parseInt(srl_ajax_object.max_upload_size, 10) : (20 * 1024 * 1024));
            const maxAllowedFormatted = isR2Enabled ? '100 MB' : ((typeof srl_ajax_object !== 'undefined' && srl_ajax_object.max_upload_size_formatted) ? srl_ajax_object.max_upload_size_formatted : '20 MB');

            if (file.size > maxAllowedBytes) {
                progressContainer.show();
                progressFill.css('width', '0%');
                statusText.html('<span style="color: #dc3545;">✖ El archivo seleccionado (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) supera el límite permitido (' + maxAllowedFormatted + '). Pega un enlace de video (YouTube/Drive/Twitch) abajo o sube un archivo más liviano.</span>');
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

            progressContainer.show();
            progressFill.css('width', '0%');
            statusText.text('Preparando subida de ' + file.name + '...');

            // Direct Cloudflare R2 Upload Workflow (Bypasses WordPress PHP server limits completely)
            if (isR2Enabled) {
                $.ajax({
                    url: srl_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'srl_get_r2_upload_url',
                        nonce: currentNonce,
                        filename: file.name,
                        filetype: file.type || 'application/octet-stream'
                    },
                    success: function(presignedRes) {
                        if (presignedRes && presignedRes.success && presignedRes.data && presignedRes.data.upload_url) {
                            const uploadUrl = presignedRes.data.upload_url;
                            const publicUrl = presignedRes.data.public_url;
                            const filename = presignedRes.data.filename || file.name;

                            // Perform direct PUT to Cloudflare R2
                            const xhr = new XMLHttpRequest();
                            xhr.open('PUT', uploadUrl, true);
                            if (file.type) {
                                xhr.setRequestHeader('Content-Type', file.type);
                            }

                            xhr.upload.addEventListener('progress', function(evt) {
                                if (evt.lengthComputable) {
                                    const percent = Math.round((evt.loaded / evt.total) * 100);
                                    progressFill.css('width', percent + '%');
                                    statusText.text('Subiendo a Cloudflare R2: ' + filename + ' (' + percent + '%)...');
                                }
                            }, false);

                            xhr.onload = function() {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    progressFill.css('width', '100%');
                                    statusText.html('<span style="color: #28a745;">✔ Subido correctamente (Cloudflare R2): <strong>' + filename + '</strong></span>');

                                    // Append URL to evidence textarea
                                    const currentVal = evidenceTextarea.val().trim();
                                    if (currentVal) {
                                        evidenceTextarea.val(currentVal + '\n' + publicUrl);
                                    } else {
                                        evidenceTextarea.val(publicUrl);
                                    }
                                } else {
                                    statusText.html('<span style="color: #dc3545;">✖ Error al guardar en Cloudflare R2 (HTTP ' + xhr.status + '). Verifica la configuración de CORS de tu bucket R2.</span>');
                                }
                            };

                            xhr.onerror = function() {
                                statusText.html('<span style="color: #dc3545;">✖ Error de conexión con Cloudflare R2. Verifica que tu bucket R2 tenga una regla de CORS que permita peticiones PUT desde tu dominio.</span>');
                            };

                            xhr.send(file);
                        } else {
                            const errMsg = (presignedRes && presignedRes.data && presignedRes.data.message) ? presignedRes.data.message : 'Error al obtener autorización de subida a R2.';
                            statusText.html('<span style="color: #dc3545;">✖ ' + errMsg + '</span>');
                        }
                    },
                    error: function(xhr) {
                        statusText.html('<span style="color: #dc3545;">✖ Error de comunicación al solicitar subida a Cloudflare R2.</span>');
                    }
                });
            } else {
                // Fallback: Local WordPress Upload via admin-ajax.php
                const formData = new FormData();
                formData.append('action', 'srl_upload_evidence_file');
                formData.append('nonce', currentNonce);
                formData.append('protest_nonce', currentNonce);
                formData.append('evidence_file', file);

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
                            statusText.html('<span style="color: #28a745;">✔ Subido correctamente (Servidor): <strong>' + response.data.filename + '</strong></span>');

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
                            errMsg = 'El servidor rechazó la subida (400 Bad Request). El archivo excede el tamaño máximo permitido por PHP (' + maxAllowedFormatted + ').';
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
                    var successMsg = '<div class="srl-notice srl-notice-success" style="background: #155724; color: #d4edda; padding: 12px; border-radius: 4px; margin-top: 10px;">' + res.data.message;
                    if (res.data.permalink) {
                        successMsg += '<br><a href="' + res.data.permalink + '" style="display: inline-block; margin-top: 8px; color: #00d2d3; font-weight: bold; text-decoration: underline;">Ver Reclamo en Vivo ↗</a>';
                    }
                    successMsg += '</div>';
                    responseDiv.html(successMsg);
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

    // ==========================================
    // Incident Protest Single Template Logic
    // ==========================================

    // 1. Slow-motion Video Speed Controls
    $(document).on('click', '.srl-speed-btn', function() {
        var videoId = $(this).data('video-id');
        var speed = parseFloat($(this).data('speed'));
        var video = document.getElementById(videoId);
        if (video) {
            video.playbackRate = speed;
            $(this).siblings('.srl-speed-btn').css({
                'background': '#222',
                'border-color': '#444',
                'color': '#ddd',
                'font-weight': 'normal'
            });
            $(this).css({
                'background': '#00d2d3',
                'border-color': '#00d2d3',
                'color': '#000',
                'font-weight': 'bold'
            });
        }
    });

    // 2. Admin Decision Choice Selection
    var currentSelectedDecision = 'proceeds';
    $(document).on('click', '.srl-decision-choice-btn', function() {
        var decision = $(this).data('decision');
        currentSelectedDecision = decision;
        if (decision === 'proceeds') {
            $('#srl-vote-btn-proceeds').css({'background': '#2ed573', 'color': '#000'});
            $('#srl-vote-btn-dismissed').css({'background': 'transparent', 'color': '#ff4757'});
        } else {
            $('#srl-vote-btn-proceeds').css({'background': 'transparent', 'color': '#2ed573'});
            $('#srl-vote-btn-dismissed').css({'background': '#ff4757', 'color': '#fff'});
        }
    });

    // 3. Submit / Update Admin Vote
    $(document).on('click', '#srl-submit-my-vote-btn', function() {
        var btn = $(this);
        var postId = btn.data('post-id');
        var notes = $('#srl_my_vote_notes').val();
        var feedback = $('#srl-my-vote-feedback');

        btn.prop('disabled', true).text('Registrando voto...');
        feedback.html('');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_cast_protest_vote',
                nonce: srl_ajax_object.nonce,
                protest_id: postId,
                decision: currentSelectedDecision,
                notes: notes
            },
            success: function(res) {
                btn.prop('disabled', false).text('Confirmar / Actualizar Mi Voto');
                if (res.success) {
                    feedback.html('<span style="color: #2ed573; font-weight: bold;">✔ ' + res.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 600);
                } else {
                    feedback.html('<span style="color: #ff4757;">✖ ' + res.data.message + '</span>');
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Confirmar / Actualizar Mi Voto');
                feedback.html('<span style="color: #ff4757;">✖ Error de red al registrar el voto.</span>');
            }
        });
    });

    // 4. Toggle External Steward Modal & Save
    $(document).on('click', '#srl-toggle-external-modal-btn', function() {
        $('#srl-external-vote-modal').slideToggle(200);
    });

    $(document).on('click', '#srl-save-external-vote-btn', function() {
        var btn = $(this);
        var postId = btn.data('post-id');
        var name = $('#srl_external_steward_name').val();
        var decision = $('#srl_external_decision').val();
        var notes = $('#srl_external_notes').val();

        if (!name) {
            alert('Por favor escribe el nombre del comisario externo.');
            return;
        }

        btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_add_external_steward_vote',
                nonce: srl_ajax_object.nonce,
                protest_id: postId,
                steward_name: name,
                decision: decision,
                notes: notes
            },
            success: function(res) {
                btn.prop('disabled', false).text('Guardar Voto Externo');
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message);
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Guardar Voto Externo');
                alert('Error de red al guardar el voto.');
            }
        });
    });

    // 5. Delete Vote
    $(document).on('click', '.srl-delete-vote-btn', function() {
        if (!confirm('¿Estás seguro de que deseas eliminar este voto?')) return;
        var btn = $(this);
        var postId = btn.data('post-id');
        var voteKey = btn.data('vote-key');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_delete_steward_vote',
                nonce: srl_ajax_object.nonce,
                protest_id: postId,
                vote_key: voteKey
            },
            success: function(res) {
                if (res.success) {
                    $('#srl-vote-row-' + voteKey).fadeOut(300, function() {
                        $(this).remove();
                        location.reload();
                    });
                } else {
                    alert(res.data.message);
                }
            }
        });
    });

    // 6. Copy AI Suggested Penalty to Ruling Input
    $(document).on('click', '#srl-copy-ai-penalty-btn', function() {
        var aiPenalty = $('#srl-ai-recommended-penalty-text').text().trim();
        if (aiPenalty) {
            $('#srl_final_sanction_input').val(aiPenalty);
        }
    });

    // 7. Save Final Ruling
    $(document).on('click', '#srl-save-final-ruling-btn', function() {
        var btn = $(this);
        var postId = btn.data('post-id');
        var status = $('#srl_final_action_status').val();
        var sanction = $('#srl_final_sanction_input').val();
        var notes = $('#srl_final_steward_notes').val();
        var feedback = $('#srl-final-ruling-feedback');

        btn.prop('disabled', true).text('Guardando dictamen...');
        feedback.html('');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_finalize_protest_ruling',
                nonce: srl_ajax_object.nonce,
                protest_id: postId,
                action_status: status,
                final_sanction: sanction,
                steward_notes: notes
            },
            success: function(res) {
                btn.prop('disabled', false).text('💾 Guardar Dictamen Oficial');
                if (res.success) {
                    feedback.html('<span style="color: #2ed573; font-weight: bold;">✔ ' + res.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 600);
                } else {
                    feedback.html('<span style="color: #ff4757;">✖ ' + res.data.message + '</span>');
                }
            },
            error: function() {
                btn.prop('disabled', false).text('💾 Guardar Dictamen Oficial');
                feedback.html('<span style="color: #ff4757;">✖ Error de red al guardar el dictamen oficial.</span>');
            }
        });
    });

    // 8. Reopen Protest
    $(document).on('click', '#srl-reopen-protest-btn', function() {
        if (!confirm('¿Reabrir este caso para nueva deliberación? El estado volverá a estar En Revisión.')) return;
        var btn = $(this);
        var postId = btn.data('post-id');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_reopen_protest',
                nonce: srl_ajax_object.nonce,
                protest_id: postId
            },
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.data.message);
                }
            }
        });
    });

});

