# gatherpress_recurring_event_created


Fires after a recurring event instance is created.

## Auto-generated Example

```php
add_action(
   'gatherpress_recurring_event_created',
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

- [includes/core/classes/class-recurrence-generator.php:409](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-recurrence-generator.php#L409)
```php
do_action( 'gatherpress_recurring_event_created', $new_event_id, $template_event_id )
```



[← All Hooks](Hooks.md)
