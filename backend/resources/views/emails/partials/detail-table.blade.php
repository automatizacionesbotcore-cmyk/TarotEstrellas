<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#FAF7F2;border:1px solid #E8DFD6;border-left:4px solid {{ $accent ?? '#5A3D8B' }};border-radius:8px;margin:22px 0;">
    <tr>
        <td style="padding:16px 18px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                @foreach($rows as $label => $value)
                    @if($value !== null && $value !== '')
                        <tr>
                            <td style="padding:7px 0;width:38%;font-size:14px;line-height:1.4;color:#756A80;vertical-align:top;">{{ $label }}</td>
                            <td style="padding:7px 0;font-size:14px;line-height:1.4;color:#2B2238;font-weight:600;vertical-align:top;">{!! $value !!}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </td>
    </tr>
</table>
