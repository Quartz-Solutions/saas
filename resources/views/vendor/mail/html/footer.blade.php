@php
    $appName = config('app.name');
    $appUrl = config('app.url');
@endphp
<tr>
    <td>
        <table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td class="content-cell" align="center" style="padding-top:18px;padding-bottom:24px;">
                    {{ Illuminate\Mail\Markdown::parse($slot) }}
                    <p style="margin-top:10px;">
                        <a href="{{ $appUrl }}">{{ $appName }}</a>
                    </p>
                </td>
            </tr>
        </table>
    </td>
</tr>
