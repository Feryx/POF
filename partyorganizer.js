jQuery(function($) {

    // Toggle add new compo form
    $('#po-toggle-add').click(function() {
        $('#po-add-form').slideToggle();
    });

    // Add new compo
    $('#po-add').click(function() {
        $.post(ajaxurl, {
            action: 'po_add_compo',
            name: $('#po-new-name').val(),
            start: $('#po-new-start').val()
        }, function(resp) {
            if (resp.success) {
                location.reload();
            }
        });
    });

    // Toggle fields
    $(document).on('click', '.po-toggle', function() {
        let td = $(this);
        let row = td.closest('tr');
        let id = row.data('id');
        let field = td.data('field');

        $.post(ajaxurl, {
            action: 'po_toggle_field',
            id: id,
            field: field
        }, function(resp) {
            if (resp.success) {
                td.text(resp.data.new ? '✅' : '❌');
            }
        });
    });

    // Media picker for background image
    jQuery(document).ready(function($) {
        let file_frame;

        $(document).on('click', '.po-fanpic', function() {
            let td = $(this);
            let row = td.closest('tr');
            let id = row.data('id');

            if (file_frame) {
                file_frame.open();
                return;
            }

            file_frame = wp.media({
                title: 'Select a background',
                button: {
                    text: 'Select'
                },
                multiple: false
            });

            file_frame.on('select', function() {
                let attachment = file_frame.state().get('selection').first().toJSON();

                // Update the image immediately
                td.html('<img src="' + attachment.sizes.thumbnail.url + '" style="width:70px;height:auto;">');

                // Save to the DB via AJAX
                $.post(ajaxurl, {
                    action: 'po_update_backscreen',
                    id: id,
                    backscreen: attachment.id
                });
            });

            file_frame.open();
        });
    });


    // Edit button
    $(document).on('click', '.po-edit', function() {
        let row = $(this).closest('tr');
        let id = row.data('id');
        let name = row.find('.po-name').text();
        let start = row.find('.po-start').text();

        row.after(`
            <tr class="po-edit-row">
                <td colspan="8">
                    <input type="text" class="po-edit-name" value="${name}">
                    <input type="datetime-local" class="po-edit-start" value="${start.replace(' ', 'T')}">
                    <button class="button button-primary po-save-edit" data-id="${id}">Save</button>
                    <button class="button po-cancel-edit">Cancel</button>
                    <button class="button button-danger po-delete-compo" data-id="${id}">🗑 Delete</button>
                </td>
            </tr>
        `);
        row.hide();
    });

    // Save edit
    $(document).on('click', '.po-save-edit', function() {
        let id = $(this).data('id');
        let row = $(this).closest('tr');
        let name = row.find('.po-edit-name').val();
        let start = row.find('.po-edit-start').val();

        $.post(ajaxurl, {
            action: 'po_save_edit',
            id: id,
            name: name,
            start: start
        }, function(resp) {
            if (resp.success) {
                location.reload();
            }
        });
    });

    // Cancel edit
    $(document).on('click', '.po-cancel-edit', function() {
        $('.po-edit-row').prev().show();
        $('.po-edit-row').remove();
    });

    // Delete
    $(document).on('click', '.po-delete-compo', function() {
        if (!confirm('Are you sure you want to delete this compo?')) return;
        let id = $(this).data('id');
        $.post(ajaxurl, {
            action: 'po_delete_compo',
            id: id
        }, function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert('Error: ' + resp.data);
            }
        });
    });

});