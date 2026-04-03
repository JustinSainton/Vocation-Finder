<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Garamond', 'Georgia', serif; font-size: 11pt; color: #1C1917; margin: 0; padding: 40px; }
        h1 { font-size: 18pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #A8A29E; padding-bottom: 4px; margin: 20px 0 10px; color: #78716C; }
        .summary { margin-bottom: 16px; line-height: 1.5; }
        .entry { margin-bottom: 14px; }
        .entry-header { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .entry-title { font-weight: bold; }
        .entry-date { color: #78716C; font-size: 10pt; }
        .entry-subtitle { color: #78716C; font-size: 10pt; margin-bottom: 4px; }
        ul { margin: 4px 0; padding-left: 20px; }
        li { margin-bottom: 3px; line-height: 1.4; }
        .skills-group { margin-bottom: 6px; }
        .skills-label { font-weight: bold; font-size: 10pt; }
        .skills-list { font-size: 10pt; color: #57534E; }
    </style>
</head>
<body>
    @if(!empty($resume['summary']))
        <h2>Professional Summary</h2>
        <div class="summary">{{ $resume['summary'] }}</div>
    @endif

    @if(!empty($resume['work']))
        <h2>Experience</h2>
        @foreach($resume['work'] as $entry)
            <div class="entry">
                <div class="entry-title">{{ $entry['position'] ?? '' }}</div>
                <div class="entry-subtitle">{{ $entry['company'] ?? '' }} &middot; {{ $entry['startDate'] ?? '' }} &ndash; {{ $entry['endDate'] ?? 'Present' }}</div>
                @if(!empty($entry['highlights']))
                    <ul>
                        @foreach($entry['highlights'] as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif

    @if(!empty($resume['education']))
        <h2>Education</h2>
        @foreach($resume['education'] as $entry)
            <div class="entry">
                <div class="entry-title">{{ $entry['studyType'] ?? '' }} in {{ $entry['area'] ?? '' }}</div>
                <div class="entry-subtitle">{{ $entry['institution'] ?? '' }}</div>
            </div>
        @endforeach
    @endif

    @if(!empty($resume['volunteer']))
        <h2>Volunteer Experience</h2>
        @foreach($resume['volunteer'] as $entry)
            <div class="entry">
                <div class="entry-title">{{ $entry['position'] ?? '' }}</div>
                <div class="entry-subtitle">{{ $entry['organization'] ?? '' }} &middot; {{ $entry['startDate'] ?? '' }} &ndash; {{ $entry['endDate'] ?? 'Present' }}</div>
                @if(!empty($entry['highlights']))
                    <ul>
                        @foreach($entry['highlights'] as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif

    @if(!empty($resume['skills']))
        <h2>Skills</h2>
        @foreach($resume['skills'] as $group)
            <div class="skills-group">
                <span class="skills-label">{{ $group['name'] ?? '' }}:</span>
                <span class="skills-list">{{ implode(', ', $group['keywords'] ?? []) }}</span>
            </div>
        @endforeach
    @endif
</body>
</html>
