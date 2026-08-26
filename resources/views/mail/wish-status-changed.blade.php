<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  h1 { font-size: 20px; color: #111827; margin: 0 0 8px; }
  .status-angenommen { background: #d1fae5; color: #065f46; }
  .status-abgelehnt  { background: #fee2e2; color: #991b1b; }
  .status-beobachten { background: #fef9c3; color: #92400e; }
  .status-eingereicht{ background: #dbeafe; color: #1e40af; }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
  .note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #1e40af; margin-top: 16px; }
  .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <h1>Ihr Medienwunsch wurde bearbeitet</h1>

    @php
        $statusClass = 'status-' . $wish->status->value;
    @endphp
    <span class="badge {{ $statusClass }}">{{ $wish->status->label() }}</span>

    <p>Hallo {{ $wish->user->name }},</p>
    <p>
        Ihr Medienwunsch
        @if($wish->title)
            <strong>„{{ $wish->title }}"</strong>
        @else
            zum Thema <strong>„{{ mb_substr($wish->topic_freetext ?? '', 0, 80) }}"</strong>
        @endif
        wurde von unseren Kurat:innen bearbeitet.
    </p>

    <p>
        <strong>Neuer Status:</strong> {{ $wish->status->label() }}
    </p>

    @if($wish->status->value === 'angenommen')
        <p>Der Wunsch wurde angenommen und wird für eine Anschaffung geprüft.</p>
    @elseif($wish->status->value === 'abgelehnt')
        <p>Der Wunsch wurde leider nicht angenommen. Falls Sie Fragen haben, sprechen Sie gerne unsere Kurat:innen an.</p>
    @elseif($wish->status->value === 'beobachten')
        <p>Der Wunsch wird beobachtet – wir prüfen, ob weiteres Interesse besteht.</p>
    @endif

    @if($wish->curator_note)
        <div class="note">
            <strong>Anmerkung der Kuration:</strong><br>
            {{ $wish->curator_note }}
        </div>
    @endif

    <p style="margin-top: 24px;">
        <a href="{{ config('app.url') }}/wuensche" class="btn">Meine Wünsche ansehen</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Sie erhalten diese E-Mail, weil Sie einen Medienwunsch eingereicht haben.
    </div>
</div>
</body>
</html>
