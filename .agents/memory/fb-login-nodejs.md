---
name: Facebook Login Node.js HTTP
description: Key findings untuk Facebook login script via raw HTTP (useCDSWebLoginMutation)
---

# Facebook Login Node.js — Key Findings

## doc_id untuk useCDSWebLoginMutation
- `doc_id = 9807605492696448` (rev 1040986233, Juni 2026)
- Tidak ada di HTML — hanya di JS bundle; nilai dari capture file

## Public Key Enkripsi Password Facebook
- key_id=104 adalah Facebook (BUKAN Instagram key_id=87!)
- Regex: `"password_encryption":{"encryption_data":{"key_id":(\d+),"public_key":"([0-9a-f]{64})"`
- Juga di: `"caa_password_encryption_data":{"encryption_data":{"key_id":...`
- Algoritma: NaCl sealed-box (tweetnacl-sealedbox-js) + AES-GCM, format `#PWD_BROWSER:5:ts:b64`
- Buffer layout: [1, keyId, sealedKeyLen(2B LE), sealedKey(80B), gcmTag(16B), ciphertext]
- **Why:** byte[1] dari capture enc_password = 0x68 = 104, bukan 87 (Instagram)

## Token Extraction Patterns (HTML yang benar)
- lsd: `["LSD",[],{"token":"..."}` 
- __rev: `"consistency":{"rev":(\d+)`
- __hs: `"haste_session":"([^"]+)"`
- __hsi: `"hsi":"([^"]+)"`
- __spin_r/t: tidak ada di HTML → fallback ke rev/timestamp
- public_key: pola "password_encryption" atau "caa_password_encryption_data"

## Response Structure
- Response dari useCDSWebLoginMutation: `data.caa_login_web.error_code`
- 1348009/1348131 = credentials salah atau IP di-flag
- 1348110/1348117/1348118 = akun diblokir
- Checkpoint/2FA: `caa_login_web.redirect_uri` berisi URL 2FA

## Variables Structure (dari capture)
- Field `identifier` (bukan `credentials.email`)
- Field `enc_password` dan `password` keduanya terisi encrypted value
- `credential_type: "password"`
- `caa_login_request_extra_info` berisi: ab_test_data, shared_prefs_data, guid, jazoest, lgndim, lgnjs, lgnrnd, locale, login_source, lsd, timezone
- `shared_prefs_data` key 30005 = `{"w":1366,"h":768}` (object, bukan number)
- `login_source: "COMET_HEADERLESS_LOGIN"` (uppercase di input level, lowercase di caa_login_request_extra_info)

## URLSearchParams di Node 20
- JANGAN `const { URLSearchParams } = require('url')` — conflict dengan global
- URLSearchParams sudah global di Node 18+

## Facebook HTML adalah Full React App
- Tidak ada HTML form di homepage atau /login/ page
- lsd/tokens ada tapi sebagai JS inline, bukan hidden form inputs
- JS bundles tidak ter-list di HTML — tidak bisa fetch doc_id dari HTML
