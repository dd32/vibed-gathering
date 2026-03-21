# gatherpress_event_duplicated


Fires after an event is duplicated.

## Auto-generated Example

```php
add_action(
   'gatherpress_event_duplicated',
    function(
        int $gatherpress_new_id,
        int $gatherpress_source_id
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$gatherpress_new_id` The new event post ID.
- *`int`* `$gatherpress_source_id` The source event post ID.

## Files

- [includes/core/classes/class-event-template.php:175](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event-template.php#L175)
```php
do_action( 'gatherpress_event_duplicated', $gatherpress_new_id, $gatherpress_source_id )
```



[← All Hooks](Hooks.md)
