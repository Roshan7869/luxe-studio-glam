const https = require('https');
const querystring = require('querystring');

const URL = process.env.AUDIT_HOST || 'luxe-studio-glam-production.up.railway.app';
const USERNAME = process.env.AUDIT_USER;
const PASSWORD = process.env.AUDIT_PASSWORD;

if (!USERNAME || !PASSWORD) {
    console.error('ERROR: AUDIT_USER and AUDIT_PASSWORD environment variables must be set.');
    process.exit(1);
}

async function audit() {
    console.log(`Logging into remotely deployed WordPress enterprise portal at https://${URL}...`);

    const loginData = querystring.stringify({
        log: USERNAME,
        pwd: PASSWORD,
        'wp-submit': 'Log In',
        redirect_to: `https://${URL}/wp-admin/`,
        testcookie: '1'
    });

    const loginOptions = {
        hostname: URL,
        port: 443,
        path: '/wp-login.php',
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Content-Length': loginData.length
        }
    };

    const loginRes = await new Promise((resolve, reject) => {
        const req = https.request(loginOptions, res => resolve(res));
        req.on('error', e => reject(e));
        req.write(loginData);
        req.end();
    });

    console.log(`Login HTTP Code: ${loginRes.statusCode}`);

    // WordPress login returns 302 on success
    if (loginRes.statusCode !== 302 && loginRes.statusCode !== 200) {
        console.error('Login failed.');
        return;
    }

    const cookies = loginRes.headers['set-cookie'] || [];
    let cookieString = cookies.map(c => c.split(';')[0]).join('; ');
    console.log('Obtained auth cookies.');

    // Fetch the admin page to get the REST API Nonce
    const adminOptions = {
        hostname: URL,
        port: 443,
        path: '/wp-admin/',
        method: 'GET',
        headers: {
            'Cookie': cookieString
        }
    };

    const adminBody = await new Promise((resolve, reject) => {
        const req = https.request(adminOptions, res => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => resolve(body));
        });
        req.on('error', e => reject(e));
        req.end();
    });

    const nonceMatch = adminBody.match(/var wpApiSettings = {"root":".*?","nonce":"([a-z0-9]+)","versionString":".*?"};/);
    const nonce = nonceMatch ? nonceMatch[1] : null;

    console.log(`Authenticating REST API requests... Nonce: ${nonce || 'Not found'}`);

    // Request operations summary
    const apiOptions = {
        hostname: URL,
        port: 443,
        path: '/wp-json/glamlux/v1/operations/summary',
        method: 'GET',
        headers: {
            'Cookie': cookieString,
            'X-WP-Nonce': nonce || ''
        }
    };

    const apiBody = await new Promise((resolve, reject) => {
        const req = https.request(apiOptions, res => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => resolve({ code: res.statusCode, body }));
        });
        req.on('error', e => reject(e));
        req.end();
    });

    console.log(`Operations API Returned ${apiBody.code}:`);
    try {
        console.log(JSON.stringify(JSON.parse(apiBody.body), null, 2));
    } catch {
        // Fallback if not json
        console.log("Raw Response:", apiBody.body.substring(0, 500));

        // Let's also check if WordPress is returning a 403 or 401 with an error message
        console.log("Health API Error: Could not parse JSON. The endpoint might be locked down or returning HTML.");
    }
}

audit();
