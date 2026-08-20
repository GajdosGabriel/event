@component('mail::message')
{{ $greeting }}

{{ __('mail.question_answered.intro', ['event' => $eventName]) }}

> {{ $questionBody }}

**{{ __('mail.question_answered.answer_label') }}**

{{ $answerBody }}

@if ($eventUrl)
@component('mail::button', ['url' => $eventUrl])
{{ __('mail.question_answered.action') }}
@endcomponent
@endif

{{ __('mail.question_answered.outro') }}
@endcomponent
