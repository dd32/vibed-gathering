# gatherpress_group_member_role_updated


Fires after a member's role is updated.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_member_role_updated',
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
- *`string`* `$role` The new role.

## Files

- [includes/core/classes/class-group.php:432](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-group.php#L432)
```php
do_action( 'gatherpress_group_member_role_updated', $this->blog_id, $user_id, $role )
```



[← All Hooks](Hooks.md)
