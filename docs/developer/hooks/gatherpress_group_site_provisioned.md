# gatherpress_group_site_provisioned


Fires after a group site is provisioned from an approved application.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_site_provisioned',
    function(
        int $blog_id,
        int $application_id,
        int $user_id
    ) {
        // Your code here.
    },
    10,
    3
);
```

## Parameters

- *`int`* `$blog_id` The new blog ID.
- *`int`* `$application_id` The application post ID.
- *`int`* `$user_id` The organizer user ID.

## Files

- [includes/core/classes/class-group-application.php:528](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-group-application.php#L528)
```php
do_action( 'gatherpress_group_site_provisioned', $blog_id, $application_id, $user_id )
```



[← All Hooks](Hooks.md)
