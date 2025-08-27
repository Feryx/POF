<?php
function Feryx_TimeLine() {
    global $wpdb;
    $timeline_table = $wpdb->prefix . '_Feryx_timeline';
    $compos_table   = $wpdb->prefix . '_Feryx_compos';

// --- Synchronize Compo events ---
$compos_events = $wpdb->get_results("SELECT id, name, start_time FROM $compos_table");

foreach($compos_events as $compos) {
    $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $timeline_table WHERE compo_id = %d",
        $compos->id
    ));

    if ($existing_id) {
        // Exists, just update the time
        $wpdb->update(
            $timeline_table,
            ['time' => $compos->start_time],
            ['id' => $existing_id],
            ['%s'],
            ['%d']
        );
    } else {
        // Doesn't exist, insert as new
        $wpdb->insert(
            $timeline_table,
            [
                'list_id'   => $wpdb->get_var("SELECT MAX(list_id) FROM $timeline_table") + 1,
                'eventname' => $compos->name,
                'time'      => $compos->start_time,
                'mainevent' => 0,
                'compo_id'  => $compos->id
            ],
            ['%d','%s','%s','%d','%d']
        );
    }
}
    // --- Add new event ---
    if (isset($_POST['add_event'])) {
        $eventname = sanitize_text_field($_POST['eventname']);
        $time = sanitize_text_field($_POST['time']);
        $wpdb->insert($timeline_table, [
            'eventname' => $eventname,
            'time' => $time,
            'list_id' => $wpdb->get_var("SELECT MAX(list_id) FROM $timeline_table") + 1,
            'mainevent' => 0
        ]);
    }

    // Get events
    $events = $wpdb->get_results("SELECT * FROM $timeline_table ORDER BY list_id ASC");
    $ifif=0;
    ?>
    <div class="wrap">
        <h1>Timeline Editor</h1>
<p>Draggable list.<br>Shortcode:[feryx_timelinevisitoors]<br> plugin/css/timeline.css</p>
        <ul id="timeline-list">
            <?php foreach($events as $event):?>
<li data-id="<?php echo $event->id; ?>"<?php if($ifif == 1){ echo 'style="background-color: #dddddf;"'; }else{ echo 'style="background-color: #ebe6e8;"'; } ?>>
    <span class="event-type" style="background-color:<?php
        switch($event->mainevent) {
            case 0: echo "#5e5656"; break;
            case 1: echo "#41633f"; break;
            case 2: echo "#322f70"; break;
            case 3: echo "#8c1943"; break;
        }
        ?>!important; color:white;">
        <?php
        switch($event->mainevent) {
            case 0: echo "Normal"; break;
            case 1: echo "Secondary"; break;
            case 2: echo "Main"; break;
            case 3: echo "Alert"; break;
        }
        ?>
    </span>
    <span class="event-text"><strong style="padding-left:5px;"><?php echo esc_html($event->eventname); ?></strong> - <?php echo esc_html($event->time); ?></span>
    <button class="edit-event button" data-id="<?php echo $event->id; ?>">Edit</button>
    <button style="background-color: red; color:white;" class="delete-event button" data-id="<?php echo $event->id; ?>">Delete</button>
<div class="edit-form" style="display:none;">
    <input type="text" class="edit-name" value="<?php echo esc_attr($event->eventname); ?>">
    <input type="datetime-local" class="edit-time" value="<?php echo date('Y-m-d\TH:i', strtotime($event->time)); ?>">

    <select class="edit-mainevent">
        <option value="0" <?php selected($event->mainevent, 0); ?>>Normal Event</option>
        <option value="1" <?php selected($event->mainevent, 1); ?>>Secondary Event</option>
        <option value="2" <?php selected($event->mainevent, 2); ?>>Main Event</option>
        <option value="3" <?php selected($event->mainevent, 3); ?>>Alert Event</option>
    </select>

    <button class="save-edit button">Save</button>
    <button class="cancel-edit button">Cancel</button>
</div>

                <?php $ifif = 1 - $ifif; // 1<->0 toggle ?>
            <?php endforeach; ?>
        </ul>

<?php
$party_start_raw = get_option('po_party_start'); // The option format is, e.g., 2025-08-14 20:00:00
$party_start = date('Y-m-d\TH:i', strtotime($party_start_raw)); // Convert to datetime-local format
?>
<h2>Add event</h2>
<form method="post">
    <input type="text" name="eventname" placeholder="Event name" required>
    <input type="datetime-local" name="time" value="<?php echo esc_attr($party_start); ?>" required>
    <input type="submit" name="add_event" value="Add">
</form>

<form method="post" id="beginning-form"><div id="beginning-message" style="display:none; padding:10px; margin-bottom:10px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:5px;">
    Events successfully added!
</div>
    <button type="button" id="beginning-button" class="button button-primary" style="margin-top:30px;">Press for Copy events from settings</button>
</form>
    </div>

    <style>
    #timeline-list { list-style:none; padding:0; }
    #timeline-list li { padding:10px; margin:5px 0; background:#f1f1f1; cursor:move; position:relative; }
    .edit-form { margin-top:5px; }
    .delete-event { position:absolute; right:5px; top:5px; background:#f44336;color:#fff;border:none;padding:0 5px; cursor:pointer; }
    </style>
<script>
jQuery(document).ready(function($){
    $(document).on('click', '#beginning-button', function(e){
        e.preventDefault();

        $.post(ajaxurl, {
            action: 'add_beginning_events'
        }, function(response){
            // reload the page
            location.reload();
        });
    });
});

</script>
    <script>
    jQuery(document).ready(function($){
        $("#timeline-list").sortable({
            cursor: "move",
            update: function(event, ui) {
                var order = $(this).sortable('toArray', { attribute: 'data-id' });
                $.post(ajaxurl, {
                    action: 'update_timeline_order',
                    order: order
                });

            }
        });


        // Edit button
        $(".edit-event").click(function(){
            var li = $(this).closest("li");
            li.find(".edit-form").show();
            li.find(".event-text, .edit-event").hide();
        });

        $(".cancel-edit").click(function(){
            var li = $(this).closest("li");
            li.find(".edit-form").hide();
            li.find(".event-text, .edit-event").show();
        });

$(".save-edit").click(function(){
    var li = $(this).closest("li");
    var id = li.data("id");
    var name = li.find(".edit-name").val();
    var time = li.find(".edit-time").val();
    var mainevent = li.find(".edit-mainevent").val();

    $.post(ajaxurl, {
        action: 'update_timeline_item',
        id: id,
        name: name,
        time: time,
        mainevent: mainevent
    }, function(){
        // Update the text
        li.find(".event-text").html('<strong style="padding-left:5px;">' + name + '</strong> - ' + time);

        // Update the type color and text
        var typeText = '';
        var bgColor = '';
        switch(parseInt(mainevent)) {
            case 0: typeText = "Normal"; bgColor = "#5e5656"; break;
            case 1: typeText = "Secondary"; bgColor = "#41633f"; break;
            case 2: typeText = "Main"; bgColor = "#322f70"; break;
            case 3: typeText = "Alert"; bgColor = "#8c1943"; break;
        }
        li.find(".event-type").text(typeText).css("background-color", bgColor);

        // Close the edit form, restore buttons
        li.find(".edit-form").hide();
        li.find(".event-text, .edit-event").show();
    });
});


        // Delete button
        $(".delete-event").click(function(){
            if(!confirm("Are you sure you want to delete this event?")) return;
            var li = $(this).closest("li");
            var id = li.data("id");
            $.post(ajaxurl, {
                action: 'delete_timeline_item',
                id: id
            }, function(){
                li.remove();
            });
        });
    });
    </script>
    <?php
}

// Ajax save
add_action('wp_ajax_update_timeline_item', function(){
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_timeline';
    $id = intval($_POST['id']);
    $name = sanitize_text_field($_POST['name']);
    $time = sanitize_text_field($_POST['time']);
    $mainevent = intval($_POST['mainevent']);

    $wpdb->update($table, [
        'eventname' => $name,
        'time' => $time,
        'mainevent' => $mainevent
    ], ['id' => $id]);

    wp_send_json_success();
});


// Ajax delete
add_action('wp_ajax_delete_timeline_item', function(){
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_timeline';
    $id = intval($_POST['id']);
    $wpdb->delete($table, ['id'=>$id]);
    wp_send_json_success();
});
add_action('wp_ajax_add_beginning_events', function(){
    global $wpdb;
    $timeline_table = $wpdb->prefix . '_Feryx_timeline';
    $compos_table   = $wpdb->prefix . '_Feryx_compos';

    $party_start      = get_option('po_party_start');
    $doors_open       = get_option('po_doors_open');
    $doors_close      = get_option('po_doors_close');
    $opening_ceremony = get_option('po_opening_ceremony');

    $events_to_add = [
        ['eventname' => 'Party start', 'time' => $party_start],
        ['eventname' => 'Doors open', 'time' => $doors_open],
        ['eventname' => 'Doors close', 'time' => $doors_close],
        ['eventname' => 'Opening ceremony', 'time' => $opening_ceremony],
    ];

    foreach($events_to_add as $ev){
        // If the event doesn't already exist in the timeline
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $timeline_table WHERE eventname = %s",
            $ev['eventname']
        ));

        if(!$exists){
            $wpdb->insert($timeline_table, [
                'eventname' => $ev['eventname'],
                'time'      => $ev['time'],
                'list_id'   => $wpdb->get_var("SELECT MAX(list_id) FROM $timeline_table") + 1,
                'mainevent' => 0,
                'compo_id'  => 0
            ]);
        }
    }

    wp_send_json_success();
});
function fanicoo($XC) {
    switch ($XC) {
        case 0:return "event_normal";
        case 1:return "event_secondary";
        case 2:return "event_main";
        case 3:return "event_alert";
        default:return "event_normal";
    }
}

function feryx_timelineVisitors_output() {
    global $wpdb;
    $timeline_table = $wpdb->prefix . '_Feryx_timeline';
    $events = $wpdb->get_results("SELECT * FROM $timeline_table ORDER BY time ASC");

    ob_start();

    ?>
    <ul class="timeline-list">
        <?php foreach ($events as $event): ?>
            <li>
                <strong class="<?php echo fanicoo($event->mainevent); ?>"><?php echo esc_html($event->eventname); ?></strong>
                <small><?php echo esc_html($event->time); ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
    <style>
        .timeline-list {
            list-style: none;
            padding: 0;
            max-width: 800px;
            margin: auto;
        }
        .timeline-list li {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        @media (max-width: 600px) {
            .timeline-list li {
                font-size: 14px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('feryx_timelinevisitoors', 'feryx_timelineVisitors_output');
add_filter('the_content', function($content) {
    if (is_page('timeline')) {
        return feryx_timelineVisitors_output();
    }
    return $content;
});
add_action('wp', function() {
    if (is_page('timeline')) {
        wp_enqueue_style(
            'feryx-timeline-style',
            plugin_dir_url(__FILE__) . '../css/timeline.css',
            array(),
            '1.0'
        );
    }
});