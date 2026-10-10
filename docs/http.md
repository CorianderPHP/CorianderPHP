# HTTP handlers

Method-file handlers receive `ServerRequestInterface` and return `ResponseInterface`. Path parameters are request attributes; Nyholm's existing request implementation parses URI query parameters. `RequestFactory::fromGlobals()` attaches cookies and nested PSR uploaded files, derives headers from server variables and parses form/JSON bodies. Mutating URL-encoded requests include PUT/PATCH/DELETE; malformed JSON or a JSON body that is not an object/array raises `BadRequestException`, emitted as HTTP 400 by the starter.

Use `getAttribute('id')`, `getQueryParams()`, `getParsedBody()`, `getCookieParams()` and `getUploadedFiles()`. Validate types at application boundaries. Upload filenames/media types are client claims; application code must choose its destination and validation policy.

`Responses::html($html, $status = 200)`, `json($data, $status = 200)`, `redirect($location, $status = 303)` and `view($name, $data = [], $status = 200)` return PSR responses. Modify headers with `withHeader()`. JSON encoding failures throw instead of silently substituting an empty body. A redirect after a successful form POST defaults to 303. Layout inheritance is integrated in the views work package.
