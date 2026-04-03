<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Garamond', 'Georgia', serif; font-size: 11pt; color: #1C1917; margin: 0; padding: 60px; line-height: 1.6; }
        p { margin: 0 0 14px; }
    </style>
</head>
<body>
    @foreach(explode("\n\n", $content) as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach
</body>
</html>
