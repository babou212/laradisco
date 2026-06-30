# Query features: include, filter, sort

List and show endpoints support a subset of JSON:API query parameters, powered
by [`spatie/laravel-query-builder`](https://spatie.be/docs/laravel-query-builder)
(config: `config/query-builder.php`). **What is allowed is endpoint-specific** —
each endpoint's entry in the generated reference lists its permitted includes,
filters, and sorts. The rules below describe the shared syntax.

Parameter names follow JSON:API: `include`, `filter`, `sort`, `fields`. Multiple
values are comma-separated. Relationship names are converted to
`snake_case_plural`.

## `include` — embed related resources

Use `?include=` to embed relationships in the top-level `included` array. Only
relationships listed in the endpoint's `allowedIncludes` are accepted; an
unknown include returns an error. Nested includes use dot notation.

```
GET /api/v1/channels/42/messages?include=user,reactions,replyTo.user
```

A relationship is embedded only when included; otherwise it appears as a
resource-identifier (`{ "type", "id" }`) without attributes. Controllers opt
loaded relations into the document via `->includePreviouslyLoadedRelationships()`.

## `filter` — narrow a collection

Filters use `filter[name]=value`. Only declared `allowedFilters` are honoured;
many are partial (substring) matches.

```
GET /api/v1/settings/members?filter[username]=ada
```

## `sort` — order a collection

Comma-separated field list; prefix a field with `-` for descending. Only
`allowedSorts` are accepted; each endpoint has a default sort.

```
GET /api/v1/settings/roles?sort=-position,name
GET /api/v1/notifications?sort=-created_at
```

## Sparse fieldsets (`fields[type]`)

The JSON:API `fields[type]` parameter is recognised by the query-builder config
but is **not actively constrained** by endpoints today — resources return their
full declared attribute set. Do not rely on sparse fieldsets to hide fields.

## Example

```
GET /api/v1/settings/members?include=roles&filter[username]=a&sort=username&page=2
```

…returns page 2 of members whose username contains "a", sorted ascending by
username, with each member's `roles` embedded in `included`.
