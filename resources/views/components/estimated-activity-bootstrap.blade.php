@props(['contexts' => []])
@foreach($contexts as $context)
<script type="application/json" data-estimated-activity>{!! json_encode($context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endforeach
