jQuery(document).ready(function($){
    function loadProds() {
        let compo = $('#feryx-livevote-list').data('compo');
        if (!compo) return;
        $.post(feryxVote.ajaxurl, {action:'feryx_get_prods', compoid: compo}, function(res){
            $('#feryx-livevote-list').html(res);
        });
    }

    // first load
    loadProds();

    // 15mp-repeat
    setInterval(loadProds, 15000);

    // vote (delegate!)
    $(document).on('click', '.stars .star', function(){
        let compo = $('#feryx-livevote-list').data('compo');
        let entry = $(this).closest('.stars').data('entry');
        let vote  = $(this).data('vote');
        $.post(feryxVote.ajaxurl, {action:'feryx_vote', compoid: compo, entryid: entry, vote: vote}, function(){
            loadProds();
        });
    });
});
