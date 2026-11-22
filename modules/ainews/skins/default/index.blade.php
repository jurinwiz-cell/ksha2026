@extends($layout_info->layout_file ?: 'default')

@section('content')
<div class="ainews-newsletter-container" style="max-width: 800px; margin: 0 auto; font-family: 'Inter', sans-serif;">
    <h2 style="border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">AI 뉴스레터 목록</h2>

    @if(count($newsletters))
        <ul style="list-style: none; padding: 0;">
            @foreach($newsletters as $newsletter)
                <li style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa;">
                    <h3 style="margin-top: 0;">{{ $newsletter->title }}</h3>
                    <div class="newsletter-content" style="color: #555; line-height: 1.6;">
                        {!! $newsletter->content !!}
                    </div>
                    <div style="margin-top: 10px; font-size: 0.9em; color: #888;">
                        발행일: {{ zdate($newsletter->publish_date ?: $newsletter->regdate, 'Y-m-d H:i') }}
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p style="text-align: center; color: #777; padding: 40px 0;">아직 발행된 뉴스레터가 없습니다.</p>
    @endif
</div>
@endsection
