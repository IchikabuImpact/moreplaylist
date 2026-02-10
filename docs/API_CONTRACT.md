# /api/videos API Contract

## Request

`GET /api/videos`

### Query parameters

- `keyword` (optional, string)
  - Used when `feed_url` is not provided.
  - If omitted, server uses the existing default query (`Lo-Fi`).
- `feed_url` (optional, string)
  - When provided, server resolves it as playlist mode.
- `pageToken` (optional, string)
  - Passed through to YouTube API paging.

## Response

`Content-Type: application/json`

```json
{
  "videos": [
    {
      "title": "string",
      "videoId": "string",
      "thumbnail": "string|null"
    }
  ],
  "prevPageToken": "string|null",
  "nextPageToken": "string|null"
}
```

Notes:

- `prevPageToken` and `nextPageToken` are returned as `null` when not available.
- Items without `videoId` are skipped.
- If `feed_url` does not contain a valid playlist id, server returns `400` with JSON error payload.
