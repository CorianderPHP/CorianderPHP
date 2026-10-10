# Sessions

The starter configures secure cookie options but opens no session until one is needed. Public GET/HEAD and error requests can run without a session cookie or PHP's session lock, even when the browser sends an existing cookie.

`Csrf::token()`, `Csrf::input()` and `Csrf::validate()` start a session automatically. Application code and authentication middleware must start it before reading or writing `$_SESSION`:

```php
use CorianderCore\Core\Bootstrap\SessionBootstrap;

SessionBootstrap::start();
$userId = $_SESSION['user_id'] ?? null;
```

Call this inside the directory's authentication middleware before deciding whether to delegate to the next handler. `start()` is safe to call when a session is already active. Existing `startForRequest()` remains available for applications that deliberately want eager startup; the starter no longer calls it.

PHP's default session storage locks each active session until the request ends or `session_write_close()` is called. After your last session write, close it before slow work when possible:

```php
SessionBootstrap::start();
$_SESSION['last_seen'] = time();
session_write_close();
// Slow work can now run without holding this session's lock.
```

Writes after closing are not persisted unless you reopen the session first. The framework does not close sessions automatically before handlers finish, so application writes and CSRF tokens remain persistent. Cookie-authenticated mutations still require CSRF validation, including JSON endpoints. Never treat a missing session on a public page as authentication.

For custom bootstraps, configure cookies with `SessionBootstrap::configure(TrustedProxy::isSecureRequest($_SERVER))` before any session or CSRF call, and finish session work before emitting output. The starter does this automatically.
