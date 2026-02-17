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

Every request to the task endpoints must include:
- `X-Api-Signature`: HMAC SHA256 signature of the raw request body using `EXTERNAL_API_SECRET`.

The server rejects requests if the signature is missing or invalid.

## Endpoints

### External Tasks (Restricted)

```
POST /api/v1/external/tasks
```

This endpoint is intended for tasks coming from external automated systems. It requires signature verification and creates tasks in the `external_tasks` table with `in_review` status.

### Normal Tasks

```
POST /api/v1/tasks
```

This endpoint is for creating standard tasks in the main backlog (`tasks` table). It also requires signature verification.

#### Required headers (Both endpoints)

```
Content-Type: application/json
X-Api-Signature: <hmac>
```

### Request body

```
{
  "title": "Ontruiming unit 12",
  "description": "Unit leegmaken wegens wanbetaling",
  "feedback_information": "Wat moet er gebeuren na het uitvoeren van deze taak",
  "feedback_owner_name": "Jan Janssen",
  "feedback_emails": "jan@voorbeeld.nl; kees@voorbeeld.nl",
  "location_id": 123,
  "location_external_id": 456,
  "deadline": "2025-03-05",
  "external_deadline_at": "2025-03-05 12:00:00",
  "estimated_time_minutes": 120,
  "priority": "high"
}
```

Rules:
- `title` is required.
- You must provide either `location_id` or `location_external_id`.
- `description` is optional; when omitted, it defaults to an empty string.
- `priority` must be one of the configured task priorities (for example: `high`, `normal`, `low`).
- `external_deadline_at` is an optional date/time field (you can also use `deadline` for backward compatibility).
- Newly created external tasks will have the status `in_review`.
 - `feedback_emails` is an optional comma or semicolon separated list of e‑mail addresses; max length 255 characters total.
 - `feedback_information` and `feedback_owner_name` are optional strings; max length 255 characters.

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
