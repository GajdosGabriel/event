<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Venue;
use App\Notifications\Channels\BellChannel;
use App\Support\DashboardUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ProfileCompleted extends Notification
{
    public function __construct(public Canal|Venue $subject, public array $changes) {}

    public function via(object $notifiable): array
    {
        return [BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => $this->title(), 'link' => DashboardUrl::edit($this->subject)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->subject instanceof Canal ? 'kanála' : 'miesta';
        $mail = (new MailMessage)->subject($this->title())
            ->greeting(__('mail.common.greeting'))
            ->line(new HtmlString('<p>Aby vás návštevníci ľahšie našli, doplnili sme pri stránke '.$type.' <strong>'.e($this->subject->name).'</strong> chýbajúce údaje z verejných zdrojov.</p>'))
            ->line('Vaše vyplnené údaje a ručne zadanú polohu sme zachovali.');
        $labels = ['website' => 'Web', 'email' => 'E-mail', 'phone' => 'Telefón', 'street' => 'Ulica',
            'postcode' => 'PSČ', 'country' => 'Krajina', 'body' => 'Popis', 'municipality_id' => 'Obec',
            'village_id' => 'Obec', 'latitude' => 'Zemepisná šírka', 'longitude' => 'Zemepisná dĺžka'];
        foreach ($this->changes as $field => $value) {
            if (isset($labels[$field])) {
                if (in_array($field, ['municipality_id', 'village_id'], true)) {
                    $value = $this->subject->municipality?->fullname;
                }
                $mail->line(new HtmlString('<p><strong>'.e($labels[$field]).':</strong> '.e(strip_tags((string) $value)).'</p>'));
            }
        }

        return $mail->action('Skontrolovať údaje', DashboardUrl::edit($this->subject))
            ->line('Prosíme, skontrolujte, či sú doplnené údaje správne. Upraviť ich môžete kedykoľvek.');
    }

    private function title(): string
    {
        return ($this->subject instanceof Canal ? 'Doplnili sme údaje kanála ' : 'Doplnili sme údaje miesta ').$this->subject->name;
    }
}
