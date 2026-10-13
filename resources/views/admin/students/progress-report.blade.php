@extends('layouts.admin')
@section('content')
<x-progress-report-content :report="$report" :student="$student" :lessons="$lessons" :hours="$hours" :homework-count="$homeworkCount" :homework-completed="$homeworkCompleted" :assessments="$assessments" :milestones="$milestones" :resources="$resources" :topics="$topics" :timezone="$timezone" />
@endsection
