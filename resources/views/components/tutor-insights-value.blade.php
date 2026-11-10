@props(['metric'])
<span>{{ App\Domains\Reporting\Services\TutorInsightsReadModel::display($metric) }}</span>
@if($metric['state'] === 'partial')<small class="insights-muted block text-xs font-normal leading-5">Partial known subtotal</small>@endif
@if($metric['invalid'])<small class="insights-muted block text-xs font-normal leading-5">{{ $metric['invalid'] }} invalid duration {{ $metric['invalid'] === 1 ? 'record' : 'records' }} excluded</small>@endif
