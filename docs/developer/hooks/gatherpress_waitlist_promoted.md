# gatherpress_waitlist_promoted


Fires after a user is promoted from the waiting list to attending.

## Auto-generated Example

```php
add_action(
   'gatherpress_waitlist_promoted',
    function(
        int $post_id,
        int $user_id
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$post_id` The event post ID.
- *`int`* `$user_id` The user ID who was promoted.

## Files

- [includes/core/classes/class-rsvp.php:411](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-rsvp.php#L411)
```php
do_action( 'gatherpress_waitlist_promoted', $this->event->ID, $response['userId'] )
```



[← All Hooks](Hooks.md)
