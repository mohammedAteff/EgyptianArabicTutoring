<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramLegacyImporter;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use App\Domains\Notifications\Services\TelegramTemplateService;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TelegramController extends Controller
{
    public function index(Request $request, TelegramLegacyImporter $importer, TelegramRuleCatalog $catalog): View
    {
        $importer->import();
        $tab = (string) $request->input('tab', 'overview');
        if (! in_array($tab, ['overview', 'bots', 'destinations', 'rules', 'commands', 'history'], true)) {
            $tab = 'overview';
        }
        $rule = $request->filled('edit') ? TelegramRule::findOrFail($request->integer('edit')) : null;
        $history = TelegramDelivery::with(['bot', 'destination', 'rule'])->orderByDesc('id');
        $filters = $request->validate(['bot' => ['nullable', 'integer'], 'destination' => ['nullable', 'integer'], 'rule' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['pending', 'sending', 'sent', 'failed', 'uncertain', 'cancelled'])], 'date' => ['nullable', 'date_format:Y-m-d']]);
        foreach (['bot' => 'telegram_bot_id', 'destination' => 'telegram_destination_id', 'rule' => 'telegram_rule_id', 'status' => 'status'] as $input => $column) {
            if (! empty($filters[$input])) {
                $history->where($column, $filters[$input]);
            }
        }
        if (! empty($filters['date'])) {
            $start = CarbonImmutable::parse($filters['date'], app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
            $history->where('created_at', '>=', $start->setTimezone('UTC'))->where('created_at', '<', $start->addDay()->setTimezone('UTC'));
        }

        return view('admin.telegram.index', ['students' => Student::orderBy('first_name')->orderBy('last_name')->get(), 'sessionTypes' => SessionType::orderBy('title')->get(), 'tab' => $tab, 'bots' => TelegramBot::with('destinations')->get(), 'destinations' => TelegramDestination::with('bot')->get(), 'rules' => TelegramRule::with(['bot', 'destinations'])->get(), 'editingRule' => $rule, 'catalog' => $catalog->all(), 'history' => $history->paginate(30)->withQueryString()]);
    }

    public function global(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            Setting::set('telegram.automation_enabled', $request->boolean('enabled'), 'telegram');
            Setting::set('telegram.commands_enabled', $request->boolean('commands_enabled'), 'telegram');
            $this->audit('global', 0, ['enabled' => $request->boolean('enabled'), 'commands_enabled' => $request->boolean('commands_enabled')]);
        });

        return back()->with('success', 'Telegram switches saved.');
    }

    public function bot(Request $request, ?TelegramBot $bot = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'token' => [$bot ? 'nullable' : 'required', 'string', 'regex:/^[0-9]{5,20}:[A-Za-z0-9_-]{20,100}$/'], 'commands' => ['nullable', 'array'], 'commands.*' => [Rule::in(['today', 'tomorrow', 'student', 'stats'])]]);
        $data['enabled'] = $request->boolean('enabled');
        $data['commands_enabled'] = $request->boolean('commands_enabled');
        $data['commands'] = $data['commands'] ?? [];
        if (empty($data['token'])) {
            unset($data['token']);
        }
        DB::transaction(function () use ($bot, $data): void {
            $bot ??= new TelegramBot;
            $bot->fill($data)->save();
            $this->audit('bot', $bot->id, array_diff_key($data, ['token' => true]));
        });

        return back()->with('success', 'Bot saved. Its token is encrypted and hidden.');
    }

    public function destination(Request $request, ?TelegramDestination $destination = null): RedirectResponse
    {
        $data = $request->validate(['telegram_bot_id' => ['required', 'exists:telegram_bots,id'], 'name' => ['required', 'string', 'max:120'], 'chat_id' => ['required', 'string', 'regex:/^-?[0-9]{1,30}$/', Rule::unique('telegram_destinations', 'chat_id')->where('telegram_bot_id', $request->integer('telegram_bot_id'))->ignore($destination?->id)], 'type' => ['required', Rule::in(['private', 'group', 'channel'])], 'detail_level' => ['required', Rule::in(['summary', 'personal'])], 'allowed_user_ids' => ['nullable', 'string', 'max:2000', 'regex:/^[0-9,\s]*$/']]);
        if ($destination && $destination->telegram_bot_id !== $request->integer('telegram_bot_id')) {
            abort(422, 'A destination cannot move between bots.');
        }
        $data['enabled'] = $request->boolean('enabled');
        $data['allowed_user_ids'] = array_values(array_filter(array_map('trim', explode(',', $data['allowed_user_ids'] ?? ''))));
        DB::transaction(function () use ($destination, $data): void {
            $destination ??= new TelegramDestination;
            $destination->fill($data)->save();
            $this->audit('destination', $destination->id, $data);
        });

        return back()->with('success', 'Destination saved.');
    }

    public function rule(Request $request, TelegramRuleCatalog $catalog, TelegramTemplateService $templates, ?TelegramRule $rule = null): RedirectResponse
    {
        $data = $request->validate(['telegram_bot_id' => ['required', 'exists:telegram_bots,id'], 'name' => ['required', 'string', 'max:120'], 'trigger' => ['required', Rule::in(array_keys($catalog->all()))], 'mode' => ['required', 'string'], 'priority' => ['required', Rule::in(['critical', 'normal', 'low'])], 'minutes' => ['required', 'integer', 'between:1,525600'], 'threshold' => ['required', 'integer', 'between:0,1000000'], 'window_minutes' => ['required', 'integer', 'between:1,1440'], 'cooldown_minutes' => ['required', 'integer', 'between:0,525600'], 'send_time' => ['required', 'date_format:H:i'], 'quiet_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_end'], 'quiet_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_start', 'different:quiet_start'], 'template' => ['required', 'string', 'max:12000'], 'destinations' => ['required', 'array', 'min:1'], 'destinations.*' => ['required', 'integer', Rule::exists('telegram_destinations', 'id')->where('telegram_bot_id', $request->integer('telegram_bot_id'))], 'conditions' => ['nullable', 'array:_student_id,_session_type_id,country,source'], 'conditions._student_id' => ['nullable', 'integer', 'exists:students,id'], 'conditions._session_type_id' => ['nullable', 'integer', 'exists:session_types,id'], 'conditions.country' => ['nullable', 'regex:/^[A-Z]{2}$/'], 'conditions.source' => ['nullable', 'string', 'max:100'], 'sections' => ['nullable', 'array'], 'sections.*' => [Rule::in(['bookings', 'students', 'resources', 'system', 'analytics'])]]);
        if (! in_array($data['mode'], $catalog->get($data['trigger'])['modes'], true)) {
            throw ValidationException::withMessages(['mode' => 'Choose an available mode for this alert.']);
        }
        $templates->validate($data['trigger'], $data['template']);
        $ids = $data['destinations'];
        unset($data['destinations']);
        $data['enabled'] = $request->boolean('enabled');
        $data['sections'] = $data['sections'] ?? [];
        DB::transaction(function () use ($rule, $data, $ids): void {
            $rule ??= new TelegramRule;
            $rule->fill($data)->save();
            $rule->destinations()->sync($ids);
            $this->audit('rule', $rule->id, $data);
        });

        return redirect()->route('admin.telegram.index', ['tab' => 'rules'])->with('success', 'Alert rule saved.');
    }

    public function preview(Request $request, TelegramTemplateService $templates, TelegramRuleCatalog $catalog): RedirectResponse
    {
        $data = $request->validate(['trigger' => ['required', Rule::in(array_keys($catalog->all()))], 'template' => ['required', 'string', 'max:12000']]);
        $samples = array_fill_keys($catalog->get($data['trigger'])['fields'], 'Sample');
        $samples['student_name'] = 'Example student';
        $samples['email'] = 'example@example.org';
        $samples['phone'] = 'Example phone';

        return back()->with('preview', $templates->render($data['trigger'], $data['template'], $samples, true))->withInput($request->except(['token']));
    }

    public function run(TelegramRule $rule, TelegramReadService $read, TelegramAutomationService $automation): RedirectResponse
    {
        abort_unless($rule->mode === 'on_demand' && in_array($rule->trigger, ['business_digest', 'analytics_digest'], true), 422);
        $automation->emit($rule->trigger, 'manual:'.Str::uuid(), ['date' => now(app(TimezoneService::class)->getBusinessTimezone())->toDateString(), 'digest' => $rule->trigger === 'business_digest' ? $read->businessDigest($rule->sections ?? []) : $read->stats(1)], null, $rule->id);
        $this->audit('run', $rule->id, ['trigger' => $rule->trigger]);

        return back()->with('success', 'On-demand report requested. Check delivery history for its outcome.');
    }

    public function test(TelegramDestination $destination, TelegramDeliveryService $delivery): RedirectResponse
    {
        abort_unless($destination->enabled && $destination->bot->enabled, 422, 'Enable the selected bot and destination before testing.');
        $result = $delivery->test($destination);
        $this->audit('test', $destination->id, ['status' => $result->status]);

        return back()->with($result->status === 'sent' ? 'success' : 'error', 'TEST delivery status: '.$result->status.($result->failure_code ? ' ('.$result->failure_code.')' : ''));
    }

    /** @param array<string,mixed> $data */
    private function audit(string $action, int $id, array $data): void
    {
        AuditLog::create(['administrator_id' => auth()->id(), 'action' => 'telegram_'.$action.'_saved', 'entity_type' => 'Telegram automation', 'entity_id' => $id, 'new_data' => $data, 'created_at' => now()]);
    }
}
