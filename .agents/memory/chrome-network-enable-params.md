---
name: Chrome Network.enable params encoding
description: Chrome >=112 strict validasi params Network.enable — harus object {} bukan array [].
---

# Chrome Network.enable — Params Harus Object, Bukan Array

## Rule
Saat mengirim `Network.enable` via CDP, params harus di-encode sebagai JSON **object** `{}`, bukan **array** `[]`.

## Why
Chrome >=112 strict validasi params untuk domain yang punya optional parameters (seperti Network.enable yang punya maxTotalBufferSize, maxResourceBufferSize, maxPostDataSize). PHP `json_encode([])` menghasilkan `[]` (array), bukan `{}` (object). Chrome CDP mengembalikan error: `{"code":-32602,"message":"Invalid parameters","data":"Failed to deserialize params - CBOR: map start expected"}`.

Domain lain seperti `Page.enable`, `DOM.enable`, `Runtime.enable` tidak punya parameter sama sekali — Chrome mengabaikan params mereka, jadi `[]` tidak masalah.

## How to apply
Gunakan `Protocol::networkEnable()` (bukan `Protocol::enable('Network')`) di semua tempat yang perlu mengaktifkan Network domain. Method ini menggunakan `new \stdClass()` untuk params sehingga PHP encode sebagai `{}`.

```php
// SALAH — menghasilkan "params":[]
$cdp->send(Protocol::enable('Network'));

// BENAR — menghasilkan "params":{}
$cdp->send(Protocol::networkEnable());
```
