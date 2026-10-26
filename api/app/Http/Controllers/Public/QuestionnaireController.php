<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventQuestionResource;
use App\Models\Event;
use App\Services\Tickets\Questionnaire;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Dotazník k registrácii (strava, ubytovanie…) pre formulár lístkov — web
 * aj embed. Nezamieňať s Q&A nástenkou (EventQuestionController).
 */
class QuestionnaireController extends Controller
{
    public function index($eventId, Questionnaire $questionnaire): AnonymousResourceCollection
    {
        $event = Event::query()->findOrFail($eventId);

        return EventQuestionResource::collection($questionnaire->activeQuestions($event));
    }
}
