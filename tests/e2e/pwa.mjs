import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';

const origin = process.env.RAHANDAZ_PWA_ORIGIN ?? 'http://127.0.0.1:8108';
assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
const revision = '/tmp/rahandaz-pwa-revision';
const server = spawn('php', ['-S', new URL(origin).host, '-t', 'public', 'tests/e2e/pwa-server.php'], { env: { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: origin, DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', SESSION_DRIVER: 'array', RAHANDAZ_BASE_PATH: '/rahandaz', RAHANDAZ_PWA_TEST_REVISION: revision }, stdio: 'ignore' });
await writeFile(revision, 'version-one');
const profile = await mkdtemp(`${tmpdir()}/rahandaz-pwa-chrome-`);
const chrome = spawn('/opt/google/chrome/chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=9335', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let targets;
    for (let i = 0; i < 100; i++) {
        try { targets = await (await fetch('http://127.0.0.1:9335/json/list')).json(); break; } catch { await delay(100); }
    }
    assert.ok(targets, 'Chrome failed to start');
    socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let sequence = 0;
    const pending = new Map();
    const errors = [];
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
        if (message.id) {
            const task = pending.get(message.id); pending.delete(message.id);
            if (message.error) task.reject(new Error(message.error.message)); else task.resolve(message.result);
        }
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence; pending.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async (expression) => {
        const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true, userGesture: true });
        assert.ok(!result.exceptionDetails, JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const waitFor = async (expression) => {
        for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await delay(100); }
        throw new Error(`Timed out: ${expression}`);
    };
    for (let i = 0; i < 100; i++) { try { if ((await fetch(origin + '/rahandaz/manifest.webmanifest')).ok) break; } catch {} await delay(100); }
    await send('Runtime.enable');
    await send('Page.enable');
    await send('Network.enable');
    await send('Page.navigate', { url: `${origin}/rahandaz/login` });
    await waitFor(`document.querySelector('input[name="identifier"]') && !!navigator.serviceWorker.controller`);
    const manifest = await evaluate(`fetch('/rahandaz/manifest.webmanifest').then(r=>r.json())`);
    assert.equal(manifest.scope, '/rahandaz/');
    assert.equal(manifest.icons.length, 3);
    const appManifest = await send('Page.getAppManifest');
    assert.equal(appManifest.errors.length, 0, JSON.stringify(appManifest.errors));
    const installability = await send('Page.getInstallabilityErrors');
    assert.deepEqual(installability.installabilityErrors, [], JSON.stringify(installability));
    // Capture an install prompt fixture; dismissing must not claim successful installation.
    await evaluate(`(() => { window.__pwaPromptCalls=0; const e=new Event('beforeinstallprompt'); e.prompt=async()=>{window.__pwaPromptCalls++}; e.userChoice=Promise.resolve({outcome:'dismissed'}); window.dispatchEvent(e); return true; })()`);
    await delay(100);
    await evaluate(`Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('نصب برنامه')).click()`);
    await waitFor(`window.__pwaPromptCalls===1 && !!Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('نصب برنامه') && !b.disabled)`);
    await evaluate(`Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('نصب برنامه')).click()`);
    await waitFor(`!!document.querySelector('[role="dialog"]')`);
    for (const width of [320, 390, 768, 1440]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: width === 320 ? 568 : 900, deviceScaleFactor: 1, mobile: width < 768 });
        await delay(100);
        if (width === 320) { const shot = await send('Page.captureScreenshot', { format: 'png' }); await writeFile('/tmp/rahandaz-pwa-install-mobile.png', Buffer.from(shot.data, 'base64')); }
        assert.ok(await evaluate(`document.documentElement.scrollWidth <= innerWidth + 1`), `overflow at ${width}`);
        assert.ok(await evaluate(`(() => { const r=document.querySelector('[role="dialog"]').getBoundingClientRect(); return r.left>=0 && r.right<=innerWidth+1 && r.top>=0 && r.bottom<=innerHeight+1; })()`));
    }
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'dark' }, { name: 'prefers-reduced-motion', value: 'reduce' }] });
    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
    await writeFile('/tmp/rahandaz-pwa-install-dark.png', Buffer.from(screenshot.data, 'base64'));
    await evaluate(`Array.from(document.querySelectorAll('[role="dialog"] button')).find(b=>b.textContent==='بستن').click()`);
    // A new worker must wait; no reload or form loss until explicit consent.
    await evaluate(`document.querySelector('[name="identifier"]').value='unsaved-fixture'`);
    await writeFile(revision, 'version-two');
    await evaluate(`navigator.serviceWorker.getRegistration().then(r=>r.update())`);
    await waitFor(`!!Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('نسخهٔ تازه'))`);
    assert.equal(await evaluate(`document.querySelector('[name="identifier"]').value`), 'unsaved-fixture');
    await evaluate(`Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('نسخهٔ تازه')).click()`);
    await waitFor(`!!document.querySelector('[role="dialog"]')`);
    await evaluate(`Array.from(document.querySelectorAll('[role="dialog"] button')).find(b=>b.textContent.includes('ذخیره کرده‌ام')).click()`);
    await waitFor(`document.querySelector('[name="identifier"]')?.value === '' && !document.querySelector('[role="dialog"]')`);
    await evaluate(`navigator.serviceWorker.ready`);
    const stored = await evaluate(`(async()=>{const result=[];for(const name of await caches.keys()){for(const req of await (await caches.open(name)).keys())result.push(new URL(req.url).pathname)}return result})()`);
    assert.deepEqual(stored.sort(), ['/assets/pwa/offline.html', '/branding/logo-symbol.svg']);
    // Stop our isolated origin as well: CDP page-only offline emulation can leave the worker's own network online.
    server.kill();
    await new Promise(resolve => server.once('exit', resolve));
    await send('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0 });
    assert.ok(await evaluate(`fetch('/rahandaz/login',{headers:{'X-Inertia':'true'}}).then(()=>false,()=>true)`), 'Inertia must not receive offline HTML');
    assert.ok(await evaluate(`fetch('/rahandaz/login',{method:'POST',body:'fixture-only'}).then(()=>false,()=>true)`), 'POST must not be replayed or cached');
    await send('Page.navigate', { url: `${origin}/rahandaz/login?private-probe=1` });
    await waitFor(`document.body.textContent.includes('ارتباط با پنل برقرار نیست')`);
    assert.equal(await evaluate(`document.querySelector('input')`), null);
    assert.equal(errors.length, 0, JSON.stringify(errors));
    console.log('PASS: manifest/installability, registration, install dismissal, responsive RTL dialog, deferred update/form preservation, public-only cache, offline navigation, no POST/Inertia caching.');
    console.log('Chrome loopback only; physical iOS/Android/Desktop OS installation not certified. Screenshot: /tmp/rahandaz-pwa-install-dark.png');
} finally {
    if (server.exitCode === null && server.signalCode === null) { server.kill(); await new Promise(resolve => server.once('exit', resolve)); }
    socket?.close(); chrome.kill();
    await new Promise(resolve => chrome.once('exit', resolve));
    await rm(profile, { recursive: true, force: true });
    await rm(revision, { force: true });
}
