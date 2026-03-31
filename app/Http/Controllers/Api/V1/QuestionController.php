<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Support\ConversationLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $locale = ConversationLocale::normalize($request->query('locale', $request->header('X-Locale')));
        $betaEnabled = config('vocation.beta.questions_enabled');

        $questions = Question::with(['category', 'translations' => fn ($query) => $query->where('locale', $locale)])
            ->where('is_beta', $betaEnabled)
            ->orderBy('sort_order')
            ->get();

        return QuestionResource::collection($questions);
    }
}
