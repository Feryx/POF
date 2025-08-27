jQuery(document).ready(function($) {
    $('#feryx-equality-check').on('click', function() {
        $.post(feryx_ajax.ajaxurl, { action: 'feryx_equality_check' }, function(response) {
            if (response.success && response.data.length > 0) {
                handleTies(response.data, 0);
            } else {
                alert("No tie in the top 3 positions 🎉");
            }
        });
    });

function handleTies(ties) {
    if (ties.length === 0) {
        alert("All ties resolved ✅");
        location.reload();
        return;
    }

    let tie = ties[0];
    let choice = confirm(
        "There's a tie:\n" +
        tie.entry1 + " vs " + tie.entry2 +
        "\nOK = " + tie.entry1 + ", Cancel = " + tie.entry2
    );

    $.post(feryx_ajax.ajaxurl, {
        action: 'feryx_resolve_tie',
        winner: choice ? tie.entry1_id : tie.entry2_id,
        compo: tie.compo_id
    }, function(resp) {
        if (resp.success) {
            // re-query for updated ties
            $.post(feryx_ajax.ajaxurl, { action: 'feryx_equality_check' }, function(res) {
                handleTies(res.data || []);
            });
        }
    });
}

});