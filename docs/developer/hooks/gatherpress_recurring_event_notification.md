# gatherpress_recurring_event_notification


Fires when a recurring event notification should be sent.
Allows themes and plugins to hook into recurring event notifications.

## Auto-generated Example

```php
add_action(
   'gatherpress_recurring_event_notification',
    function(
        int $new_event_id,
        int $template_event_id
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$new_event_id` The new event post ID.
- *`int`* `$template_event_id` The template event post ID.

## Files

- [includes/core/classes/class-email.php:404](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-email.php#L404)
```php
do_action( 'gatherpress_recurring_event_notification', $new_event_id, $template_event_id )
```



[← All Hooks](Hooks.md)
