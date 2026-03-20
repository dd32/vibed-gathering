# gatherpress_group_member_added


Fires after a user joins a group.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_member_added',
    function(
        int $blog_id,
        int $user_id,
        string $role
    ) {
        // Your code here.
    },
    10,
    3
);
```

## Parameters

- *`int`* `$blog_id` The blog ID of the group.
- *`int`* `$user_id` The user ID.
- *`string`* `$role` The membership role.

## Files

- [includes/core/classes/class-group.php:363](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-group.php#L363)
```php
do_action( 'gatherpress_group_member_added', $this->blog_id, $user_id, $role )
```



[← All Hooks](Hooks.md)
