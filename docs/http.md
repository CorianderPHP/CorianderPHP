# HTTP handlers

`RequestFactory::fromGlobals()` checks `Content-Length` and bounds body reads before parsing. The default limit comes from `API_MAX_BODY_BYTES` (1 MiB without configuration); pass `maxBodyBytes:` to override it for an embedded application. Oversized input raises `PayloadTooLargeException`, emitted as 413 by the starter even when JSON is malformed. Injected raw bodies may be strings or PSR streams. PHP/web-server upload limits still apply before application code runs.

Method-file handlers receive `ServerRequestInterface` and return `ResponseInterface`. Path parameters are request attributes; Nyholm's existing request implementation parses URI query parameters. `RequestFactory::fromGlobals()` attaches cookies and nested PSR uploaded files, derives headers from server variables and parses form/JSON bodies. Mutating URL-encoded requests include PUT/PATCH/DELETE; malformed JSON or a JSON body that is not an object/array raises `BadRequestException`, emitted as HTTP 400 by the starter.

Use `getAttribute('id')`, `getQueryParams()`, `getParsedBody()`, `getCookieParams()` and `getUploadedFiles()`. Validate types at application boundaries. Upload filenames/media types are client claims; application code must choose its destination and validation policy.

`Responses::html($html, $status = 200)`, `json($data, $status = 200)`, `redirect($location, $status = 303)` and `view($name, $data = [], $status = 200, $layout = true)` return PSR responses. Modify headers with `withHeader()`. JSON encoding failures throw instead of silently substituting an empty body. A redirect after a successful form POST defaults to 303. See [views](views.md) for independently inherited headers and footers and layout-free fragments.
