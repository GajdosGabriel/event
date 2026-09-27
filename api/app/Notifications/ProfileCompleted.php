<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Venue;
use App\Support\DashboardUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ProfileCompleted extends Notification
{
    public function __construct(public Canal|Venue $subject, public array $changes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->subject instanceof Canal ? 'kanála' : 'miesta';
        $mail = (new MailMessage)->subject('Doplnili sme profil '.$this->subject->name)
            ->greeting('Dobrý deň,')
            ->line(new HtmlString('<p>Aby návštevníci ľahšie našli váš profil '.$type.' <strong>'.e($this->subject->name).'</strong>, doplnili sme chýbajúce údaje z verejných zdrojov pomocou AI a geokódera.</p>'))
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

        return $mail->action('Skontrolovať profil', DashboardUrl::edit($this->subject))
            ->line('Prosíme, skontrolujte správnosť doplnených údajov. Vo svojom profile ich môžete kedykoľvek upraviť.');
    }
}
