{{--
    Markenkopf für alle Benachrichtigungs-Mails.

    Bildmarke als PNG statt SVG: Viele E-Mail-Clients rendern kein SVG.
    Die Wortmarke steht als Text daneben, damit der Absender auch dann
    erkennbar bleibt, wenn der Client entfernte Bilder blockiert – das ist
    bei Outlook und Gmail die Voreinstellung.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0"
       style="margin:0 0 20px;border-collapse:collapse;">
    <tr>
        <td style="vertical-align:middle;padding-right:10px;">
            <img src="{{ config('app.url') }}/icon-192.png" width="32" height="32" alt=""
                 style="display:block;width:32px;height:32px;border:0;border-radius:8px;">
        </td>
        <td style="vertical-align:middle;">
            <span style="font-family:Arial,sans-serif;font-size:17px;font-weight:bold;
                         color:#111827;letter-spacing:-0.2px;">{{ config('app.name') }}</span>
        </td>
    </tr>
</table>
