import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../resources/js/form-builder.js', import.meta.url), 'utf8');
test('visual edits retain stable keys, options, validation and conditional metadata while reordering', () => {
    let factory;
    vm.runInNewContext(source, {document: {addEventListener: (event, callback) => callback()}, Alpine: {data: (name, callback) => factory = callback}});
    const questions = [{question_key: 'goal', label: 'Goal', question_type: 'short_text', options: [], validation_rules: {max_length: 250}, conditional_logic: null}, {question_key: 'level', label: 'Level', question_type: 'dropdown', options: [{label: 'Beginner', value: 'beginner'}], conditional_logic: {version: 1, mode: 'all', conditions: [{question_key: 'goal', operator: 'equals', value: 'Travel'}]}}];
    const builder = factory(questions); builder.move(1, -1); builder.questions[0].label = 'Your level'; builder.add();
    const saved = JSON.parse(builder.serialize());
    assert.equal(saved[0].question_key, 'level'); assert.equal(saved[0].options[0].value, 'beginner'); assert.equal(saved[0].conditional_logic.conditions[0].question_key, 'goal');
    assert.equal(saved[1].validation_rules.max_length, 250); assert.equal(saved[2].sort_order, 2);
    assert.equal(new Set(saved.map(question => question.question_key)).size, 3);
});
