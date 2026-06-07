'use strict';
/**
 * Facebook Login — Node.js HTTP (sesuai data flow capture CDP)
 *
 * Flow identik dengan capture fb_capture_*.json:
 *   Step 1 → GET  https://www.facebook.com/           (ambil cookies + tokens + public key)
 *   Step 2 → POST https://www.facebook.com/api/graphql/  (useCDSWebLoginMutation, doc_id dari capture)
 *   Step 3 → GET  two_step_verification/authentication/ (2FA page, jika akun aktifkan 2FA)
 *   Step 4 → POST two_step_verification/authentication/ (submit OTP kode)
 *
 * Enkripsi password: NaCl sealed-box + AES-GCM, format #PWD_BROWSER:5:ts:b64
 * (identik dengan encpass_1780792982736.js yang disertakan)
 *
 * Usage:
 *   node facebook_login.js
 *   node facebook_login.js --email=X --password=Y
 *   node facebook_login.js --email=X --password=Y --otp=123456
 *   node facebook_login.js --email=X --password=Y --output=result.json
 *   node facebook_login.js --help
 */

const axios   = require('axios');
const { wrapper }           = require('axios-cookiejar-support');
const { CookieJar }         = require('tough-cookie');
const nacl                  = require('tweetnacl-sealedbox-js');
const { webcrypto }         = require('crypto');
const fs                    = require('fs');
const crypto                = require('crypto');
const readline              = require('readline');

// ─── CLI args ─────────────────────────────────────────────────────────────────
const args = Object.fromEntries(
  process.argv.slice(2)
    .filter(a => a.startsWith('--'))
    .map(a => { const [k, ...v] = a.slice(2).split('='); return [k, v.join('=') || true]; })
);

if (args.help) {
  console.log([
    'Usage: node facebook_login.js [options]',
    '',
    'Options:',
    '  --email=EMAIL        Email atau nomor telepon (default: 083807650503)',
    '  --password=PASS      Password akun (default: Bulusari2580)',
    '  --otp=CODE           Kode OTP 2FA (jika tidak diisi, akan ditanya interaktif)',
    '  --output=FILE        Simpan hasil ke JSON file',
    '  --verbose            Tampilkan detail header, body, dan tokens',
    '  --help               Tampilkan bantuan ini',
  ].join('\n'));
  process.exit(0);
}

const EMAIL   = args.email    || '083807650503';
const PASS    = args.password || 'Bulusari2580';
const OTP     = args.otp      || null;
const OUTPUT  = args.output   || null;
const VERBOSE = !!args.verbose;

// ─── Konstanta dari capture ───────────────────────────────────────────────────
const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36';

// doc_id terverifikasi dari capture fb_capture_1780792711192.json (rev 1040986233)
const DOC_ID = '9807605492696448';

// Header dasar persis seperti di capture (urutan penting untuk fingerprint)
const BASE_HEADERS = {
  'User-Agent'                  : UA,
  'Accept-Language'             : 'en-US,en;q=0.9',
  'sec-ch-ua'                   : '"Chromium";v="138", "Google Chrome";v="138"',
  'sec-ch-ua-mobile'            : '?0',
  'sec-ch-ua-platform'          : '"Linux"',
  'sec-ch-prefers-color-scheme' : 'light',
};

// Sec-Fetch headers — wajib oleh Facebook
const SEC_FETCH_NAV = {
  'Sec-Fetch-Site' : 'none',
  'Sec-Fetch-Mode' : 'navigate',
  'Sec-Fetch-User' : '?1',
  'Sec-Fetch-Dest' : 'document',
};
const SEC_FETCH_XHR = {
  'Sec-Fetch-Site' : 'same-origin',
  'Sec-Fetch-Mode' : 'cors',
  'Sec-Fetch-Dest' : 'empty',
};

// ─── Helper: extract token dengan beberapa pola regex ─────────────────────────
function extract(html, ...patterns) {
  for (const p of patterns) {
    const m = html.match(p);
    if (m) return m[1];
  }
  return null;
}

// ─── Helper: jazoest = "2" + sum(charCodes(lsd)) ─────────────────────────────
function computeJazoest(lsd) {
  let sum = 0;
  for (const ch of lsd) sum += ch.charCodeAt(0);
  return '2' + sum;
}

// ─── Helper: UUID v4 ──────────────────────────────────────────────────────────
function uuid4() {
  return crypto.randomUUID();
}

// ─── Helper: random hex string ────────────────────────────────────────────────
function randomHex(len) {
  return crypto.randomBytes(Math.ceil(len / 2)).toString('hex').slice(0, len);
}

// ─── Helper: lgnrnd format dari capture = "172915_lMb3" ──────────────────────
function makeLgnrnd() {
  const digits = String(Math.floor(Math.random() * 900000 + 100000));
  const chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  const suffix = Array.from({ length: 4 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
  return `${digits}_${suffix}`;
}

// ─── Helper: shared_prefs_data (behavioral telemetry, format terverifikasi) ───
// Struktur didecode dari capture fb_capture_1780792711192.json
// Key 30005 = window size (object), bukan number
// Key 30006 dari capture script asli tidak ada — hapus, gunakan key yang sesuai
function makeSharedPrefs(refUrl = 'https://www.facebook.com/') {
  const now  = Date.now() / 1000;
  const ctx  = { cn: refUrl };
  const e    = (v, offset = 0) => [{ t: parseFloat((now + offset).toFixed(3)), ctx, v }];
  const data = {
    '30000' : e(false,       0.000),   // logged_in
    '30001' : e(5,           0.003),
    '30002' : e(2,           0.004),
    '30003' : e(['en-US'],   0.005),   // locale
    '30004' : e(100,         0.005),   // zoom
    '30005' : e({ w: 1366, h: 768 }, 0.006), // viewport size (object)
    '30007' : e('default',   0.007),
    '30008' : e('prompt',    0.023),
    '30012' : e('Google Inc.', 0.011),
    '30013' : e('5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 0.012),
    '30015' : e('Linux x86_64', 0.012),
    '30018' : e(8,           0.013),
    '30022' : e(false,       0.016),
    '30040' : e(-420,        0.017),   // timezone offset
    '30093' : e(0,           0.017),
    '30094' : e(UA,          0.018),
    '30095' : e(2,           0.018),
    '30106' : [
      { t: parseFloat((now).toFixed(3)),        ctx, v: false },
      { t: parseFloat((now + 0.976).toFixed(3)), ctx, v: true  },
    ],
    '30107' : e(false, -0.003),
  };
  return Buffer.from(JSON.stringify(data)).toString('base64');
}

// ─── Helper: lgndim = base64 JSON screen dims (dari capture) ─────────────────
function makeLgndim(w = 800, h = 600) {
  return Buffer.from(JSON.stringify({ w, h, aw: w, ah: h, c: 24 })).toString('base64');
}

// ─── Enkripsi password: NaCl sealed-box + AES-GCM ────────────────────────────
// Format output: #PWD_BROWSER:5:timestamp:base64(buffer)
// Buffer layout: [1, keyId, sealedKeyLen(2B LE), sealedKey, gcmTag(16B), ciphertext]
// Identik dengan encpass_1780792982736.js yang disertakan
async function encryptPassword(version, publicKeyHex, plaintext) {
  const time           = Math.floor(Date.now() / 1000);
  const plaintextBytes = new Uint8Array(Buffer.from(plaintext, 'utf-8'));
  const additionalData = new Uint8Array(Buffer.from(String(time), 'utf-8'));
  const publicKey      = new Uint8Array(Buffer.from(publicKeyHex, 'hex'));

  const aesKey    = await webcrypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
  const aesKeyRaw = await webcrypto.subtle.exportKey('raw', aesKey);
  const iv        = new Uint8Array(12); // zero IV — sama dengan encpass.js
  const sealedKey = nacl.seal(new Uint8Array(aesKeyRaw), publicKey);

  const encrypted = await webcrypto.subtle.encrypt(
    { name: 'AES-GCM', iv, additionalData },
    aesKey,
    plaintextBytes
  );

  // Pack buffer
  const buf    = new Uint8Array(100 + plaintextBytes.length);
  let   offset = 0;

  buf[offset++] = 1;
  buf[offset++] = version;
  buf[offset++] = sealedKey.length & 0xff;
  buf[offset++] = (sealedKey.length >> 8) & 0xff;
  buf.set(sealedKey, offset);
  offset += 32 + nacl.overheadLength; // sealedKey = 32 (key) + 32 (overhead)

  let encBytes = new Uint8Array(encrypted);
  const gcmTag = encBytes.slice(-16);   // últimos 16 bytes = GCM auth tag
  encBytes     = encBytes.slice(0, -16);
  buf.set(gcmTag, offset);   offset += 16;
  buf.set(encBytes, offset);

  return `#PWD_BROWSER:5:${time}:${Buffer.from(buf).toString('base64')}`;
}

// ─── Helper: strip FB anti-JSON prefix "for (;;);" ───────────────────────────
function stripFbPrefix(text) {
  return text.replace(/^for\s*\(;;\);/, '').trim();
}

// ─── Helper: readline prompt untuk input OTP interaktif ───────────────────────
function promptOtp(question) {
  return new Promise(resolve => {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    rl.question(question, answer => {
      rl.close();
      resolve(answer.trim());
    });
  });
}

// ─── Helper: extract encrypted_context dari berbagai lokasi di response ───────
// Facebook menyimpan ini di: two_factor_result.encrypted_context,
// atau sebagai query param di redirect_uri / two_step_verification URL
function extractEncCtx(tfResult, redirectUri, responseText) {
  // 1. Langsung dari two_factor_result object
  if (tfResult?.encrypted_context) return tfResult.encrypted_context;

  // 2. Dari redirect_uri / URL dalam two_factor_result
  const tryUrl = str => {
    if (!str) return null;
    try {
      const u = new URL(str.includes('://') ? str : 'https://www.facebook.com' + str);
      return u.searchParams.get('encrypted_context') || null;
    } catch { return null; }
  };
  const fromTfRedirect = tryUrl(tfResult?.redirect_uri || tfResult?.redirect_url);
  if (fromTfRedirect) return fromTfRedirect;
  const fromRedirectUri = tryUrl(redirectUri);
  if (fromRedirectUri) return fromRedirectUri;

  // 3. Regex langsung dari response text (escaped atau tidak)
  const m = responseText.match(/two_step_verification[^"]*encrypted_context=([A-Za-z0-9_\-+/=]{20,})/);
  if (m) return decodeURIComponent(m[1]);

  // 4. Cari di responseText dengan pola "encrypted_context":"..."
  const m2 = responseText.match(/"encrypted_context"\s*:\s*"([^"]{20,})"/);
  if (m2) return m2[1];

  return null;
}

// ─── Logging ──────────────────────────────────────────────────────────────────
const col = {
  reset : '\x1b[0m', green : '\x1b[32m', yellow : '\x1b[33m',
  red   : '\x1b[31m', cyan  : '\x1b[36m', bold   : '\x1b[1m', dim : '\x1b[2m',
};
const log  = (msg)           => console.log(msg);
const ok   = (msg)           => console.log(`${col.green}✓ ${msg}${col.reset}`);
const warn = (msg)           => console.log(`${col.yellow}⚠ ${msg}${col.reset}`);
const err  = (msg)           => console.log(`${col.red}✗ ${msg}${col.reset}`);
const step = (n, total, msg) => console.log(`\n→ [${n}/${total}] ${msg}`);
const kv   = (k, v)          => console.log(`   ${col.dim}${k.padEnd(14)}${col.reset}: ${v}`);

// ══════════════════════════════════════════════════════════════════════════════
async function main() {
  log(`\n${col.bold}Facebook Login — Node.js HTTP${col.reset}`);
  log('─'.repeat(54));
  kv('Email',   EMAIL);
  kv('doc_id',  DOC_ID);
  kv('Output',  OUTPUT || '(tidak disimpan)');

  // ─── Setup HTTP client dengan cookie jar ───────────────────────────────────
  const jar    = new CookieJar();
  const client = wrapper(axios.create({
    jar,
    withCredentials : true,
    timeout         : 30000,
    maxRedirects    : 0,
    validateStatus  : s => s < 500,
    headers         : BASE_HEADERS,
  }));

  // ══════════════════════════════════════════════════════════════════════════
  // STEP 1 — GET https://www.facebook.com/
  // Tujuan: dapat cookies datr+sb, extract token dinamis, dan public key enkripsi
  // ══════════════════════════════════════════════════════════════════════════
  step(1, 4, 'GET https://www.facebook.com/ (ambil cookies + tokens + public key)');

  let html;
  try {
    const r1 = await client.get('https://www.facebook.com/', {
      headers: {
        ...SEC_FETCH_NAV,
        'Accept'                   : 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Upgrade-Insecure-Requests': '1',
        'viewport-width'           : '1366',
        'dpr'                      : '1',
      },
    });
    html = typeof r1.data === 'string' ? r1.data : JSON.stringify(r1.data);
    kv('Status',  r1.status);
    kv('Length',  html.length + ' chars');
  } catch (e) {
    err('GET https://www.facebook.com/ gagal: ' + e.message);
    process.exit(1);
  }

  // ── Extract token dari HTML ────────────────────────────────────────────────
  // lsd (CSRF token) — ada di LSD bundle JS inline
  const lsd = extract(html,
    /\["LSD",\[\],\{"token":"([^"]+)"\}/,
    /name="lsd"[^>]+value="([^"]+)"/,
  );

  // __rev — revision number, ada di "consistency":{"rev":...}
  const rev = extract(html,
    /"consistency":\{"rev":(\d{9,10})/,
    /"revision":(\d{9,10})/,
  );

  // __hs — haste session
  const hs = extract(html,
    /"haste_session":"([^"]+)"/,
    /"__hs","([^"]+)"/,
  );

  // __hsi — haste session ID
  const hsi = extract(html,
    /"hsi":"([^"]+)"/,
    /"__hsi","([^"]+)"/,
  );

  // __spin_* — bundle spin info (nilai sama dengan rev)
  const spinR = extract(html, /"__spin_r",(\d+)/, /"__spin_r":(\d+)/) || rev;
  const spinT = extract(html, /"__spin_t",(\d+)/, /"__spin_t":(\d+)/) || String(Math.floor(Date.now() / 1000));
  const spinB = extract(html, /"__spin_b","([^"]+)"/)                  || 'trunk';

  // __dyn, __csr — fallback dari capture (tidak ada di HTML, dibuild JS)
  const dyn = extract(html, /"__dyn","([^"]{20,})"/) ||
    '7xeUmwlE7ibwKBAg5S1Dxu13w8CewSwMwNw9G2S0lW4o0B-q1ew6ywaq0yE7i0n24oaEd86a3a1YwBgao6C0Mo2swaO4U2zxe2GewbS361qw8Xxm16wa-0raazo7u0zE2ZwrU6qE15E6O1FwlA1HGp1yU5N90HwtU1fEhw5yw66w9O3mdw';

  const csr  = extract(html, /"__csr","([^"]{10,})"/) ||
    'hG91f1xcKyriWBHrF224ih5EDBy8G9x64Unz9A7lVazUx3byXK65mucBQu2erxa2eUG0xoy6oG3m8x2Wy9U9byo9Xz8rwSzGJ1y1kCCU-aGii8wfnCoC1nwfW783Owsdk11g4C0lO0IUmwsE040m03pa0ti088yBg2qwfi2e02uR4F28mg0naAw047DyUJ01WO055oF028EcE1cE3Sa02ka03dG05M60YA0W406MU4Hw0klSpDg0jRgG1280g2ii3O027Wi54260zHS05CE1ep80zK';

  const hsdp = extract(html, /"__hsdp","([^"]+)"/) || 'gdHY5c42h80n9w4sw6fw8u033m09Qw67w';
  const hblp = extract(html, /"__hblp","([^"]+)"/) || '0ba0efw2jE1782hwfS0x-0gm3u2HwsU0Yq0cKw3eE8U0Di0oubwCwhE4i1Vw6Ww7HwooaU3zwm82Hwt8';
  const sjsp = extract(html, /"__sjsp","([^"]+)"/) || 'gdHYtf2k42h8';

  // Session ID — dari HTML atau generate random
  const sessionId = extract(html,
    /"__s","([a-z0-9]{6}:[a-z0-9]{6}:[a-z0-9]{6})"/,
    /"sessionID":"([^"]+)"/,
  ) || (() => {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    const part  = () => Array.from({ length: 6 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
    return `${part()}:${part()}:${part()}`;
  })();

  // Public key enkripsi password Facebook — ada di "password_encryption":{"encryption_data":...}
  // Pola dari HTML: "key_id":104,"public_key":"c141e50c2ca8dbd8..."
  // key_id=104 adalah Facebook, key_id=87 adalah Instagram (BEDA kunci!)
  const keyId    = extract(html,
    /"password_encryption":\{"encryption_data":\{"key_id":(\d+),"public_key":"[0-9a-f]{64}"/,
    /"caa_password_encryption_data":\{"encryption_data":\{"key_id":(\d+)/,
    /"key_id":(\d+),"public_key":"[0-9a-f]{64}"/,
  );
  const pubKeyHex = extract(html,
    /"password_encryption":\{"encryption_data":\{"key_id":\d+,"public_key":"([0-9a-f]{64})"/,
    /"caa_password_encryption_data":\{"encryption_data":\{"key_id":\d+,"public_key":"([0-9a-f]{64})"/,
    /"key_id":1\d\d,"public_key":"([0-9a-f]{64})"/,
  );

  if (!lsd) {
    err('Tidak bisa extract LSD token — Facebook mungkin memblokir IP atau tampilkan CAPTCHA');
    if (VERBOSE) log(html.substring(0, 800));
    process.exit(1);
  }

  if (!pubKeyHex) {
    err('Tidak bisa extract public key enkripsi — HTML tidak berisi PasswordEncryption module');
    process.exit(1);
  }

  const jazoest = computeJazoest(lsd);

  kv('lsd',      lsd);
  kv('jazoest',  jazoest);
  kv('__rev',    rev   || '(fallback)');
  kv('__hs',     hs    ? hs.substring(0, 40) + '...' : '(fallback)');
  kv('__hsi',    hsi   || '(fallback)');
  kv('__s',      sessionId);
  kv('keyId',    keyId || '?');
  kv('pubKey',   pubKeyHex.substring(0, 16) + '...');

  const cookies1 = await jar.getCookies('https://www.facebook.com');
  kv('Cookies',  cookies1.map(c => c.key).join(', ') || '(kosong)');

  // ── Enkripsi password ──────────────────────────────────────────────────────
  let encPassword;
  try {
    encPassword = await encryptPassword(parseInt(keyId || '87'), pubKeyHex, PASS);
    kv('enc_pass', encPassword.substring(0, 30) + '...');
  } catch (e) {
    err('Enkripsi password gagal: ' + e.message);
    process.exit(1);
  }

  // ══════════════════════════════════════════════════════════════════════════
  // STEP 2 — POST /api/graphql/ dengan useCDSWebLoginMutation
  // Semua params, urutan, dan struktur identik dengan capture CDP
  // ══════════════════════════════════════════════════════════════════════════
  step(2, 4, 'POST /api/graphql/ (useCDSWebLoginMutation, doc_id=' + DOC_ID + ')');

  const now    = Date.now();
  const nowSec = Math.floor(now / 1000);

  // lgnrnd dari capture format: "172915_lMb3"
  const lgnrnd = makeLgnrnd();
  const lgnjs  = String(nowSec);
  const guid   = randomHex(17);

  // Variables terverifikasi dari decode capture — struktur lengkap
  const variables = {
    input: {
      actor_id             : '0',
      client_mutation_id   : '1',
      access_flow_version  : 'pre_mt_behavior',
      app                  : 'facebook',
      auth_domain_data_key : null,
      caa_login_request_extra_info: {
        ab_test_data         : 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAC',
        shared_prefs_data    : makeSharedPrefs(),
        cuid                 : '',
        guid,
        jazoest,
        lgndim               : makeLgndim(800, 600),
        lgnjs,
        lgnrnd,
        locale               : 'en_GB',
        login_source         : 'comet_headerless_login',
        lsd,
        next                 : '',
        prefill_contact_point: '',
        prefill_source       : '',
        prefill_type         : '',
        skstamp              : '',
        timezone             : '-420',
      },
      credential_type      : 'password',
      dyi_job_id           : '',
      enc_password         : { sensitive_string_value: encPassword },
      event_request_id     : uuid4(),
      identifier           : EMAIL,
      ig_web_device_id     : null,
      initial_request_id   : '1',
      lids                 : null,
      login_source         : 'COMET_HEADERLESS_LOGIN',
      next                 : null,
      passkey_payload      : null,
      password             : { sensitive_string_value: encPassword },
      persistent           : true,
      query_params         : '{}',
      trusted_device_records: '{}',
      use_uid_to_login     : false,
      waterfall_id         : uuid4(),
    },
    scale: 1,
  };

  // Body params — urutan persis dari capture (penting untuk server-side checks)
  const bodyParams = new URLSearchParams([
    ['av',                       '0'],
    ['__user',                   '0'],
    ['__a',                      '1'],
    ['__req',                    '5'],
    ['__hs',                     hs    || '20611.HYP:comet_loggedout_pkg.2.1...0'],
    ['dpr',                      '1'],
    ['__ccg',                    'EXCELLENT'],
    ['__rev',                    rev   || '1040986233'],
    ['__s',                      sessionId],
    ['__hsi',                    hsi   || String(now)],
    ['__dyn',                    dyn],
    ['__csr',                    csr],
    ['__hsdp',                   hsdp],
    ['__hblp',                   hblp],
    ['__sjsp',                   sjsp],
    ['__comet_req',              '15'],
    ['lsd',                      lsd],
    ['jazoest',                  jazoest],
    ['__spin_r',                 spinR || rev || '1040986233'],
    ['__spin_b',                 spinB],
    ['__spin_t',                 spinT],
    ['qpl_active_flow_ids',      '175125627,516759801'],
    ['fb_api_caller_class',      'RelayModern'],
    ['fb_api_req_friendly_name', 'useCDSWebLoginMutation'],
    ['server_timestamps',        'true'],
    ['variables',                JSON.stringify(variables)],
    ['doc_id',                   DOC_ID],
    ['fb_api_analytics_tags',    '["qpl_active_flow_ids=175125627,516759801"]'],
  ]);

  // Header POST — persis dari capture
  const postHeaders = {
    ...BASE_HEADERS,
    ...SEC_FETCH_XHR,
    'Accept'             : '*/*',
    'Content-Type'       : 'application/x-www-form-urlencoded',
    'Origin'             : 'https://www.facebook.com',
    'Referer'            : 'https://www.facebook.com/',
    'X-FB-Friendly-Name' : 'useCDSWebLoginMutation',
    'X-ASBD-ID'          : '359341',
    'X-FB-LSD'           : lsd,
  };

  if (VERBOSE) {
    log('\n[VERBOSE] Request headers:');
    for (const [k, v] of Object.entries(postHeaders)) kv(k, v);
    log('\n[VERBOSE] Body params:');
    for (const [k, v] of bodyParams) {
      if (k === 'variables') kv(k, v.substring(0, 150) + '...');
      else if (k === '__dyn' || k === '__csr') kv(k, v.substring(0, 40) + '...');
      else kv(k, v);
    }
  }

  let r2, responseText;
  try {
    r2 = await client.post('https://www.facebook.com/api/graphql/', bodyParams.toString(), {
      headers       : postHeaders,
      maxRedirects  : 5,
      validateStatus: s => s < 600,
    });
    responseText = typeof r2.data === 'string' ? r2.data : JSON.stringify(r2.data);
    kv('Status',  r2.status);
    kv('Length',  responseText.length + ' chars');
  } catch (e) {
    err('POST /api/graphql/ gagal: ' + e.message);
    process.exit(1);
  }

  // ─── Parse response ────────────────────────────────────────────────────────
  const cleanJson = stripFbPrefix(responseText);
  let parsed = null;
  try { parsed = JSON.parse(cleanJson); } catch { /* mungkin HTML redirect */ }

  if (VERBOSE) {
    log('\n[VERBOSE] Response (600 chars):');
    log(responseText.substring(0, 600));
  }

  // ─── Cek cookies dan data login ────────────────────────────────────────────
  const cookies2  = await jar.getCookies('https://www.facebook.com');
  const cUser     = cookies2.find(c => c.key === 'c_user')?.value;
  const xs        = cookies2.find(c => c.key === 'xs')?.value;
  const fbdtsg    = extract(responseText, /"fb_dtsg","([^"]+)"/);

  // Ekstrak caa_login_web dari response (struktur baru useCDSWebLoginMutation)
  const caaLogin  = parsed?.data?.caa_login_web || null;
  const errCode   = caaLogin?.error_code ?? null;
  const redirectUri = caaLogin?.redirect_uri
    || extract(responseText, /"redirect_uri":"([^"]+)"/)
    || null;
  const tfResult  = caaLogin?.two_factor_result;

  // ─── Verdict ───────────────────────────────────────────────────────────────
  log('\n' + '─'.repeat(54));

  let verdict = 'unknown';

  // 1. Login berhasil — ada c_user cookie atau redirect_uri tanpa error
  if (cUser) {
    ok(`LOGIN BERHASIL — c_user: ${cUser}`);
    if (xs) kv('xs', xs.substring(0, 20) + '...');
    verdict = 'success';

  // 2. Redirect ke 2FA / checkpoint
  } else if (
    (redirectUri && (redirectUri.includes('two_step') || redirectUri.includes('checkpoint'))) ||
    r2.status === 302 ||
    (r2.headers?.location || '').includes('checkpoint') ||
    (r2.headers?.location || '').includes('two_step_verification') ||
    tfResult != null ||
    responseText.includes('two_step_verification')
  ) {
    const loc = redirectUri || r2.headers?.location || 'two_step_verification';
    warn('CHECKPOINT — Akun butuh verifikasi 2FA/OTP');
    kv('Redirect', decodeURIComponent(loc).substring(0, 80));
    kv('Artinya',  'Password BENAR, akun aktifkan 2FA');
    verdict = 'checkpoint';

    if (VERBOSE && tfResult) {
      log('\n[VERBOSE] two_factor_result:');
      log(JSON.stringify(tfResult, null, 2).substring(0, 600));
    }

    // ── Step 3 & 4: Submit OTP ─────────────────────────────────────────────
    const encCtx = extractEncCtx(tfResult, loc, responseText);

    if (!encCtx) {
      warn('Tidak bisa extract encrypted_context — skip 2FA submission');
      warn('Jalankan dengan --verbose untuk lihat two_factor_result lengkap');
    } else {
      kv('enc_ctx', encCtx.substring(0, 32) + '...');

      // ── STEP 3 — GET halaman 2FA ─────────────────────────────────────────
      const twoFaUrl = `https://www.facebook.com/two_step_verification/authentication/?encrypted_context=${encodeURIComponent(encCtx)}&flow=pre_authentication&next`;
      step(3, 4, 'GET two_step_verification/authentication/ (ambil token 2FA)');

      let html2fa = '';
      try {
        const r3 = await client.get(twoFaUrl, {
          maxRedirects: 5,
          headers: {
            ...SEC_FETCH_NAV,
            'Accept'                   : 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Upgrade-Insecure-Requests': '1',
            'Referer'                  : 'https://www.facebook.com/',
          },
        });
        html2fa = typeof r3.data === 'string' ? r3.data : JSON.stringify(r3.data);
        kv('Status', r3.status);
        kv('Length', html2fa.length + ' chars');
      } catch (e3) {
        err('GET 2FA page gagal: ' + e3.message);
      }

      // Ekstrak tokens dari halaman 2FA
      const fbDtsg2 = extract(html2fa,
        /"fb_dtsg","([^"]+)"/,
        /"fb_dtsg_ag","([^"]+)"/,
        /name="fb_dtsg"[^>]+value="([^"]+)"/,
        /"DTSGInitData"[^}]*"token":"([^"]+)"/,
      );
      const nh2   = extract(html2fa,
        /"nh":"([^"]+)"/,
        /name="nh"[^>]+value="([^"]+)"/,
      );
      const lsd2  = extract(html2fa,
        /\["LSD",\[\],\{"token":"([^"]+)"\}/,
        /name="lsd"[^>]+value="([^"]+)"/,
      );
      const uid2  = extract(html2fa,
        /"uid":"?(\d+)"?/,
        /name="uid"[^>]+value="(\d+)"/,
        /"user_id":"?(\d+)"?/,
      );

      if (VERBOSE) {
        kv('fb_dtsg', fbDtsg2 || '(tidak ditemukan)');
        kv('nh',      nh2     || '(tidak ditemukan)');
        kv('lsd',     lsd2    || '(tidak ditemukan)');
        kv('uid',     uid2    || '(tidak ditemukan)');
      }

      if (!fbDtsg2 && !nh2) {
        warn('Tidak bisa extract token dari 2FA page — encrypted_context mungkin expired');
        warn('Pastikan menjalankan script langsung setelah masuk ke checkpoint');
      } else {
        // ── STEP 4 — Submit OTP ────────────────────────────────────────────
        step(4, 4, 'POST two_step_verification/authentication/ (submit kode OTP)');

        // Minta OTP dari user (interaktif atau --otp arg)
        const otpCode = OTP || await promptOtp('\n   Masukkan kode OTP 2FA: ');

        if (!otpCode || !/^\d{4,8}$/.test(otpCode)) {
          err('Kode OTP tidak valid (harus 4-8 digit angka)');
        } else {
          kv('OTP', otpCode);

          // POST OTP ke Facebook
          const otpBody = new URLSearchParams();
          otpBody.set('approvals_code',    otpCode);
          otpBody.set('encrypted_context', encCtx);
          if (fbDtsg2) otpBody.set('fb_dtsg', fbDtsg2);
          if (nh2)     otpBody.set('nh',      nh2);
          if (lsd2)    otpBody.set('lsd',     lsd2);
          if (uid2)    otpBody.set('uid',     uid2);
          otpBody.set('submit[Submit Code]', 'Submit Code');
          otpBody.set('__a',  '1');
          otpBody.set('__req', 'a');

          let r4, otpResponseText = '';
          try {
            r4 = await client.post(
              'https://www.facebook.com/two_step_verification/authentication/',
              otpBody,
              {
                maxRedirects: 5,
                headers: {
                  ...SEC_FETCH_XHR,
                  'Content-Type' : 'application/x-www-form-urlencoded',
                  'Referer'      : twoFaUrl,
                  'Origin'       : 'https://www.facebook.com',
                  'X-Requested-With': 'XMLHttpRequest',
                },
              }
            );
            otpResponseText = typeof r4.data === 'string' ? r4.data : JSON.stringify(r4.data);
            kv('Status', r4.status);
            kv('Length', otpResponseText.length + ' chars');
            if (VERBOSE) {
              log('[VERBOSE] OTP Response (400 chars):');
              log(otpResponseText.substring(0, 400));
            }
          } catch (e4) {
            err('POST OTP gagal: ' + e4.message);
          }

          // ── Cek hasil OTP ────────────────────────────────────────────────
          const cookies3 = await jar.getCookies('https://www.facebook.com');
          const cUser2   = cookies3.find(c => c.key === 'c_user')?.value;
          const xs2      = cookies3.find(c => c.key === 'xs')?.value;

          log('\n' + '─'.repeat(54));
          if (cUser2) {
            ok(`LOGIN BERHASIL — c_user: ${cUser2}`);
            if (xs2) kv('xs', xs2.substring(0, 24) + '...');
            verdict = 'success_2fa';
          } else if (
            otpResponseText.includes('incorrect') ||
            otpResponseText.includes('invalid') ||
            otpResponseText.includes('wrong') ||
            otpResponseText.includes('error')
          ) {
            err('KODE OTP SALAH atau expired — coba lagi dengan kode baru');
            verdict = 'wrong_otp';
          } else if (
            otpResponseText.includes('checkpoint') ||
            otpResponseText.includes('two_step')
          ) {
            warn('2FA berhasil tapi masih ada checkpoint lanjutan');
            verdict = 'checkpoint_next';
          } else {
            warn('OTP response tidak jelas — periksa manual');
            if (!VERBOSE) log('  Preview: ' + otpResponseText.substring(0, 200));
            verdict = 'otp_unknown';
          }

          // Update cookies display
          log('\nCookies setelah 2FA:');
          for (const ck of cookies3) {
            const val = ck.value.length > 40 ? ck.value.substring(0, 40) + '…' : ck.value;
            log(`  ${ck.key.padEnd(16)} = ${val}`);
          }
        }
      }
    }

  // 3. caa_login_web error codes:
  //    1348009, 1348131 = "login information incorrect" (wrong user/pass atau IP flagged)
  //    1348110           = account disabled
  } else if (errCode != null) {
    const knownWrong = [1348009, 1348131, 1348012, 1348150];
    const knownBlock = [1348110, 1348118, 1348117];
    if (knownWrong.includes(errCode)) {
      err(`CREDENTIALS SALAH (FB error ${errCode}) — Email/password tidak cocok atau IP di-flag`);
      kv('error_msg', (caaLogin?.error_message?.text || '').substring(0, 80));
    } else if (knownBlock.includes(errCode)) {
      err(`AKUN DIBLOKIR (FB error ${errCode})`);
      kv('error_msg', (caaLogin?.error_message?.text || '').substring(0, 80));
    } else if (redirectUri) {
      ok(`REDIRECT LOGIN (error ${errCode}) → ${decodeURIComponent(redirectUri).substring(0, 80)}`);
      verdict = 'redirect';
    } else {
      warn(`FB CAA ERROR ${errCode} — ${(caaLogin?.error_message?.text || '').substring(0, 80)}`);
    }
    if (verdict === 'unknown') verdict = 'caa_error:' + errCode;

  // 4. Legacy errors field
  } else if (parsed?.error) {
    const fbErr = parsed;
    err(`FB API ERROR — ${fbErr.errorSummary || fbErr.error}: ${fbErr.errorDescription || ''}`);
    kv('error_msg', (fbErr.payload?.error_msg || '').substring(0, 80));
    verdict = 'fb_error:' + (fbErr.error || 'unknown');

  } else if (parsed?.errors?.length) {
    const e0 = parsed.errors[0];
    err(`GraphQL ERROR — ${e0.message || e0.code || JSON.stringify(e0)}`);
    verdict = 'gql_error:' + (e0.code || 'unknown');

  // 5. Redirect yang berhasil (redirect_uri tanpa error_code)
  } else if (redirectUri) {
    ok(`REDIRECT LOGIN → ${decodeURIComponent(redirectUri).substring(0, 80)}`);
    verdict = 'redirect';

  } else {
    warn('Status tidak jelas — periksa response');
    if (!VERBOSE) log('  Preview: ' + responseText.substring(0, 300));
    verdict = 'unknown';
  }

  // ─── Ringkasan cookies (hanya untuk non-2FA flow, 2FA sudah tampilkan sendiri) ─
  const is2faFlow = ['success_2fa', 'wrong_otp', 'checkpoint_next', 'otp_unknown'].includes(verdict);
  if (!is2faFlow) {
    log('\nCookies yang diterima:');
    if (cookies2.length === 0) {
      log('  (tidak ada)');
    } else {
      for (const ck of cookies2) {
        const val = ck.value.length > 40 ? ck.value.substring(0, 40) + '…' : ck.value;
        log(`  ${ck.key.padEnd(16)} = ${val}`);
      }
    }
  }

  // ─── Simpan output JSON ────────────────────────────────────────────────────
  if (OUTPUT) {
    const finalCookies = await jar.getCookies('https://www.facebook.com');
    const finalCUser   = finalCookies.find(c => c.key === 'c_user')?.value || cUser || null;
    const finalXs      = finalCookies.find(c => c.key === 'xs')?.value     || xs    || null;
    const result = {
      timestamp    : new Date().toISOString(),
      email        : EMAIL,
      verdict,
      status       : r2.status,
      c_user       : finalCUser,
      xs           : finalXs,
      fb_dtsg      : fbdtsg || null,
      cookies      : Object.fromEntries(finalCookies.map(c => [c.key, c.value])),
      tokens       : { lsd, jazoest, rev, hs, hsi, sessionId, spinR, spinT, spinB, keyId },
      responsePreview: responseText.substring(0, 3000),
      parsed       : parsed || null,
    };
    fs.writeFileSync(OUTPUT, JSON.stringify(result, null, 2));
    ok(`Hasil disimpan ke: ${OUTPUT}`);
  }

  log('');
}

main().catch(e => {
  console.error(`\x1b[31m✗ Fatal: ${e.message}\x1b[0m`);
  if (VERBOSE) console.error(e.stack);
  process.exit(1);
});
