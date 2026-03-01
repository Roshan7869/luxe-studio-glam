const https = require('https');
const querystring = require('querystring');

const URL = 'luxe-studio-glam-production.up.railway.app';
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';

async function req(options, postData = null) {
    return new Promise((resolve, reject) => {
        const client = https.request(options, res => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => resolve({ code: res.statusCode, headers: res.headers, body }));
        });
        client.on('error', e => reject(e));
        if (postData) client.write(postData);
        client.end();
    });
}

async function triggerMigration() {
    console.log(`Authenticating...`);
    const loginData = querystring.stringify({ log: USERNAME, pwd: PASSWORD, 'wp-submit': 'Log In', redirect_to: `https://${URL}/wp-admin/`, testcookie: '1' });
    const loginRes = await req({ hostname: URL, port: 443, path: '/wp-login.php', method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': loginData.length } }, loginData);

    const cookies = loginRes.headers['set-cookie'] || [];
    const cookieString = cookies.map(c => c.split(';')[0]).join('; ');

    console.log(`Fetching plugins.php to extract nonces...`);
    const pluginRes = await req({ hostname: URL, port: 443, path: '/wp-admin/plugins.php', method: 'GET', headers: { 'Cookie': cookieString } });

    // Deactivate glamlux-core
    const deactMatch = pluginRes.body.match(/plugins\.php\?action=deactivate&amp;plugin=glamlux-core%2Fglamlux-core\.php.*?_wpnonce=([a-z0-9]+)/);
    if (deactMatch) {
        console.log(`Deactivating glamlux-core...`);
        await req({ hostname: URL, port: 443, path: `/wp-admin/plugins.php?action=deactivate&plugin=glamlux-core%2Fglamlux-core.php&_wpnonce=${deactMatch[1]}`, method: 'GET', headers: { 'Cookie': cookieString } });
    } else {
        console.log("Plugin already deactivated or nonce not found.");
    }

    // Refresh plugins page to get activate nonce
    const pluginRes2 = await req({ hostname: URL, port: 443, path: '/wp-admin/plugins.php', method: 'GET', headers: { 'Cookie': cookieString } });
    const actMatch = pluginRes2.body.match(/plugins\.php\?action=activate&amp;plugin=glamlux-core%2Fglamlux-core\.php.*?_wpnonce=([a-z0-9]+)/);

    if (actMatch) {
        console.log(`Activating glamlux-core to trigger DB migrations...`);
        await req({ hostname: URL, port: 443, path: `/wp-admin/plugins.php?action=activate&plugin=glamlux-core%2Fglamlux-core.php&_wpnonce=${actMatch[1]}`, method: 'GET', headers: { 'Cookie': cookieString } });
        console.log("Activation triggered!");
    } else {
        console.log("Could not find activation link. Maybe it's already active and deactivation failed?");
    }
}

triggerMigration();
