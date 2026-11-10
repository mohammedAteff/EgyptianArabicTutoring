<?php

namespace App\Domains\Forms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Forms\Models\Form;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinalStudentFormsInstaller
{
    public function __construct(private FormBuilderService $builder, private FormValidationService $validation, private AuditLogService $audit) {}

    /** @return array<string, mixed> */
    public function target(): array
    {
        $connection = DB::connection();
        if (! app()->environment(['local', 'testing', 'forms-intake-browser'])
            || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            || $connection->getConfig('read') || $connection->getConfig('write')
            || $connection->getConfig('unix_socket')) {
            throw new RuntimeException('Refusing an unverified local database target.');
        }
        $actual = $connection->selectOne('SELECT DATABASE() AS database_name, @@hostname AS server_host, @@port AS server_port', useReadPdo: false);
        if ($actual->database_name !== $connection->getDatabaseName()
            || strcasecmp($actual->server_host, gethostname()) !== 0
            || (int) $actual->server_port !== (int) $connection->getConfig('port')) {
            throw new RuntimeException('The actual database server does not match the configured local target.');
        }

        return ['environment' => app()->environment(), 'connection' => config('database.default'), 'host' => $connection->getConfig('host'), 'port' => (int) $actual->server_port, 'database' => $actual->database_name, 'server' => $actual->server_host];
    }

    /** @return array<string, array<string, mixed>> */
    public function plan(bool $replace): array
    {
        $this->target();
        $competing = Form::query()->where('slug', '!=', 'short-form')->where('status', 'published')
            ->whereHas('triggers', fn ($query) => $query->where('trigger_name', 'pre_booking'))->exists();
        if ($competing) {
            throw new RuntimeException('A competing published pre-booking form exists. Resolve the assignment conflict first.');
        }
        $plans = [];
        foreach (config('student_forms') as $slug => $definition) {
            $this->validation->validateStructure($definition['questions']);
            $form = Form::query()->where('slug', $slug)->with('publishedVersion.questions.options', 'triggers')->first();
            $plans[$slug] = [
                'action' => ! $form ? 'create' : ($this->matches($form, $definition) ? 'unchanged' : ($replace ? 'replace' : 'preserve')),
                'form_id' => $form?->id, 'active_version_id' => $form?->active_version_id,
                'published_version_id' => $form?->published_version_id, 'lock_version' => $form?->lock_version,
                'question_count' => count($definition['questions']),
            ];
        }

        return $plans;
    }

    /** @param array<string, array<string, mixed>> $expected
     * @return array<string, array<string, mixed>>
     */
    public function apply(Administrator $author, bool $replace, array $expected): array
    {
        $this->target();

        return $this->builder->atomicPublication(function () use ($author, $replace, $expected): array {
            $author = Administrator::query()->whereKey($author->id)->whereNull('suspended_at')->whereIn('role', ['super_admin', 'admin'])->lockForUpdate()->first();
            if (! $author) {
                throw new RuntimeException('An existing active administrator is required.');
            }
            Form::query()->whereIn('slug', array_keys(config('student_forms')))->orderBy('id')->lockForUpdate()->get();
            $plans = $this->plan($replace);
            if ($plans !== $expected) {
                throw new RuntimeException('The forms changed after inspection. Run the command again to review the new plan.');
            }
            foreach ($plans as $slug => &$plan) {
                if (! in_array($plan['action'], ['create', 'replace'], true)) {
                    continue;
                }
                $definition = config('student_forms.'.$slug);
                $form = $plan['form_id'] === null
                    ? $this->builder->create($definition['metadata'], $definition['questions'], $author)
                    : $this->builder->update($plan['form_id'], $definition['metadata'] + ['changelog' => 'Final student questionnaires; earlier definitions retained.'], $definition['questions'], $plan['active_version_id'], $plan['lock_version'], preserveBaseVersion: true);
                $form = $this->builder->publish($form->id, $form->active_version_id, $form->lock_version);
                $this->audit->log('form.published', Form::class, $form->id, null, ['form_id' => $form->id, 'version_id' => $form->published_version_id, 'source' => 'final-student-forms'], $author->id);
                $plan = [...$plan, 'form_id' => $form->id, 'active_version_id' => $form->active_version_id, 'published_version_id' => $form->published_version_id, 'lock_version' => $form->lock_version, 'version_number' => $form->publishedVersion->version_number];
            }

            return $plans;
        });
    }

    /** Definitions only: never includes student answers.
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return ['target' => $this->target(), 'forms' => Form::query()->whereIn('slug', array_keys(config('student_forms')))->with('versions.questions.options', 'triggers')->get()->toArray(), 'integrity' => $this->integrity()];
    }

    /** @return array<string, array{count: int, sha256: string}> */
    public function integrity(): array
    {
        $result = [];
        foreach (['form_submissions', 'form_answers', 'form_submission_revisions'] as $table) {
            $hash = hash_init('sha256');
            $count = 0;
            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
                $count++;
            }
            $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }

        return $result;
    }

    /** @param array<string, mixed> $definition */
    private function matches(Form $form, array $definition): bool
    {
        if ($form->status !== 'published' || ! $form->publishedVersion) {
            return false;
        }
        foreach ($definition['metadata'] as $key => $value) {
            if ($key === 'triggers') {
                $actual = $form->triggers->pluck('trigger_name')->sort()->values()->all();
                $wanted = collect($value)->sort()->values()->all();
                if ($actual !== $wanted) {
                    return false;
                }
            } elseif ($form->getAttribute($key) !== $value) {
                return false;
            }
        }
        $questions = [];
        foreach ($form->publishedVersion->questions as $question) {
            $questions[] = [...$question->only(['question_key', 'label', 'description', 'question_type', 'is_required', 'assistant_visible', 'sort_order', 'validation_rules', 'presentation_config', 'conditional_logic']), 'options' => $question->options->map(fn ($option): array => ['label' => $option->label, 'value' => $option->value, 'sort_order' => (int) $option->sort_order])->all()];
        }

        return $questions == $definition['questions'];
    }
}
