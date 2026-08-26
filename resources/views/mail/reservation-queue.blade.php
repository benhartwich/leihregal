<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
  .position { font-size: 28px; font-weight: 700; color: #2563eb; text-align: center; margin: 20px 0 4px; }
  .position-label { text-align: center; color: #6b7280; font-size: 13px; margin-bottom: 20px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">Warteliste</span>
    <h1>{{ $reservation->media->title }}</h1>
    <p class="meta">
        @if($reservation->media->author){{ $reservation->media->author }} &middot; @endif
        {{ $reservation->media->type->label() }}
    </p>

    <p>Hallo {{ $reservation->user->name }},</p>
    <p>das Medium wurde zurückgegeben. Sie rücken in der Warteliste vor:</p>

    <div class="position">{{ $position }}</div>
    <div class="position-label">Ihre aktuelle Position in der Warteliste</div>

    <p>Sie werden eine weitere Nachricht erhalten, sobald das Medium für Sie zur Abholung bereitsteht.</p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Sie erhalten diese E-Mail, weil Sie ein Medium reserviert haben.
    </div>
</div>
</body>
</html>
