@extends('layouts.public')
@section('body-class', 'studio-legal-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php($document = app(\App\Domains\CMS\Services\LegalDocumentPresenter::class)->fromBlocks(config('public_content.policy.blocks')))
<x-public-legal-document :document="$document" :title="config('public_content.policy.title')" :description="__('Practical details about booking, changing a lesson, package validity and refunds. Read this alongside the Terms & Conditions.')" current="policy" :language="$copyLocale ?? 'en'" />
@endsection
