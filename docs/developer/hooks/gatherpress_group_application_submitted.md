# gatherpress_group_application_submitted


Fires after a group application is submitted.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_application_submitted',
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

- *`int`* `$post_id` The application post ID.
- *`int`* `$user_id` The applicant user ID.

## Files

- [includes/core/classes/class-group-application.php:330](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-group-application.php#L330)
```php
do_action( 'gatherpress_group_application_submitted', $post_id, $user_id )
```



[← All Hooks](Hooks.md)
