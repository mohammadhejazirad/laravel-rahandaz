import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { resolve } from 'node:path';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

// Render shared markup locally. This is not a browser or viewport certification.
const cacheDir = mkdtempSync(resolve(tmpdir(), 'rahandaz-ui-test-'));
const server = await createServer({ cacheDir, configFile: false, plugins: [react()], appType: 'custom', server: { middlewareMode: true, hmr: false, ws: false }, resolve: { alias: { '@': resolve('resources/js') } } });
after(async () => { await server.close(); rmSync(cacheDir, { recursive: true, force: true }); });
const { TableFrame } = await server.ssrLoadModule('/resources/js/components/ui/table-frame.tsx');
const { formatBytes } = await server.ssrLoadModule('/resources/js/lib/format.ts');

const { shouldNotifySoftware } = await server.ssrLoadModule('/resources/js/lib/software-jobs.ts');

const { Button } = await server.ssrLoadModule('/resources/js/components/ui/button.tsx');
const { createActionLock } = await server.ssrLoadModule('/resources/js/lib/action-lock.ts');
const { Checkbox } = await server.ssrLoadModule('/resources/js/components/ui/checkbox.tsx');
const { RadioCard } = await server.ssrLoadModule('/resources/js/components/ui/radio-card.tsx');

const { SelectBox } = await server.ssrLoadModule('/resources/js/components/ui/select.tsx');
const { matchesSelectSearch } = await server.ssrLoadModule('/resources/js/lib/select-search.ts');

test('responsive tables preserve column context, links and escaped data without duplicate rows', () => {
    const html = renderToStaticMarkup(React.createElement(TableFrame, { label: 'منابع' },
        React.createElement('thead', null, React.createElement('tr', null, React.createElement('th', null, 'نام'), React.createElement('th', null, 'وضعیت'))),
        React.createElement('tbody', null, ['one', 'two'].map(uuid => React.createElement('tr', { key: uuid },
            React.createElement('td', null, React.createElement('a', { href: `/projects/${uuid}` }, '<script>private</script>')),
            React.createElement('td', null, 'ثبت‌شده'),
        ))),
    ));
    assert.equal((html.match(/role="row"/g) ?? []).length, 2);
    assert.equal((html.match(/role="cell"/g) ?? []).length, 4);
    assert.equal((html.match(/class="mobile-cell-label"/g) ?? []).length, 4);
    assert.match(html, /href="\/projects\/one"/);
    assert.match(html, /&lt;script&gt;private&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /aria-label="منابع"/);
});

test('monitoring units distinguish unavailable data from a measured zero', () => {
    for (const value of [null, -1, NaN, Infinity]) assert.equal(formatBytes(value), '—');
    assert.match(formatBytes(0), /۰ B/);
    assert.match(formatBytes(1024), /۱ KiB/);
    assert.match(formatBytes(1024 ** 3), /۱ GiB/);
});

test('software completion handles a fast response, resumed work, failures and deduplication', () => {
    const job = { uuid: 'job-one', status: 'succeeded', message: null, packages: [] };
    assert.equal(shouldNotifySoftware(job, null, null, null), false, 'Historical success must not toast on first visit');
    assert.equal(shouldNotifySoftware(job, null, null, 'job-one'), true, 'The request may finish before its first response');
    assert.equal(shouldNotifySoftware(job, 'job-one', null, null), true);
    assert.equal(shouldNotifySoftware(job, null, 'pending', null), true, 'Refresh preserves observed work');
    assert.equal(shouldNotifySoftware(job, 'job-one', 'notified', 'job-one'), false);
    assert.equal(shouldNotifySoftware({ ...job, status: 'running' }, 'job-one', 'pending', 'job-one'), false);
    assert.equal(shouldNotifySoftware({ ...job, status: 'queued' }, 'job-one', null, 'job-one'), false);
    assert.equal(shouldNotifySoftware({ ...job, status: 'failed' }, 'job-one', null, null), true);
    assert.equal(shouldNotifySoftware(job, 'different-job', null, 'different-job'), false);
});

test('loading buttons preserve semantics for native buttons, icon actions and slotted links', () => {
    const native = renderToStaticMarkup(React.createElement(Button, { type: 'submit', loading: true, loadingLabel: 'در حال ذخیره' }, 'ذخیره'));
    assert.match(native, /disabled=""/);
    assert.match(native, /aria-busy="true"/);
    assert.match(native, /type="submit"/);
    assert.match(native, /در حال ذخیره/);
    assert.match(native, /motion-safe:animate-spin/);
    const icon = renderToStaticMarkup(React.createElement(Button, { size: 'icon', loading: true, 'aria-label': 'ذخیره' }, 'آیکون'));
    assert.match(icon, /class="sr-only"/);
    assert.match(icon, /aria-label="ذخیره"/);
    const link = renderToStaticMarkup(React.createElement(Button, { asChild: true, loading: true, loadingLabel: 'در حال دریافت' }, React.createElement('a', { href: '/jobs', 'data-example': 'preserved' }, 'گزارش')));
    assert.match(link, /<a /);
    assert.match(link, /href="\/jobs"/);
    assert.match(link, /data-example="preserved"/);
    assert.match(link, /aria-disabled="true"/);
    assert.match(link, /tabindex="-1"/);
    assert.match(link, /در حال دریافت/);
    assert.match(link, /<svg/);
});

test('disabled slotted buttons stop activation before child click handlers', () => {
    let prevented = false;
    let stopped = false;
    let called = false;
    const element = Button({ asChild: true, disabled: true, onClickCapture: () => { called = true; }, children: React.createElement('a', { href: '/jobs', onClickCapture: () => { called = true; } }, 'گزارش') });
    assert.equal(element.props.children.props.onClickCapture, undefined);
    element.props.onClickCapture({ preventDefault: () => { prevented = true; }, stopPropagation: () => { stopped = true; } });
    assert.equal(prevented, true);
    assert.equal(stopped, true);
    assert.equal(called, false);
});

test('request locks reject double activation and recover on completion, cancellation and synchronous errors', () => {
    const transitions = [];
    const lock = createActionLock(value => transitions.push(value));
    let finish;
    assert.equal(lock.run(done => { finish = done; }), true);
    assert.equal(lock.run(() => assert.fail('Duplicate request')), false);
    finish(); // Inertia onFinish also runs after error/cancellation.
    let nextFinish;
    assert.equal(lock.run(done => { nextFinish = done; }), true);
    finish(); // A duplicate old callback must not unlock the next request.
    assert.equal(lock.run(() => assert.fail('Stale callback released lock')), false);
    nextFinish();
    assert.throws(() => lock.run(() => { throw new Error('Failed to start'); }), /Failed to start/);
    assert.equal(lock.run(done => done()), true);
    assert.deepEqual(transitions, [true, false, true, false, true, false, true, false]);
});

test('shared checkbox and radio controls express validation without native required', () => {
    for (const component of [Checkbox, RadioCard]) {
        const html = renderToStaticMarkup(React.createElement(component, { name: 'choice', value: 'one', isRequired: true, disabled: true }, 'گزینه'));
        assert.match(html, /aria-required="true"/);
        assert.match(html, /disabled=""/);
        assert.doesNotMatch(html, /\srequired(?:="|\s|>)/);
        assert.match(html, /گزینه/);
    }
});


test('SelectBox preserves form values, accessible labels, disabled state and Persian errors', () => {
    const html = renderToStaticMarkup(React.createElement(SelectBox, { name: 'environment', label: 'محیط', value: 'production', onChange: () => {}, isRequired: true, searchable: true, error: 'محیط را انتخاب کنید', disabled: true },
        React.createElement('option', { value: '' }, 'انتخاب محیط'),
        React.createElement('option', { value: 'production' }, 'محیط اصلی'),
    ));
    assert.match(html, /role="combobox"/);
    assert.match(html, /name="environment"/);
    assert.match(html, /value="production" selected=""/);
    assert.match(html, /aria-labelledby="environment-label environment-value"/);
    assert.match(html, /aria-required="true"/);
    assert.match(html, /aria-invalid="true"/);
    assert.match(html, /aria-describedby="environment-error"/);
    assert.match(html, /id="environment-error" role="alert"/);
    assert.match(html, /disabled=""/);
    assert.doesNotMatch(html, /\srequired(?:=|\s|>)/);
    assert.match(html, /id="environment-value"[^>]*>محیط اصلی/);
});

test('select search handles Persian and Arabic variants, diacritics, multiple words and case', () => {
    assert.equal(matchesSelectSearch('پايگاه داده كاربری PostgreSQL', 'پایگاه کاربری'), true);
    assert.equal(matchesSelectSearch('NodeJS Production', 'production NODE'), true);
    assert.equal(matchesSelectSearch('مُحیط پروژه', 'محیط'), true);
    assert.equal(matchesSelectSearch('پشتیبان‌گیری', 'پشتیبان گیری'), true);
    assert.equal(matchesSelectSearch('محیط اصلی', '   '), true);
    assert.equal(matchesSelectSearch('محیط اصلی', 'آزمایشی'), false);
});
