@extends('errors::minimal')

@section('title', __('Unable to complete your request'))
@section('code', $exception->getStatusCode())
@section('message', e(__($exception->getMessage() ?: 'Please refresh the page and try again.')))
