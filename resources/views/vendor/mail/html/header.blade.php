@props(['url'])
@php
    $appName = config('app.name');
    $cmsGlobals = app(\App\Support\Cms\GlobalsService::class)->get('brand');
    $lightLogo = $cmsGlobals['logo_light_url'] ?? null;
@endphp
<tr>
    <td>
        <table class="content" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td>
                    <div class="header-stripe">&nbsp;</div>
                </td>
            </tr>
        </table>
    </td>
</tr>
<tr>
    <td>
        <table class="content" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td class="header-band">
                    <a href="{{ $url }}" style="text-decoration:none;display:inline-block;">
                        @if ($lightLogo)
                            <img src="{{ $lightLogo }}" alt="{{ $appName }}" class="logo" />
                        @endif
                        <span class="brand-name">{{ $appName }}</span>
                    </a>
                </td>
            </tr>
        </table>
    </td>
</tr>
