{{-- Termín a miesto podujatia — kto sa rozhoduje o účasti alebo drží lístok,
     ich má mať priamo v e-maile. Premenné `$when` a `$where` dodáva notifikácia
     (App\Support\MailText::range, názov s adresou miesta); bez nich sekcia vypadne. --}}
@if (!empty($when))
{{ __('mail.common.when', ['when' => $when]) }}<br>
@endif
@if (!empty($where))
{{ __('mail.common.where', ['where' => $where]) }}
@endif
@if (!empty($when) || !empty($where))

@endif
