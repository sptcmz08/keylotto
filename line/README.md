# LINE Integration

Public webhook endpoint:

- `/line/webhook.php`

Before using in production, set these values in `config.php` or environment variables:

- `LINE_CHANNEL_SECRET`
- `LINE_CHANNEL_ACCESS_TOKEN`

Webhook behavior:

- `GET /line/webhook.php` returns a simple ready JSON response
- `POST /line/webhook.php` accepts LINE webhook events
- Group IDs are stored in the `line_groups` table automatically when the bot joins or receives a message in a group
