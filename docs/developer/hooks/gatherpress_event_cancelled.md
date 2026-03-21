# gatherpress_event_cancelled


Fires after an event is cancelled.

## Auto-generated Example

```php
add_action(
   'gatherpress_event_cancelled',
    function(
        int $event_id,
        string $reason
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$event_id` The event post ID.
- *`string`* `$reason` The cancellation reason.

## Files

- [includes/core/classes/class-event-status.php:244](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event-status.php#L244)
```php
do_action( 'gatherpress_event_cancelled', $event_id, $reason )
```



[← All Hooks](Hooks.md)
