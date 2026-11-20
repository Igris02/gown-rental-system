{{ $shop['name'] }}

{{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($shop['address'])
{{ $shop['address'] }}
@endif
@if ($shop['phone'])
Phone: {{ $shop['phone'] }}
@endif
