# gatherpress_group_application_reviewed


Fires after a group application is reviewed.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_application_reviewed',
    function(
        int $post_id,
        string $action
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$post_id` The application post ID.
- *`string`* `$action` The review action taken.

## Files

- [includes/core/classes/class-group-application.php:447](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-group-application.php#L447)
```php
do_action( 'gatherpress_group_application_reviewed', $post_id, $action )
```



[← All Hooks](Hooks.md)
