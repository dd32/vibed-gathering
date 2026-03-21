# gatherpress_meetup_event_imported


Fires after a Meetup.com event is imported.

## Auto-generated Example

```php
add_action(
   'gatherpress_meetup_event_imported',
    function(
        int $gatherpress_post_id,
        array $gatherpress_meetup_event
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$gatherpress_post_id` The new event post ID.
- *`array`* `$gatherpress_meetup_event` The original Meetup.com data.

## Files

- [includes/core/classes/class-meetup-import.php:278](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-meetup-import.php#L278)
```php
do_action( 'gatherpress_meetup_event_imported', $gatherpress_post_id, $gatherpress_meetup_event )
```



[← All Hooks](Hooks.md)
