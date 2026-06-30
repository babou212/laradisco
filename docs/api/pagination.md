# Pagination

The API uses **two different pagination schemes**. Most collections use standard
page-based pagination; message and direct-message **timelines** use a custom
anchored-window scheme. Check each endpoint's reference entry for which applies.

## 1. Page-based (most list endpoints)

Standard Laravel `paginate()`. Request a page with `?page=N`; the response
carries `meta` (page counters) and `links` (first/last/prev/next). Used by, e.g.
`GET /api/v1/settings/members`, `/settings/roles`, `/notifications`.

```
GET /api/v1/settings/members?page=2
```

```json
{
  "data": [ /* ... */ ],
  "links": { "first": "...", "last": "...", "prev": "...", "next": "..." },
  "meta": { "current_page": 2, "from": 51, "last_page": 4, "per_page": 50, "to": 100, "total": 175 }
}
```

## 2. Anchored window (message & DM timelines)

Chat timelines are not page-numbered. Instead you anchor on a message **id** and
ask for a window around it. Applies to `GET /api/v1/channels/{channel}/messages`,
thread messages, and DM messages (`MessageController::index`,
`DirectMessageController`, `MessageWindowService`).

Query parameters (all optional; see `MessagePaginateRequest`):

| Param | Meaning |
|---|---|
| `limit` | page size, 1–100 (default 20) |
| `before` | return messages older than this message id |
| `after` | return messages newer than this message id |
| `around` | return a window centred on this message id |

With no anchor, the latest `limit` messages are returned. **Results are always
ordered oldest→newest (ascending).**

The response `meta` carries cursor flags instead of page numbers:

```json
{
  "data": [ /* messages, ascending */ ],
  "meta": {
    "has_more_before": true,
    "has_more_after": false,
    "oldest_id": "1840",
    "newest_id": "1859"
  }
}
```

To page backwards through history, request `?before=<meta.oldest_id>` repeatedly
until `meta.has_more_before` is `false`. To catch up on newer messages, request
`?after=<meta.newest_id>` until `meta.has_more_after` is `false`. Use `?around=`
to jump to a specific message (e.g. from a search result or a permalink) and load
context on both sides.
