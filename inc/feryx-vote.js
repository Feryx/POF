jQuery(document).ready(function($){
    function loadProds(compo) {
        if (!compo) return;
        $.post(feryxVote.ajaxurl, {action:'feryx_get_prods_vote', compoid: compo}, function(res){
            $('#feryx-vote-list-' + compo).html(res);
        });
    }

    $('.feryx-vote-list').each(function(){
        let compo = $(this).data('compo');
        loadProds(compo);
        setInterval(function(){ loadProds(compo); }, 15000);
    });

    $(document).on('click', '.stars .star', function(){
        let compo = $(this).closest('.feryx-vote-list').data('compo');
        let entry = $(this).closest('.stars').data('entry');
        let vote  = $(this).data('vote');
        $.post(feryxVote.ajaxurl, {action:'feryx_vote', compoid: compo, entryid: entry, vote: vote}, function(){
            loadProds(compo);
        });
    });
});
