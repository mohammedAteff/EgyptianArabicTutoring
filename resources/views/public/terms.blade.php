@extends('layouts.public')
@section('body-class', 'studio-legal-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php($document = app(\App\Domains\CMS\Services\LegalDocumentPresenter::class)->fromText($content ?? null))
<x-public-legal-document :document="$document" :title="$title ?? __('Terms & Conditions')" :description="__('The arrangements that guide our lessons, your package and your learning experience.')" current="terms" :language="$translation?->locale ?? (($isFallback ?? false) ? 'en' : app()->getLocale())" :empty-message="__('The published terms are not available yet. Please contact Abdallah directly for the current terms before booking.')" />
@endsection
