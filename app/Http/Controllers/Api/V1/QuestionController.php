<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $questions = Question::with('category')
            ->orderBy('sort_order')
            ->get();

        return QuestionResource::collection($questions);
    }
}
