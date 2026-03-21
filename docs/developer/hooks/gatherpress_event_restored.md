# gatherpress_event_restored


Fires after a cancelled event is restored.

## Auto-generated Example

```php
add_action(
   'gatherpress_event_restored',
    function( int $event_id ) {
        // Your code here.
    }
);
```

## Parameters

- *`int`* `$event_id` The event post ID.

## Files

- [includes/core/classes/class-event-status.php:275](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event-status.php#L275)
```php
do_action( 'gatherpress_event_restored', $event_id )
```



[← All Hooks](Hooks.md)
