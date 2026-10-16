import assert from 'node:assert/strict';
import test from 'node:test';
import { validateField } from '../../resources/js/lib/form-validation.ts';

test('required fields and optional empty fields are validated without browser constraint validation', () => {
    assert.equal(validateField({ value: ' ', isRequired: true }), 'این فیلد را تکمیل کنید.');
    assert.equal(validateField({ value: '', type: 'url' }), undefined);
    assert.equal(validateField({ value: 'secret-value', isRequired: true }), undefined);
});
test('URL, email, length and format failures have localized field errors', () => {
    for (const rule of [{value:'invalid', type:'email'}, {value:'javascript:alert(1)', type:'url'}, {value:'short', minLength:8}, {value:'too-long', maxLength:3}, {value:'Invalid Slug', pattern:'[a-z0-9-]+'}]) assert.equal(typeof validateField(rule), 'string');
    assert.equal(validateField({value:'https://git.example.com', type:'url'}), undefined);
    assert.equal(validateField({value:'user@example.com', type:'email'}), undefined);
});
