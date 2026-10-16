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
const server = await createServer({ cacheDir, configFile: false, plugins: [react()], appType: 'custom', server: { middlewareMode: true }, resolve: { alias: { '@': resolve('resources/js') } } });
after(async () => { await server.close(); rmSync(cacheDir, { recursive: true, force: true }); });
const { TableFrame } = await server.ssrLoadModule('/resources/js/components/ui/table-frame.tsx');
const { formatBytes } = await server.ssrLoadModule('/resources/js/lib/format.ts');

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
