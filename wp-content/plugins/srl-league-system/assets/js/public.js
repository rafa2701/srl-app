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
    // --- NUEVO: Inicializar el ordenamiento de tablas ---
    // Busca todas las tablas con la clase 'srl-sortable-table' y las hace ordenables.
    $('.srl-sortable-table').each(function() {
        new Tablesort(this);
        console.log('Tabla ordenada inicializada.');
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
});
