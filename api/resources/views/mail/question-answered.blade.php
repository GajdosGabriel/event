@component('mail::message')
{{ $greeting }}

{{ __('mail.question_answered.intro', ['event' => $eventName]) }}

@component('mail::panel')
{!! nl2br(e($questionBody), false) !!}
@endcomponent

**{{ __('mail.question_answered.answer_label') }}**

{!! nl2br(e($answerBody), false) !!}

@if ($eventUrl)
@component('mail::button', ['url' => $eventUrl])
{{ __('mail.question_answered.action') }}
@endcomponent
@endif

{{ __('mail.question_answered.outro') }}
@endcomponent
