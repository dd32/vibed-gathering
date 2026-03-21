# gatherpress_webhook_payload


Filters the webhook payload before sending.

## Auto-generated Example

```php
add_filter(
   'gatherpress_webhook_payload',
    function(
        array $payload,
        string $text,
        string $url,
        string $webhook_url
    ) {
        // Your code here.
        return $payload;
    },
    10,
    4
);
```

## Parameters

- *`array`* `$payload` The webhook payload.
- *`string`* `$text` The notification text.
- *`string`* `$url` The optional URL.
- *`string`* `$webhook_url` The webhook endpoint URL.

## Files

- [includes/core/classes/class-webhook-notifier.php:214](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-webhook-notifier.php#L214)
```php
apply_filters( 'gatherpress_webhook_payload', $payload, $text, $url, $webhook_url )
```



[← All Hooks](Hooks.md)
