<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  h1 { font-size: 20px; color: #111827; margin: 0 0 8px; }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px; background: #d1fae5; color: #065f46; }
  .info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #1e40af; margin: 16px 0; }
  .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <h1>Dein Wunsch ist da!</h1>
    <span class="badge">Verfügbar</span>

    <p>Hallo {{ $wish->user->name }},</p>
    <p>
        Das gewünschte Medium <strong>„{{ $media->title }}"</strong>
        @if($media->author) von {{ $media->author }} @endif
        ist jetzt in unserer Bibliothek verfügbar.
    </p>

    <div class="info">
        <strong>{{ $media->title }}</strong>
        @if($media->author)<br>{{ $media->author }}@endif
        @if($media->isbn)<br><span style="font-family:monospace">ISBN {{ $media->isbn }}</span>@endif
    </div>

    <p>
        <a href="{{ config('app.url') }}/medien/{{ $media->id }}" class="btn">Jetzt ansehen</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Sie erhalten diese E-Mail, weil Sie einen Medienwunsch zu diesem Buch eingereicht haben.
    </div>
</div>
</body>
</html>
