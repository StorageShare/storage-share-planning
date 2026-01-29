# External API (Task Ingest)

This document describes how to call the external API to create tasks in the planning system.

## Configuration

Add the following settings to your `.env`:

```
EXTERNAL_API_SECRET=your_shared_secret
```

Notes:
- `EXTERNAL_API_SECRET` is a shared secret used to sign requests.

## Authentication

Every request must include:
- `X-Api-Signature`: HMAC SHA256 signature of the raw request body using `EXTERNAL_API_SECRET`.

The server rejects requests if the signature is missing or invalid.

## Endpoint

```
POST /api/v1/external/tasks
```

### Required headers

```
Content-Type: application/json
X-Api-Signature: <hmac>
```

### Request body

```
{
  "title": "Ontruiming unit 12",
  "description": "Unit leegmaken wegens wanbetaling",
  "feedback_information": "Optioneel",
  "location_id": 123,
  "location_external_id": 456,
  "deadline": "2025-03-05",
  "estimated_time_minutes": 120,
  "priority": "high"
}
```

Rules:
- `title` is required.
- You must provide either `location_id` or `location_external_id`.
- `description` is optional; when omitted, it defaults to an empty string.
- `priority` must be one of the configured task priorities (for example: `high`, `normal`, `low`).

## Response

Successful response (201):

```
{
  "success": true,
  "task_id": 987
}
```

Error responses (examples):
- `401` invalid or expired signature.
- `422` validation error (missing or invalid fields).
- `500` server missing configuration.

## Signature example (PHP)

```
$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $body, $secret);
```

Make sure the exact raw body used in the HTTP request is used for signing.

## Curl example

```
payload='{"title":"Ontruiming unit 12","location_external_id":456}'
signature=$(php -r "echo hash_hmac('sha256', '${payload}', getenv('EXTERNAL_API_SECRET'));")

curl -X POST "https://your-domain.tld/api/v1/external/tasks" \
  -H "Content-Type: application/json" \
  -H "X-Api-Signature: ${signature}" \
  -d "${payload}"
```
