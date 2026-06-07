---
name: Chrome 138 PUT /json/new keep-alive hang
description: Chrome 138 does not close the HTTP connection after PUT /json/new response, causing file_get_contents to block indefinitely.
---

## Rule
Never use `file_get_contents()` for `PUT /json/new` against Chrome 138+.

**Why:** Chrome 138 keeps the HTTP keep-alive connection open after sending the JSON response body. `file_get_contents()` waits for the connection to close before returning — it hangs forever (or until timeout, which can be 60s+).

**How to apply:** Use a raw `fsockopen` TCP socket: send the PUT request, then read in a loop and break as soon as `webSocketDebuggerUrl` appears in the buffer, then `fclose()` the socket. This returns in < 0.4s. See `Browser::putJsonNew()` in `src/Browser/Browser.php`.

curl_exec() with CURLOPT_CUSTOMREQUEST='PUT' also works correctly (curl reads until valid JSON, closes automatically).
