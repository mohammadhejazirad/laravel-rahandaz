import { readFile, writeFile, mkdir, mkdtemp } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';

const origin = process.env.RAHANDAZ_E2E_ORIGIN ?? 'http://127.0.0.1:8099';
assert.ok(/^http:\/\/127\.0\.0\.1:\d+$/.test(origin), 'Use a loopback test server.');
const credentials = JSON.parse(await readFile(process.env.RAHANDAZ_E2E_CREDENTIAL_FILE, 'utf8'));
const output = process.env.RAHANDAZ_E2E_OUTPUT ?? '/tmp/rahandaz-e2e-output';
await mkdir(output, { recursive: true });
const profile = await mkdtemp(output+'/chrome-');
const chrome = spawn('/opt/google/chrome/chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=9333', '--user-data-dir='+profile, 'about:blank'], { stdio: 'ignore' });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let targets;
    for (let i = 0; i < 100; i++) {
        try { targets = await (await fetch('http://127.0.0.1:9333/json/list')).json(); break; } catch { await delay(100); }
    }
    assert.ok(targets, 'Chrome did not start.');
    socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let sequence = 0;
    const pending = new Map();
    const errors = [];
    socket.addEventListener('message', event => {
        const response = JSON.parse(event.data);
        if (response.method === 'Runtime.exceptionThrown') errors.push(response.params.exceptionDetails.text);
        if (response.id) { const promise = pending.get(response.id); pending.delete(response.id); response.error ? promise.reject(new Error(response.error.message)) : promise.resolve(response.result); }
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => { const id = ++sequence; pending.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params })); });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        assert.ok(!result.exceptionDetails, 'Browser expression failed.');
        return result.result.value;
    };
    const waitFor = async expression => { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await delay(100); } throw new Error('Browser state timed out: '+expression); };
    await send('Runtime.enable');
    const navigate = async path => { await send('Page.navigate', { url: origin+'/rahandaz/'+path }); await waitFor(`location.pathname === ${JSON.stringify('/rahandaz/'+path.split('?')[0])} && document.readyState === 'complete' && !!document.querySelector('h1')`); await delay(150); };
    await navigate('login');
    // Native setter triggers React input state; credentials never enter console or reports.
    await evaluate(`(() => { const set = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set; for (const [name, value] of Object.entries(${JSON.stringify({ identifier: credentials.identifier, password: credentials.password })})) { const input = document.querySelector('[name="'+name+'"]'); set.call(input, value); input.dispatchEvent(new Event('input', {bubbles:true})); } return true; })()`);
    await evaluate(`document.querySelector('form').requestSubmit()`);
    await waitFor(`location.pathname.endsWith('/dashboard') && !!document.querySelector('main')`);
    await navigate('tutorials?q=symlink');
    assert.ok(await evaluate(`document.body.innerText.includes('آموزش در نتیجه')`));
    await navigate('tutorials/backups');
    await evaluate(`Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('نشان‌کردن آموزش'))?.click()`);
    await waitFor(`Array.from(document.querySelectorAll('button')).some(b => b.textContent.includes('حذف نشان'))`);
    await navigate('tutorials/backups');
    assert.ok(await evaluate(`Array.from(document.querySelectorAll('button')).some(b => b.textContent.includes('حذف نشان'))`), 'Bookmark did not survive refresh.');
    await navigate('tutorials?bookmarked=1');
    assert.ok(await evaluate(`document.body.innerText.includes('پشتیبان‌گیری واقعی و راستی‌آزمایی')`));

    const paths = ['dashboard','servers','servers/'+credentials.host_uuid,'projects','projects/'+credentials.project_uuid,'deployments','deployments/'+credentials.deployment_uuid,'domains','databases','backups','backups/schedules','monitoring','tutorials','tutorials/installation','jobs','notifications','catalog','mirrors','audit','settings/profile','settings/updates'];
    const widths = [320,360,390,430,768,1024,1280,1440,1920];
    const checks = [];
    for (const path of paths) {
        await navigate(path);
        assert.equal(await evaluate(`location.pathname.includes('/login')`), false, 'Session unexpectedly ended.');
        for (const theme of ['light','dark']) {
            await evaluate(`document.documentElement.dataset.theme=${JSON.stringify(theme)}`);
            for (const width of widths) {
                await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
                await delay(40);
                const result = await evaluate(`({ overflow: document.documentElement.scrollWidth > innerWidth + 1, rtl: document.documentElement.dir, title: document.querySelector('h1')?.innerText, width: innerWidth })`);
                checks.push({ path, theme, ...result });
                if (result.overflow) {
                    const elements = await evaluate(`Array.from(document.querySelectorAll('main *')).filter(e => { const r=e.getBoundingClientRect(); return r.width > innerWidth || r.right > innerWidth + 1 || r.left < -1; }).map(e => ({tag:e.tagName, slot:e.dataset.slot, class:e.className, width:e.getBoundingClientRect().width})).slice(0,30)`);
                    await writeFile(output+'/overflow.json', JSON.stringify({path, width, theme, elements}, null, 2));
                    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
                    await writeFile(output+'/overflow.png', Buffer.from(screenshot.data, 'base64'));
                }
                assert.equal(result.overflow, false, 'Horizontal overflow: '+path+' '+width+' '+theme);
                assert.equal(result.rtl, 'rtl');
                if (width === 1440 && theme === 'light' && ['dashboard','servers','projects','deployments','databases','monitoring','tutorials'].includes(path)) {
                    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
                    await writeFile(output+'/'+path+'.png', Buffer.from(screenshot.data, 'base64'));
                }
            }
        }
        console.log('checked '+path);
    }
    await navigate('tutorials');
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: false });
    await evaluate(`document.querySelector('[aria-label="باز کردن منوی اصلی"]').click()`);
    await waitFor(`!!document.querySelector('[role="dialog"]')`);
    const drawer = await evaluate(`(() => { const r = document.querySelector('[role="dialog"]').getBoundingClientRect(); return { right:r.right, width:r.width }; })()`);
    assert.ok(Math.abs(drawer.right - 390) < 2, 'Mobile drawer is not anchored on the right.');
    await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await delay(200);
    assert.equal(await evaluate(`!!document.querySelector('[role="dialog"]')`), false);
    assert.equal(errors.length, 0, 'JavaScript runtime errors occurred.');
    await writeFile(output+'/report.json', JSON.stringify({ status:'passed', checked_at:new Date().toISOString(), checks, drawer, runtime_errors:errors.length, fixture:'isolated PostgreSQL; host execution disabled; no synthetic metrics', bookmark:'search/detail/write/refresh/list passed' }, null, 2));
    console.log('passed '+checks.length+' viewport/theme checks and authenticated tutorial flow');
} finally {
    socket?.close();
    chrome.kill('SIGTERM');
}
