import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import { frameworkCatalog, frameworkGroups } from '../../resources/js/components/projects/framework-catalog.ts';

test('framework choices are unique, english, grouped, and backed by a monochrome icon', () => {
    const ids = frameworkCatalog.map(item => item.id);
    const groups = new Set(frameworkGroups.map(group => group.id));
    assert.equal(new Set(ids).size, ids.length);
    assert.deepEqual(frameworkGroups.map(group => group.id), ['php', 'node', 'static', 'python', 'wordpress']);

    for (const item of frameworkCatalog) {
        assert.match(item.name, /^[\x20-\x7E]+$/);
        assert.match(item.description, /[\u0600-\u06FF]/);
        assert.equal(groups.has(item.group), true);
        const svg = fs.readFileSync(`public/icons/frameworks/${item.icon}.svg`, 'utf8');
        assert.match(svg, /<path /);
        assert.equal(svg.includes('fill="#'), false);
    }
});
