const nacl = require('tweetnacl-sealedbox-js');
const { webcrypto } = require('crypto');
const axios = require('axios');

function getPasswordFromArgs() {
    const args = process.argv.slice(2); 
    const passwordArg = args.find(arg => arg.startsWith("password=")); 
    return passwordArg ? passwordArg.split("=")[1] : null;
}

function decodeUTF8(str) {
    return new Uint8Array(Buffer.from(str, 'utf-8'));
}

function parsePublicKey(hexStr) {
    return new Uint8Array(Buffer.from(hexStr, 'hex'));
}

function encodeBase64(uintArray) {
    return Buffer.from(uintArray).toString('base64');
}

async function encrypt(version, publicKeyHex, _plaintext) {
    let time = Math.floor(Date.now() / 1000).toString();
    let plaintext = decodeUTF8(_plaintext);
    let additionalData = decodeUTF8(time);

    if (publicKeyHex.length !== 64) {
        throw new Error('The public key is not a valid hexadecimal string of expected length.');
    }

    let publicKey = parsePublicKey(publicKeyHex);
    let aesKey = await webcrypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]);
    let aesKeyRaw = await webcrypto.subtle.exportKey('raw', aesKey);
    let iv = new Uint8Array(12);
    let sealedKey = nacl.seal(new Uint8Array(aesKeyRaw), publicKey);
    let encrypted = await webcrypto.subtle.encrypt(
        {
            name: "AES-GCM",
            iv,
            additionalData
        },
        aesKey,
        plaintext
    );

    let buffer = new Uint8Array(100 + plaintext.length);
    let offset = 0;

    buffer[offset] = 1;
    offset += 1;
    buffer[offset] = version;
    offset += 1;

    buffer[offset] = sealedKey.length & 255;
    buffer[offset + 1] = (sealedKey.length >> 8) & 255;
    offset += 2;

    buffer.set(sealedKey, offset);
    offset += 32;
    offset += nacl.overheadLength;

    let b = new Uint8Array(encrypted);
    let c = b.slice(-16);
    b = b.slice(0, -16);
    buffer.set(c, offset);
    offset += 16;
    buffer.set(b, offset);

    return ['#PWD_BROWSER', 5, time, encodeBase64(buffer)].join(':');
}

(async () => {
    let password = getPasswordFromArgs();
    
    if (!password) {
        console.log("Gunakan: node enc.js password=MySecretPass123");
        process.exit(1);
    }

    let res = await axios.get('https://www.facebook.com/').then((e) => e.data);
    let [_, publicKey, keyId] = res.match(/"publicKey":"(.+?)","keyId":(\d+?)\}\}/) || [];

    if (publicKey && keyId) {
        encrypt(parseInt(keyId), publicKey, password).then(encpass => {
            console.log(encpass);
        });
    } else {
        console.log("Gagal mendapatkan kunci publik.");
    }
})();
