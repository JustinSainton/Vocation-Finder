<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $copy['title'] }}</title>
    <style>
        @page {
            margin: 36px 42px 42px;
        }

        body {
            font-family: DejaVu Serif, Georgia, serif;
            color: #1c1917;
            font-size: 12px;
            line-height: 1.55;
        }

        h1, h2, h3 {
            font-weight: normal;
            margin: 0;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 6px;
        }

        h2 {
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #57534e;
            margin-top: 24px;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #d6d3d1;
        }

        p {
            margin: 0 0 10px;
        }

        .subtitle {
            color: #78716c;
            font-style: italic;
            margin-bottom: 20px;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 18px;
        }

        .meta-grid td {
            width: 33.33%;
            vertical-align: top;
            padding: 10px 12px;
            border: 1px solid #e7e5e4;
            background: #f8f7f2;
        }

        .meta-label {
            display: block;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #78716c;
            margin-bottom: 4px;
        }

        .list-card {
            margin-bottom: 8px;
            padding: 10px 12px;
            border-left: 3px solid #a8a29e;
            background: #f5f5f0;
        }

        .step-row {
            margin-bottom: 8px;
        }

        .step-number {
            display: inline-block;
            width: 18px;
            color: #57534e;
            font-weight: bold;
        }

        .pathway-blurb {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-left: 3px solid #a8a29e;
            background: #f5f5f0;
        }

        .pathway-blurb-name {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .pathway-blurb-desc {
            margin: 0 0 6px;
            color: #1c1917;
        }

        .pathway-blurb-ministry {
            margin: 0;
            font-style: italic;
            color: #57534e;
        }

        .career-chips {
            margin-top: 6px;
        }

        .footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid #d6d3d1;
            color: #78716c;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <h1>{{ $copy['title'] }}</h1>
    <p class="subtitle">{{ $copy['subtitle'] }}</p>

    @if($profile->opening_synthesis)
        <h2>{{ $copy['opening_synthesis'] }}</h2>
        {!! nl2br(e($profile->opening_synthesis)) !!}
    @endif

    @if($profile->vocational_orientation)
        <h2>{{ $copy['vocational_orientation'] }}</h2>
        {!! nl2br(e($profile->vocational_orientation)) !!}
    @endif

    <table class="meta-grid">
        <tr>
            <td>
                <span class="meta-label">{{ $copy['primary_domain'] }}</span>
                {{ $profile->primary_domain ?: $copy['not_specified'] }}
            </td>
            <td>
                <span class="meta-label">{{ $copy['mode_of_work'] }}</span>
                {{ $profile->mode_of_work ?: $copy['not_specified'] }}
            </td>
            <td>
                <span class="meta-label">{{ $copy['secondary_orientation'] }}</span>
                {{ $profile->secondary_orientation ?: $copy['not_specified'] }}
            </td>
        </tr>
    </table>

    @if(!empty($profile->primary_pathways))
        <h2>{{ $copy['primary_pathways'] }}</h2>
        @foreach($profile->primary_pathways as $pathway)
            <div class="list-card">{{ $pathway }}</div>
        @endforeach
    @endif

    @if($profile->specific_considerations)
        <h2>{{ $copy['specific_considerations'] }}</h2>
        {!! nl2br(e($profile->specific_considerations)) !!}
    @endif

    @if(!empty($profile->next_steps))
        <h2>{{ $copy['next_steps'] }}</h2>
        @foreach($profile->next_steps as $index => $step)
            <div class="step-row">
                <span class="step-number">{{ $index + 1 }}.</span>
                <span>{{ $step }}</span>
            </div>
        @endforeach
    @endif

    @if($profile->ministry_integration)
        <h2>{{ $copy['ministry_integration'] }}</h2>
        {!! nl2br(e($profile->ministry_integration)) !!}
    @endif

    @php $pathwayBlurbs = $profile->matchedPathwayBlurbs(3); @endphp
    @if(!empty($pathwayBlurbs))
        <h2>{{ $copy['vocational_pathways'] }}</h2>
        @foreach($pathwayBlurbs as $blurb)
            <div class="pathway-blurb">
                <div class="pathway-blurb-name">{{ $blurb['name'] }}</div>
                <p class="pathway-blurb-desc">{{ $blurb['description'] }}</p>
                <p class="pathway-blurb-ministry">{{ $blurb['ministry_connection'] }}</p>
                @if(!empty($blurb['career_pathways']))
                    <div class="career-chips">
                        @foreach($blurb['career_pathways'] as $cp)
                            <span class="career-chip">· {{ $cp }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    <div class="footer">
        <p>
            {{ $copy['disclaimer'] }}
        </p>
        <p>{{ $copy['return_to_results_anytime'] }}: {{ $resultsUrl }}</p>
    </div>
</body>
</html>
