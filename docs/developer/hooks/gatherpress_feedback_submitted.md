# gatherpress_feedback_submitted


Fires after event feedback is submitted.

## Auto-generated Example

```php
add_action(
   'gatherpress_feedback_submitted',
    function(
        int $gatherpress_event_id,
        int $gatherpress_user_id,
        int $gatherpress_rating,
        int $gatherpress_comment_id
    ) {
        // Your code here.
    },
    10,
    4
);
```

## Parameters

- *`int`* `$gatherpress_event_id` The event post ID.
- *`int`* `$gatherpress_user_id` The user ID.
- *`int`* `$gatherpress_rating` The star rating (1-5).
- *`int`* `$gatherpress_comment_id` The feedback comment ID.

## Files

- [includes/core/classes/class-event-feedback.php:192](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event-feedback.php#L192)
```php
do_action( 'gatherpress_feedback_submitted', $gatherpress_event_id, $gatherpress_user_id, $gatherpress_rating, $gatherpress_comment_id )
```



[← All Hooks](Hooks.md)
