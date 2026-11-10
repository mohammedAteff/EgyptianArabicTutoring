@extends('layouts.public')
@section('body-class', 'studio-legal-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php($document = app(\App\Domains\CMS\Services\LegalDocumentPresenter::class)->fromText($content ?? null))
<x-public-legal-document :document="$document" :title="$title ?? __('Privacy Policy')" :description="__('How information is used to support your learning, run the website and protect your account.')" current="privacy" :language="$translation?->locale ?? (($isFallback ?? false) ? 'en' : app()->getLocale())" :empty-message="__('The published privacy policy is not available yet. Please contact Abdallah directly for the current policy before submitting personal information.')" />
@endsection
