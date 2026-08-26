<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
  .cover { max-height: 180px; border-radius: 8px; margin: 12px 0; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
  .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">Neues Medium</span>
    <h1>{{ $media->title }}</h1>
    <p class="meta">
        @if($media->author){{ $media->author }} &middot; @endif
        {{ $media->type->label() }}
        @if($media->year) &middot; {{ $media->year }}@endif
    </p>

    @if($media->cover_path)
        <img src="{{ asset('storage/' . $media->cover_path) }}" alt="Cover" class="cover">
    @endif

    <p>Hallo {{ $recipient->name }},</p>
    <p>
        ein neues Medium, das einem Ihrer Schlagwörter entspricht, wurde in die Bibliothek aufgenommen:
        <strong>{{ $media->title }}</strong>.
    </p>

    @if($media->summary)
        <p style="color: #4b5563; font-size: 14px; font-style: italic;">{{ $media->summary }}</p>
    @endif

    <p style="margin-top: 24px;">
        <a href="{{ config('app.url') }}/medien/{{ $media->id }}" class="btn">Medium ansehen</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Sie erhalten diese Nachricht, weil Sie ein passendes Schlagwort abonniert haben.
        Abonnements können Sie in Ihrem <a href="{{ config('app.url') }}/profile" style="color: #6b7280;">Profil</a> verwalten.
    </div>
</div>
</body>
</html>
