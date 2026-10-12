import assert from "node:assert/strict";
import { test } from "node:test";
import { formatJalali } from "../../resources/js/lib/datetime.ts";

test("jalali leap-year boundary in Asia/Tehran uses latin digits", () => {
    assert.equal(formatJalali("2024-03-19T20:30:00.000Z", "Asia/Tehran"), "1403/01/01 00:00:00");
    assert.equal(formatJalali("2025-03-20T20:29:59.000Z", "Asia/Tehran"), "1403/12/30 23:59:59");
    assert.equal(formatJalali("2025-03-20T20:30:00.000Z", "Asia/Tehran"), "1404/01/01 00:00:00");
});

test("the same instant is a different civil time in UTC", () => {
    assert.notEqual(
        formatJalali("2024-03-19T20:30:00.000Z", "Asia/Tehran"),
        formatJalali("2024-03-19T20:30:00.000Z", "UTC"),
    );
});

test("invalid input does not throw", () => {
    assert.equal(formatJalali("not-a-date", "Asia/Tehran"), null);
    assert.equal(formatJalali("2024-03-19T20:30:00.000Z", "Not/AZone"), null);
});
