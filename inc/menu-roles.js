jQuery(document).ready(function($) {
    if (typeof poUserRole === 'undefined') {
        return;
    }

    var role = poUserRole.role;

    $('li.wp-block-navigation-item a').each(function() {
        var menuName = $(this).text().trim();

        // ADMIN: admin can't vote because I think it's cheating..
        if (role === 'administrator') {
            if (menuName === 'Vote' || menuName === 'Live Vote') {
                $(this).closest('li.wp-block-navigation-item').hide();
            }
        }

        // VISITOR: see all menü

        // OTHERS: see nothing
        if (role !== 'visitor' && role !== 'administrator') {
            if (menuName === 'UPLOAD' || 
                menuName === 'Edit Products' || 
                menuName === 'Vote' || 
                menuName === 'Live Vote') {
                $(this).closest('li.wp-block-navigation-item').hide();
            }
        }
    });
});
