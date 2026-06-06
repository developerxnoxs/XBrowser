const puppeteer = require('puppeteer');
const fs = require('fs');

const EMAIL    = '083807650503';
const PASSWORD = 'Bulusari2580';
const OUTPUT   = 'intercept_result.json';

const allRequests = [];

function log(msg) {
    const ts = new Date().toISOString().slice(11, 23);
    console.log(`[${ts}] ${msg}`);
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

(async () => {
    log('Launching browser...');
    const browser = await puppeteer.launch({
        headless: true,
        executablePath: '/nix/store/qa9cnw4v5xkxyip6mb9kxqfq1z4x2dx1-chromium-138.0.7204.100/bin/chromium',
        args: ['--no-sandbox','--disable-setuid-sandbox','--disable-dev-shm-usage',
               '--disable-gpu','--no-zygote','--single-process','--window-size=390,844'],
        defaultViewport: { width: 390, height: 844, isMobile: true, deviceScaleFactor: 3 },
    });

    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Linux; Android 10; Redmi Note 8 Build/QKQ1.200114.002; wv) ' +
        'AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 ' +
        'Chrome/132.0.6834.163 Mobile Safari/537.36'
    );
    await page.setExtraHTTPHeaders({
        'accept-language': 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
    });

    // ── CDP session untuk raw network capture ──────────────────────────────
    const client = await page.createCDPSession();
    await client.send('Network.enable');

    const requestMap = {};  // requestId → request info

    client.on('Network.requestWillBeSent', (evt) => {
        const { requestId, request, initiator, type } = evt;
        if (!request.url.includes('facebook.com')) return;
        requestMap[requestId] = {
            requestId,
            type,
            method: request.method,
            url: request.url,
            headers: request.headers,
            postData: request.postData || null,
            hasCredentials: false,
        };
        // Tandai kalau ada credentials
        const pd = request.postData || '';
        if (pd.includes(EMAIL) || pd.includes('encpass') || pd.includes('pass=')) {
            requestMap[requestId].hasCredentials = true;
            log(`⭐ CREDENTIALS FOUND in ${request.method} ${request.url.slice(0,80)}`);
            log(`   postData: ${pd.slice(0,300)}`);
        }
        if (request.method === 'POST' && !request.url.includes('wbloks/log') &&
            !request.url.includes('google.com') && !request.url.includes('weblite')) {
            log(`→ ${request.method} ${request.url.slice(0,100)}`);
        }
    });

    client.on('Network.responseReceived', (evt) => {
        const { requestId, response } = evt;
        if (requestMap[requestId]) {
            requestMap[requestId].responseStatus = response.status;
            requestMap[requestId].responseHeaders = response.headers;
        }
    });

    client.on('Network.loadingFinished', async (evt) => {
        const { requestId } = evt;
        if (!requestMap[requestId]) return;
        const req = requestMap[requestId];
        // Ambil response body untuk POST ke /a/bz dan semua request credential
        if (req.hasCredentials || (req.method === 'POST' && req.url.includes('/a/bz'))) {
            try {
                const body = await client.send('Network.getResponseBody', { requestId });
                req.responseBody = body.body?.slice(0, 3000);
            } catch {}
        }
        allRequests.push(req);
    });

    // ── Navigasi ──────────────────────────────────────────────────────────
    log('Step 1: Open home to get datr...');
    await page.goto('https://m.facebook.com/', { waitUntil: 'networkidle2', timeout: 30000 });
    await sleep(1500);

    const cookies0 = await page.cookies();
    log(`Cookies: ${cookies0.map(c=>c.name).join(', ')}`);

    log('Step 2: Open login page...');
    await page.goto('https://m.facebook.com/login/', { waitUntil: 'networkidle2', timeout: 30000 });
    await sleep(2000);

    log(`Login page title: ${await page.title()} | URL: ${page.url()}`);

    // ── Ambil token dari halaman ───────────────────────────────────────────
    const pageData = await page.evaluate(() => {
        const html = document.documentElement.outerHTML;
        return {
            // lsd dari HTML
            lsd: (html.match(/"LSD",\[\],\{"token":"([^"]+)"\}/) || [])[1] || null,
            // jazoest dari html
            jazoest: (html.match(/jazoest[^0-9]{0,10}(\d{8,})/) || [])[1] || null,
            // fb_dtsg dari html
            fb_dtsg: (html.match(/"fb_dtsg",\[\],\{"token":"([^"]+)"\}/) ||
                      html.match(/"EAABwzLixnjYBABvnhHKAWWsOJt[^"]*"/) || [])[1] || null,
            // public_key (berbagai format)
            public_key: (html.match(/"public_key"\s*:\s*"([0-9a-f]{64})"/) ||
                         html.match(/"publicKey"\s*:\s*"([0-9a-f]{64})"/) || [])[1] || null,
            key_id: (html.match(/"key_id"\s*:\s*(\d+)/) ||
                     html.match(/"keyId"\s*:\s*(\d+)/) || [])[1] || null,
            // form inputs
            inputs: [...document.querySelectorAll('input')].map(el => ({
                type: el.type, name: el.name, id: el.id, value: el.value?.slice(0,30)
            })),
            // form actions
            forms: [...document.querySelectorAll('form')].map(f => ({
                action: f.action, method: f.method, id: f.id
            })),
            // script tags yang mengandung kata kunci
            scriptSnippets: [...document.querySelectorAll('script')].map(s => s.textContent)
                .filter(t => t.includes('public_key') || t.includes('publicKey') || t.includes('key_id'))
                .map(t => t.slice(0, 200)),
        };
    });

    log('=== PAGE DATA ===');
    log(`lsd: ${pageData.lsd}`);
    log(`jazoest: ${pageData.jazoest}`);
    log(`fb_dtsg: ${pageData.fb_dtsg}`);
    log(`public_key: ${pageData.public_key}`);
    log(`key_id: ${pageData.key_id}`);
    log(`Inputs: ${JSON.stringify(pageData.inputs)}`);
    log(`Forms: ${JSON.stringify(pageData.forms)}`);
    log(`Scripts with key: ${pageData.scriptSnippets.length}`);
    if (pageData.scriptSnippets.length > 0) log(pageData.scriptSnippets[0]);

    // Screenshot
    await page.screenshot({ path: 'login_page.png', fullPage: false });

    // ── Isi form & submit ──────────────────────────────────────────────────
    const emailField = await page.$('input[name="email"]') ||
                       await page.$('input[type="email"]') ||
                       await page.$('input[type="text"]');

    if (!emailField) {
        log('[!] Email field tidak ditemukan!');
        // print semua visible input
        const vis = await page.$$eval('input', els => els.map(e=>({type:e.type,name:e.name,id:e.id})));
        log('Visible inputs: ' + JSON.stringify(vis));
    } else {
        log('Mengisi email...');
        await emailField.click({ clickCount: 3 });
        await emailField.type(EMAIL, { delay: 50 });
        await sleep(400);

        const passField = await page.$('input[name="pass"]') ||
                          await page.$('input[type="password"]');
        if (passField) {
            log('Mengisi password...');
            await passField.click();
            await passField.type(PASSWORD, { delay: 50 });
            await sleep(400);
        }

        await page.screenshot({ path: 'before_submit.png', fullPage: false });
        log('Screenshot before_submit.png');

        const loginBtn = await page.$('button[name="login"]') ||
                         await page.$('button[type="submit"]') ||
                         await page.$('input[type="submit"]');

        if (loginBtn) {
            log('Klik tombol login...');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(()=>{}),
                page.evaluate(el => el.click(), loginBtn),
            ]);
        } else {
            log('Tombol tidak ditemukan, tekan Enter...');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(()=>{}),
                passField?.press('Enter'),
            ]);
        }

        await sleep(3000);
        log(`URL setelah login: ${page.url()}`);

        const cookies = await page.cookies();
        const cUser = cookies.find(c => c.name === 'c_user');
        const xs    = cookies.find(c => c.name === 'xs');
        log(cUser ? `✓ LOGIN BERHASIL! c_user=${cUser.value}` : '✗ Login GAGAL (tidak ada c_user)');
        log(`All cookies: ${cookies.map(c=>c.name+'='+c.value.slice(0,15)).join('; ')}`);

        await page.screenshot({ path: 'after_login.png', fullPage: false });
        log('Screenshot after_login.png');

        // ── Ambil info dari halaman setelah login ──────────────────────────
        const afterHtml = await page.content();
        const afterInfo = {
            url: page.url(),
            c_user: cUser?.value,
            xs: xs?.value,
            cookies: cookies.map(c => `${c.name}=${c.value}`).join('; '),
        };
        fs.writeFileSync('after_login_data.json', JSON.stringify(afterInfo, null, 2));
    }

    await browser.close();

    // ── Simpan hasil ───────────────────────────────────────────────────────
    // Filter hanya facebook.com requests (exclude google)
    const fbReqs = allRequests.filter(r => r.url.includes('facebook.com'));
    fs.writeFileSync(OUTPUT, JSON.stringify({ pageData, requests: fbReqs }, null, 2));

    log(`\n=== RINGKASAN ===`);
    log(`Total FB requests: ${fbReqs.length}`);

    // Tampilkan semua POST ke /a/bz
    const bzPosts = fbReqs.filter(r => r.method === 'POST' && r.url.includes('/a/bz'));
    log(`POST ke /a/bz: ${bzPosts.length}`);
    bzPosts.forEach((r, i) => {
        log(`  ${i+1}. ${r.url.slice(0,100)}`);
        if (r.postData) log(`     body preview: ${r.postData.slice(0,200)}`);
        if (r.responseBody) log(`     response: ${r.responseBody.slice(0,200)}`);
    });

    // Tampilkan request dengan credentials
    const credReqs = fbReqs.filter(r => r.hasCredentials);
    log(`\nRequests dengan kredensial: ${credReqs.length}`);
    credReqs.forEach(r => {
        log(`  ${r.method} ${r.url.slice(0,100)}`);
        log(`  body: ${r.postData?.slice(0,400)}`);
    });

    log(`\nSimpan ke: ${OUTPUT} dan after_login_data.json`);
})();
