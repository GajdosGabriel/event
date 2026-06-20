<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\{Canal, Event, Organization, User};
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

// Simulované prihlásenie prvého usera
// Auth::login(User::find(1));


Route::get('/login', function () {
    return  null;
})->name('login');

if (! Route::has('sanctum.csrf-cookie')) {
    Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
        ->middleware('web')
        ->name('sanctum.csrf-cookie');
}


Route::get('/openAI', function () {
    $detector = new \App\Services\OpenAI\Detector();
    $url = 'https://www.vyveska.sk/28391/karmelitanska-svata-omsa-s-obradom-prijatia-posvatneho-skapuliara.html';

    // $event = Event::orderBy('created_at', 'desc')
    //     ->where('organization_id', 271) // výveska
    //     // ->whereNotNull('published_at')
    //     ->where('body_ai', null)
    //     ->first();

    $event = Event::find(10613); // Priloha

    // Canal::create([
    //     'title' => 'Ján Novák',
    //     'email' => 'jan.novak@example.com'
    // ]);

    $newBody = $detector->detectFromUrl('https://www.vyveska.sk/28328/katechezy-pre-dospelych-a-mladez.html');
    // $newBody = $detector->detectFromUrl($event->orginal_source);

    dd($newBody);
    $event->update([
        'meta' => $newBody,
        'body_ai' => $newBody['event_copywriter_payload']['event_body'] ?? null
    ]);


    if ($newBody['event_payload']['organizer']) {
        $organization = Organization::updateOrCreate(
            ['title' => $newBody['event_payload']['organizer']['name']],
            ['title' => $newBody['event_payload']['organizer']['name']]
        );

        $event->organization_id = $organization->id;
        $event->save();
    }

    // dd($newBody);

    $personNames = $newBody['event_payload']['persons'] ?? null;

    if (is_array($personNames)) {
        foreach ($personNames as $p) {
            if (is_array($p)) {
                $name = trim($p['meno'] ?? $p['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $phone = $p['telefon'] ?? $p['phone'] ?? null;
                $email = $p['email'] ?? null;
                $description = $p['description'] ?? null;

                $organization = Organization::updateOrCreate(['title' => $name]);
                if ($phone !== null) {
                    $organization->phone = $phone;
                }
                if ($email !== null) {
                    $organization->email = $email;
                }
                if ($description !== null) {
                    $organization->description = $description;
                }
                $organization->save();
            } else {
                $name = trim($p);
                if ($name === '') {
                    continue;
                }
                Organization::firstOrCreate(['title' => $name]);
            }
        }
    } elseif (!empty($personNames)) {
        if (is_array($personNames)) {
            // already handled above, but keep for safety
        } else {
            $name = trim($personNames);
            if ($name !== '') {
                Organization::firstOrCreate(['title' => $name]);
            }
        }
    }
    dd($newBody);


    print($event->orginal_source);
    print(' id ' . $event->id);
    // dd($event);
    if (!$event->orginal_source) {
        print('Sa nenašiel $event->orginal_source');
    } else {

        $newBody = $detector->promptFromUrl($event->orginal_source);

        dd($newBody);

        // $event->update(['body_ai' => $newBody['event_payload']['body_ai']]);

        // Event::where('id', $event->id)->update(['body_ai' => $newBody['event_ai']]);

        dd($newBody);
        // dd($detector->spracujUrl($event->orginal_source));
    }




    //  $event = \App\Models\Event::orderBy('created_at', 'desc')
    //     // ->whereNotNull('published_at')
    //     ->where('body_ai', null)->first();

    // dd($event);
});
