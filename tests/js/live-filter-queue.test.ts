import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createLiveFilterQueue, type FilterRequest } from '../../resources/js/lib/live-filter-queue.ts';

test('typing sends only the latest query, while choices need no typing delay', context => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const queue = createLiveFilterQueue();
    const queries: string[] = [];
    queue.schedule(() => queries.push('a'), 350);
    context.mock.timers.tick(200);
    queue.schedule(() => queries.push('ab'), 350);
    context.mock.timers.tick(349);
    assert.deepEqual(queries, []);
    context.mock.timers.tick(1);
    assert.deepEqual(queries, ['ab']);
    queue.schedule(() => queries.push('selected'), 0);
    context.mock.timers.tick(0);
    assert.deepEqual(queries, ['ab', 'selected']);
});

test('old requests and late callbacks cannot overwrite or unlock newer requests', context => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const queue = createLiveFilterQueue();
    let old!: FilterRequest;
    let aborted = 0;
    queue.schedule(request => { old = request; request.setCancel(() => aborted++); }, 0);
    context.mock.timers.tick(0);
    let next!: FilterRequest;
    queue.schedule(request => { next = request; request.setCancel(() => aborted++); }, 0);
    context.mock.timers.tick(0);
    assert.equal(aborted, 1);
    assert.equal(old.isCurrent(), false);
    old.finish();
    assert.equal(queue.pending(), true);
    assert.equal(next.isCurrent(), true);
    queue.cancel();
    assert.equal(aborted, 2);
    assert.equal(next.isCurrent(), false);
    assert.equal(queue.pending(), false);
    queue.schedule(() => assert.fail('Unmounted or disabled filter must not run'), 350);
    queue.cancel();
    context.mock.timers.tick(500);
});
