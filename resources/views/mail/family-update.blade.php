{{--
    Deliberately plain, and deliberately short. Everything printed here comes
    from ParentVisibility::summaryFor(); nothing is assembled in this file,
    because a template that reaches for a model is a second place that decides
    what a parent sees.
--}}
<x-mail::message>
Hello {{ $parentName }},

@if (! $summary['reportable'])
{{ $summary['reason'] }}

@else
Here is how {{ $summary['student_name'] }} is going.

@if ($summary['current_action'])
**What they are working on now:** {{ $summary['current_action'] }}
@else
They do not have anything in progress this month.
@endif

@if (count($summary['milestones']) > 0)
**Dates coming up**

@foreach ($summary['milestones'] as $milestone)
- {{ $milestone['due_on'] }} — {{ $milestone['title'] }} ({{ $milestone['status'] }})
@endforeach
@endif

**One thing you can do**

{{ $summary['suggested_conversation'] }}

<x-mail::button :url="$url">
See their progress
</x-mail::button>

We do not send you their coaching conversations, and we never will. A student
who knows their parent is reading everything stops being honest with the
coach, and the honesty is what the whole thing runs on.
@endif

— Vocation Finder
</x-mail::message>
