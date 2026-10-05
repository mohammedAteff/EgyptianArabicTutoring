import test from 'node:test';
import assert from 'node:assert/strict';
import { studentDetailValue, studentDetails, recoveryCodesText } from '../resources/js/clipboard.js';
function form(values) { return { elements: { namedItem(name) { return values[name] === undefined ? null : { value: values[name], ...(name === 'identity_status' ? { selectedOptions: [{ textContent: 'Verified' }] } : {}) }; } } }; }
test('copy reads current unsaved values and readable dates', () => { const values = { first_name: 'Before', date_of_birth: '1990-03-21' }; const current = form(values); values.first_name = ' Unsaved '; assert.equal(studentDetailValue(current,'first_name'),'Unsaved'); assert.equal(studentDetailValue(current,'date_of_birth'),'21/03/1990'); });
test('phone retains country context and international format', () => { assert.equal(studentDetailValue(form({ phone:'01012345678', phone_country:'eg' }),'phone'),'EG 01012345678'); assert.equal(studentDetailValue(form({ phone:'+201012345678', phone_country:'EG' }),'phone'),'+201012345678'); });
test('copy all includes empty fields and notes but no hidden secrets', () => { const output = studentDetails(form({first_name:'QA', identity_status:'verified', internal_notes:'Vocabulary', confirmation_token:'SECRET', password:'SECRET'})); assert.match(output,/Identity status: Verified/); assert.match(output,/Email: —/); assert.match(output,/Internal notes: Vocabulary/); assert.doesNotMatch(output,/SECRET|password|confirmation/); });

test('recovery copy uses only the rendered codes and profile name with clean line breaks', () => {
    const panel = { dataset: { administratorName: 'Synthetic Owner' }, querySelectorAll: selector => { assert.equal(selector, '[data-recovery-code]'); return [{textContent:' FIRST-CODE '}, {textContent:'SECOND-CODE'}]; } };
    assert.equal(recoveryCodesText(panel), 'Synthetic Owner Admin — Two-Factor Authentication Recovery Codes\n\nKeep these recovery codes somewhere secure.\nEach recovery code can only be used once.\n\nFIRST-CODE\nSECOND-CODE');
});
