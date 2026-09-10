@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" style="max-height: 60px; max-width: 200px; object-fit: contain;">
            </div>
        </a>
    </td>
</tr>