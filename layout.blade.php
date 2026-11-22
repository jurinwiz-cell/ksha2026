@version(2)
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $layout_info->title ?? '사회주택' }}</title>
    
    <!-- External Libraries -->
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <link rel="stylesheet" href="/static/vendor/swiper/swiper-bundle.min.css" />
    <script src="/static/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="/static/vendor/js/chart.min.js"></script>
    
    <!-- Theme CSS -->
    <load target="style.css" />

    <style>
        :root {
            --accent-color: #{{ $layout_info->accent_color ?? 'FF8C00' }};
        }
    </style>
</head>
<body class="font-sans text-slate-800 bg-white">

    <!-- Navigation -->
    <nav id="mainNav" class="fixed top-0 w-full z-50 bg-white/0 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="font-black text-xl text-blue-900 tracking-tight">
                @if($layout_info->site_logo)
                    <img src="{{ $layout_info->site_logo }}" alt="Logo" class="h-8" />
                @else
                    SOCIAL HOUSING
                @endif
            </div>
            <div class="hidden md:flex gap-8 text-sm font-bold">
                <a href="#hero" class="hover:text-blue-600 transition-colors">홈</a>
                <a href="#notices" class="hover:text-blue-600 transition-colors">공지사항</a>
                <a href="#widgets" class="hover:text-blue-600 transition-colors">최신글</a>
                <a href="#archive" class="hover:text-blue-600 transition-colors">자료실</a>
                <a href="#business" class="hover:text-blue-600 transition-colors">사업안내</a>
            </div>
        </div>
    </nav>

    <!-- [Section 1] Hero -->
    <section id="hero" class="min-h-screen flex items-center justify-center pt-16 bg-gradient-to-br from-blue-900 to-blue-700 text-white overflow-hidden relative" style="@if($layout_info->hero_img) background-image: url('{{ $layout_info->hero_img }}'); background-size: cover; background-position: center; @endif">
        @if($layout_info->hero_img)
            <div class="absolute inset-0 bg-blue-900/60 backdrop-blur-[2px]"></div>
        @endif
        <div class="max-w-7xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center relative z-10">
            <div class="space-y-6">
                <h1 class="text-5xl md:text-7xl font-black leading-tight">
                    {{ $layout_info->hero_title }}
                </h1>
                <p class="text-lg md:text-xl text-blue-100 font-medium max-w-lg">
                    {{ $layout_info->hero_description }}
                </p>
                <div class="pt-4">
                    <a href="#business" class="inline-block px-8 py-4 bg-[var(--accent-color)] hover:scale-105 transition-transform rounded-full font-black text-white shadow-xl shadow-orange-900/20">
                        자세히 보기
                    </a>
                </div>
            </div>
            <div class="flex justify-center relative">
                <div class="hero-svg-container">
                    <svg width="400" height="400" viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="100" y="150" width="200" height="150" rx="20" fill="white" fill-opacity="0.1" />
                        <path d="M80 170L200 70L320 170" stroke="white" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="200" cy="225" r="40" stroke="white" stroke-width="8" class="animate-pulse" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="absolute top-1/4 right-0 w-96 h-96 bg-blue-400/20 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-1/4 left-0 w-64 h-64 bg-indigo-500/20 blur-[100px] rounded-full"></div>
    </section>

    <!-- [Section 2] Notice Slider -->
    <section id="notices" class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-end mb-12">
                <div class="space-y-2">
                    <h2 class="text-sm font-black text-blue-600 uppercase tracking-widest">Notice</h2>
                    <h3 class="text-3xl font-black text-slate-900">최신 소식</h3>
                </div>
                <a href="{getSiteUrl('', 'mid', $layout_info->notice_mid)}" class="text-sm font-bold text-slate-400 hover:text-blue-600">전체보기 +</a>
            </div>
            
            <div class="swiper notice-swiper">
                <div class="swiper-wrapper">
                    @php
                        if($layout_info->notice_target) {
                            $args = new stdClass();
                            $args->module_srl = explode(',', $layout_info->notice_target);
                            $args->list_count = 5;
                            
                            if ($layout_info->notice_extra_var_name && $layout_info->notice_extra_var_value) {
                                $args->extra_vars_name = $layout_info->notice_extra_var_name;
                                $args->extra_vars_value = $layout_info->notice_extra_var_value;
                            }
                            $notice_list = executeQuery('document.getDocumentList', $args)->data;
                        } else {
                            $notice_list = [];
                        }
                    @endphp
                    @if(isset($notice_list) && count($notice_list))
                        @foreach($notice_list as $doc)
                            <div class="swiper-slide">
                                <div class="bg-white p-8 rounded-3xl border border-slate-100 hover:shadow-xl transition-all">
                                    <div class="text-xs font-bold text-blue-500 mb-2">{{ zdate($doc->regdate, 'Y.m.d') }}</div>
                                    <h4 class="text-lg font-bold text-slate-800 line-clamp-2 mb-4">{{ $doc->title }}</h4>
                                    <a href="{{ getSiteUrl('', 'document_srl', $doc->document_srl) }}" class="text-sm font-black text-slate-400">READ MORE</a>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="swiper-slide text-center text-slate-400">등록된 공지사항이 없습니다.</div>
                    @endif
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </section>

    <!-- [Section 3] Managed Widgets -->
    <section id="widgets" class="py-24">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Widget 1 -->
                <div class="widget-box">
                    <h4 class="text-lg font-black mb-6 border-l-4 border-blue-600 pl-4">{{ $layout_info->w1_title }}</h4>
                    <div class="bg-white rounded-3xl border border-slate-100 p-2 overflow-hidden shadow-sm min-h-[300px]">
                        @if($layout_info->w1_target)
                            <img src="./widgets/content/skins/default/images/widget.gif" style="display:none" />
                            @widget('content', [
                                'module_srls' => $layout_info->w1_target,
                                'skin' => $layout_info->w1_style == 'list' ? 'default' : ($layout_info->w1_style == 'webzine' ? 'webzine' : 'gallery'),
                                'list_count' => 5,
                                'title_length' => 40,
                                'option_view' => 'extra_vars',
                                'extra_var_name' => $layout_info->w1_extra_var_name ?? '',
                                'extra_var_value' => $layout_info->w1_extra_var_value ?? ''
                            ])
                        @else
                            <div class="py-20 text-center text-slate-300 text-xs font-bold">관리자에서 MID를 설정해주세요</div>
                        @endif
                    </div>
                </div>
                <!-- Widget 2 -->
                <div class="widget-box">
                    <h4 class="text-lg font-black mb-6 border-l-4 border-indigo-600 pl-4">{{ $layout_info->w2_title }}</h4>
                    <div class="bg-white rounded-3xl border border-slate-100 p-2 overflow-hidden shadow-sm min-h-[300px]">
                        @if($layout_info->w2_target)
                            @widget('content', [
                                'module_srls' => $layout_info->w2_target,
                                'skin' => $layout_info->w2_style == 'list' ? 'default' : ($layout_info->w2_style == 'webzine' ? 'webzine' : 'gallery'),
                                'list_count' => 5,
                                'title_length' => 40,
                                'option_view' => 'extra_vars',
                                'extra_var_name' => $layout_info->w2_extra_var_name ?? '',
                                'extra_var_value' => $layout_info->w2_extra_var_value ?? ''
                            ])
                        @else
                            <div class="py-20 text-center text-slate-300 text-xs font-bold">관리자에서 MID를 설정해주세요</div>
                        @endif
                    </div>
                </div>
                <!-- Widget 3 -->
                <div class="widget-box">
                    <h4 class="text-lg font-black mb-6 border-l-4 border-emerald-600 pl-4">{{ $layout_info->w3_title }}</h4>
                    <div class="bg-white rounded-3xl border border-slate-100 p-2 overflow-hidden shadow-sm min-h-[300px]">
                        @if($layout_info->w3_target)
                            @widget('content', [
                                'module_srls' => $layout_info->w3_target,
                                'skin' => $layout_info->w3_style == 'list' ? 'default' : ($layout_info->w3_style == 'webzine' ? 'webzine' : 'gallery'),
                                'list_count' => 5,
                                'title_length' => 40,
                                'option_view' => 'extra_vars',
                                'extra_var_name' => $layout_info->w3_extra_var_name ?? '',
                                'extra_var_value' => $layout_info->w3_extra_var_value ?? ''
                            ])
                        @else
                            <div class="py-20 text-center text-slate-300 text-xs font-bold">관리자에서 MID를 설정해주세요</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- [Section 4] Archive -->
    <section id="archive" class="py-24 bg-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center space-y-4 mb-16">
                <h2 class="text-sm font-black text-blue-400 uppercase tracking-widest">Archive</h2>
                <h3 class="text-4xl font-black">{{ $layout_info->archive_title }}</h3>
                <p class="text-slate-400 max-w-2xl mx-auto">{{ $layout_info->archive_desc }}</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @for($i=1; $i<=4; $i++)
                <div class="bg-white/5 rounded-3xl p-6 border border-white/10 hover:bg-white/10 transition-colors group">
                    <div class="w-full aspect-[3/4] bg-white/10 rounded-2xl mb-6 flex items-center justify-center overflow-hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="text-white/20 group-hover:scale-110 transition-transform"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><polyline points="9 15 12 12 15 15"></polyline></svg>
                    </div>
                    <h4 class="font-bold mb-2 text-sm">사회주택 활성화 보고서 #{{ $i }}</h4>
                    <p class="text-[10px] text-slate-500 mb-4">2026.04.21</p>
                    <button class="w-full py-3 bg-white/10 hover:bg-white/20 rounded-xl text-[10px] font-black transition-colors">내려받기</button>
                </div>
                @endfor
            </div>
        </div>
    </section>

    <!-- [Section 5] Business Info -->
    <section id="business" class="py-24">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="space-y-8">
                    <div class="space-y-2">
                        <h2 class="text-sm font-black text-blue-600 uppercase tracking-widest">Supply Status</h2>
                        <h3 class="text-4xl font-black text-slate-900">사회주택 공급 현황</h3>
                    </div>
                    <div class="bg-slate-50 rounded-3xl p-8 border border-slate-100 shadow-sm">
                        <canvas id="supplyChart" height="200"></canvas>
                    </div>
                </div>
                <div class="space-y-8">
                    <h4 class="text-xl font-black">최근 사업 공모 현황</h4>
                    <div class="space-y-4">
                        @for($i=1; $i<=3; $i++)
                        <div class="flex items-center gap-6 p-4 border-b border-slate-100 hover:bg-slate-50 transition-colors rounded-xl">
                            <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600 font-black">0{{ $i }}</div>
                            <div class="flex-grow">
                                <p class="font-bold text-slate-800">2026년 상반기 사회주택 공모 제{{ $i }}차</p>
                                <p class="text-xs text-slate-400">접수기간: 2026.05.01 ~ 05.31</p>
                            </div>
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-600 text-[10px] font-black rounded-full">진행중</span>
                        </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-12 border-t border-slate-100 text-center text-slate-500 text-xs font-bold">
        <div class="mb-4 text-slate-400 uppercase tracking-widest">
            {!! nl2br(e($layout_info->footer_info)) !!}
        </div>
        <div>{{ $layout_info->copyright }}</div>
    </footer>

    <!-- Theme JS -->
    <load target="main.js" />
</body>
</html>

