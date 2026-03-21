# gatherpress_attendee_checked_in


Fires after an attendee checks in to an event.

## Auto-generated Example

```php
add_action(
   'gatherpress_attendee_checked_in',
    function(
        int $gatherpress_event_id,
        int $gatherpress_user_id
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$gatherpress_event_id` The event post ID.
- *`int`* `$gatherpress_user_id` The user ID.

## Files

- [includes/core/classes/class-event-checkin.php:192](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event-checkin.php#L192)
```php
do_action( 'gatherpress_attendee_checked_in', $gatherpress_event_id, $gatherpress_user_id )
```



[← All Hooks](Hooks.md)
