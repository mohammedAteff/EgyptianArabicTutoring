document.addEventListener('alpine:init', () => {
    Alpine.data('formBuilder', (initial) => ({
        questions: initial.map(question => ({...question, options: question.options ?? [], validation_rules: question.validation_rules ?? {}, presentation_config: question.presentation_config ?? {}})), preview: false,
        types: ['short_text','long_text','email','phone','number','date','single_choice','multiple_choice','dropdown','yes_no','rating_scale','info_block'],
        add() { this.questions.push({question_key: 'q_' + Date.now().toString(36) + Math.random().toString(36).slice(2), label: 'New question', question_type: 'short_text', is_required: false, assistant_visible: true, options: [], conditional_logic: null, validation_rules: {}, presentation_config: {}}); },
        move(index, delta) { const target = index + delta; if (target < 0 || target >= this.questions.length) return; const [item] = this.questions.splice(index, 1); this.questions.splice(target, 0, item); },
        choice(question) { return ['single_choice','multiple_choice','dropdown'].includes(question.question_type); },
        addOption(question) { question.options.push({label: 'New option', value: 'option_' + Math.random().toString(36).slice(2,10)}); },
        condition(question) { question.conditional_logic = {version: 1, mode: 'all', conditions: [{question_key: '', operator: 'equals', value: ''}]}; },
        serialize() { return JSON.stringify(this.questions.map((question, index) => ({...question, sort_order: index, options: (question.options || []).map((option, order) => ({...option, sort_order: order}))}))); }
    }));
});
