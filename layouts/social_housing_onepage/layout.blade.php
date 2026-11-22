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
</head>
<body class="font-sans text-slate-800 bg-white">

    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="font-black text-xl text-blue-900 tracking-tight">SOCIAL HOUSING</div>
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
    <section id="hero" class="min-h-screen flex items-center justify-center pt-16 bg-gradient-to-br from-blue-900 to-blue-700 text-white overflow-hidden relative">
        @php
            try {
                $supply_stats_query = "SELECT
                    SUM(household_count) as total_households,
                    COUNT(DISTINCT company_name_raw) as total_orgs,
                    COUNT(*) as total_projects,
                    COUNT(CASE WHEN completion_status = '준공' THEN 1 END) as completed_projects,
                    COUNT(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.public_entity')) NOT LIKE '%민간%' AND
                    JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.public_entity')) != '' THEN 1 END) as public_projects
                    FROM tb_social_housing_supply WHERE deleted_at IS NULL AND completion_status IN ('준공', '미준공', '미착공', '종료')";

                $shm_pdo = new \PDO("mysql:host=localhost;dbname=socialhousing_db;charset=utf8mb4", 'root', '1@tkghlwnxor');
                $shm_pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

                $stmtStats = $shm_pdo->prepare($supply_stats_query);
                $stmtStats->execute();
                $stats_result = (object) $stmtStats->fetch();

                $hero_households = $stats_result->total_households ?? 8650;
                $hero_orgs = $stats_result->total_orgs ?? 45;
                $hero_projects = $stats_result->total_projects ?? 0;
                $hero_completed = $stats_result->completed_projects ?? 0;
            } catch (\Exception $e) {
                $hero_households = 8650;
                $hero_orgs = 45;
                $hero_projects = 120;
                $hero_completed = 98;
            }

            $hero_gongmo_list = [];
            if($layout_info->notice_target) {
                $h_targets = array_filter(explode(',', $layout_info->notice_target));
                $h_srls = [];
                foreach($h_targets as $t) {
                    if(!is_numeric($t)) {
                        $m_info = getModel('module')->getModuleInfoByMid(trim($t));
                        if($m_info) $h_srls[] = $m_info->module_srl;
                    } else {
                        $h_srls[] = $t;
                    }
                }

                if(count($h_srls) > 0) {
                    $h_module_in = implode(',', array_fill(0, count($h_srls), '?'));
                    $h_ev_query = "SELECT document_srl FROM document_extra_vars WHERE module_srl IN ($h_module_in)
                    AND (eid = '마감' OR eid = 'status' OR value = '진행중')";
                    $h_stmtEv = \DB::getInstance()->prepare($h_ev_query);
                    $h_stmtEv->execute($h_srls);
                    $h_ev_docs = $h_stmtEv->fetchAll();
                    $h_ev_srls = [];
                    foreach($h_ev_docs as $e) {
                        $h_ev_srls[] = $e->document_srl;
                    }

                    $h_args = new stdClass();
                    $h_args->module_srl = implode(',', $h_srls);
                    $h_args->list_count = 2;
                    $h_args->sort_index = 'list_order';
                    $h_args->order_type = 'asc';

                    if(count($h_ev_srls) > 0) {
                        $h_args->document_srl = implode(',', $h_ev_srls);
                    } else {
                        $h_cat_query = "SELECT category_srl FROM document_categories WHERE module_srl IN ($h_module_in) AND title LIKE '%사업공모%'";
                        $h_stmtCat = \DB::getInstance()->prepare($h_cat_query);
                        $h_stmtCat->execute($h_srls);
                        $h_categories = $h_stmtCat->fetchAll();
                        $h_cat_srls = [];
                        foreach($h_categories as $c) {
                            $h_cat_srls[] = $c->category_srl;
                        }
                        if(count($h_cat_srls) > 0) {
                            $h_args->category_srl = implode(',', $h_cat_srls);
                        }
                    }

                    $h_gongmo_out = executeQuery('document.getDocumentList', $h_args);
                    if($h_gongmo_out->toBool() && $h_gongmo_out->data) {
                        $hero_gongmo_list = is_array($h_gongmo_out->data) ? $h_gongmo_out->data : [$h_gongmo_out->data];
                    }
                }
            }
        @endphp

        <div class="max-w-7xl mx-auto px-4 py-16 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
            <!-- Left Hero Content (7 Cols) -->
            <div class="lg:col-span-7 space-y-6">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-xs font-bold tracking-wide text-blue-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>사회주택 공급 &amp; 공모 종합안내</span>
                </div>
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black leading-tight tracking-tight drop-shadow-sm">
                    {{ $layout_info->hero_title }}
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-blue-100/90 font-medium max-w-xl leading-relaxed">
                    {{ $layout_info->hero_description }}
                </p>
                <div class="pt-2 flex flex-wrap gap-4 items-center">
                    <a href="#business"
                        class="px-8 py-4 bg-[#{{ $layout_info->accent_color ?? 'FF8C00' }}] hover:scale-105 transition-all rounded-full font-black text-white shadow-xl shadow-orange-950/30 flex items-center gap-2 text-sm">
                        <span>공급현황 자세히보기</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </a>
                    <a href="#notices"
                        class="px-7 py-4 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 rounded-full font-bold text-white text-sm transition-colors">
                        최신 소식 확인
                    </a>
                </div>
            </div>

            <!-- Right Glassmorphism Dashboard (5 Cols) -->
            <div class="lg:col-span-5 w-full">
                <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl p-6 sm:p-7 shadow-2xl space-y-6 text-white">
                    <div class="flex justify-between items-center border-b border-white/15 pb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-500/30 flex items-center justify-center border border-white/20">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-black text-sm tracking-wide">사회주택 공급 실적 현황</h3>
                                <p class="text-[11px] text-blue-200/80">실시간 집계 데이터</p>
                            </div>
                        </div>
                        <a href="#business" class="text-xs text-blue-200 hover:text-white font-bold transition-colors">상세보기 &rarr;</a>
                    </div>

                    <div class="grid grid-cols-2 gap-3.5">
                        <div class="bg-black/20 backdrop-blur-sm rounded-2xl p-4 border border-white/10 hover:border-white/25 transition-all">
                            <div class="text-[11px] font-bold text-blue-200 mb-1">총 공급 호수</div>
                            <div class="text-2xl font-black text-white tracking-tight flex items-baseline gap-1">
                                {{ number_format($hero_households) }}
                                <span class="text-xs font-normal text-blue-300">세대</span>
                            </div>
                        </div>
                        <div class="bg-black/20 backdrop-blur-sm rounded-2xl p-4 border border-white/10 hover:border-white/25 transition-all">
                            <div class="text-[11px] font-bold text-blue-200 mb-1">공급 개소</div>
                            <div class="text-2xl font-black text-white tracking-tight flex items-baseline gap-1">
                                {{ number_format($hero_projects) }}
                                <span class="text-xs font-normal text-blue-300">개소</span>
                            </div>
                        </div>
                        <div class="bg-black/20 backdrop-blur-sm rounded-2xl p-4 border border-white/10 hover:border-white/25 transition-all">
                            <div class="text-[11px] font-bold text-blue-200 mb-1">준공 완료 건수</div>
                            <div class="text-2xl font-black text-emerald-400 tracking-tight flex items-baseline gap-1">
                                {{ number_format($hero_completed) }}
                                <span class="text-xs font-normal text-emerald-300">건</span>
                            </div>
                        </div>
                        <div class="bg-black/20 backdrop-blur-sm rounded-2xl p-4 border border-white/10 hover:border-white/25 transition-all">
                            <div class="text-[11px] font-bold text-blue-200 mb-1">회원 조합</div>
                            <div class="text-2xl font-black text-white tracking-tight flex items-baseline gap-1">
                                {{ number_format($hero_orgs) }}
                                <span class="text-xs font-normal text-blue-300">곳</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-white/15 space-y-2.5">
                        <div class="flex justify-between items-center text-xs font-bold text-blue-200">
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                                최근 사업 공모
                            </span>
                        </div>

                        @if(count($hero_gongmo_list) > 0)
                            @foreach($hero_gongmo_list as $hg_doc)
                            @php
                                $hg_item = new documentItem();
                                $hg_item->setAttribute($hg_doc);
                                $hg_vars = $hg_item->getExtraVars();
                                $hg_status = '진행중';
                                $hg_end = '';
                                if($hg_vars) {
                                    foreach($hg_vars as $ev) {
                                        if(strpos($ev->name, '종료') !== false || strpos($ev->name, '마감') !== false || $ev->eid == 'end_date') $hg_end = $ev->value;
                                        if(strpos($ev->name, '마감') !== false || $ev->eid == 'status') $hg_status = $ev->value;
                                    }
                                }
                            @endphp
                            <a href="{{ getSiteUrl('', 'document_srl', $hg_doc->document_srl) }}"
                               class="block p-3 rounded-xl bg-black/20 hover:bg-black/35 border border-white/10 hover:border-white/20 transition-all group">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="text-xs font-bold text-white group-hover:text-blue-200 transition-colors truncate">
                                        {{ $hg_doc->title }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black shrink-0 {{ in_array($hg_status, ['진행중', '모집중', '공고중']) ? 'bg-emerald-500/80 text-white' : 'bg-white/20 text-slate-200' }}">
                                        {{ $hg_status }}
                                    </span>
                                </div>
                                @if($hg_end)
                                <div class="text-[10px] text-blue-200/70">
                                    마감: {{ $hg_end }}
                                </div>
                                @endif
                            </a>
                            @endforeach
                        @else
                            <div class="p-3 text-center text-xs text-blue-200/70 bg-black/10 rounded-xl">
                                현재 진행 중인 공모 일정이 없습니다.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
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
                        if($layout_info->notice_mid || $layout_info->notice_target) {
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
                            @php
                                $doc_item = new documentItem();
                                $doc_item->setAttribute($doc);
                                $thumbnail = $doc_item->getThumbnail(400, 240, 'crop');

                                // 주요 시한 및 확장변수 추출
                                $doc_ext_vars = $doc_item->getExtraVars();
                                $period_start = '';
                                $period_end = '';
                                $status_badge = '';

                                if($doc_ext_vars) {
                                    foreach($doc_ext_vars as $ev) {
                                        if(strpos($ev->name, '시작') !== false || $ev->eid == 'start_date') $period_start = $ev->value;
                                        if(strpos($ev->name, '종료') !== false || strpos($ev->name, '마감') !== false || $ev->eid == 'end_date' || $ev->eid == 'due_date') $period_end = $ev->value;
                                        if((strpos($ev->name, '상태') !== false || $ev->eid == 'status') && in_array($ev->value, ['진행중', '모집중', '마감', '접수중', '공고중'])) {
                                            $status_badge = $ev->value;
                                        }
                                    }
                                }
                                $deadline_text = '';
                                if($period_end) {
                                    $deadline_text = ($period_start ? $period_start . ' ~ ' : '기한: ') . $period_end;
                                }

                                $doc_summary = $doc_item->getContentText(110);
                                $readed_count = (int)$doc->readed_count;
                            @endphp
                            <div class="swiper-slide h-auto">
                                <div class="bg-white rounded-3xl border border-slate-100 hover:shadow-xl transition-all overflow-hidden flex flex-col h-full group">
                                    @if($thumbnail)
                                    <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                        <img src="{{ $thumbnail }}" alt="{{ $doc->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                        @if($status_badge)
                                        <span class="absolute top-4 left-4 px-3 py-1 rounded-full text-xs font-black shadow-sm {{ in_array($status_badge, ['진행중', '모집중', '접수중']) ? 'bg-emerald-500 text-white' : 'bg-slate-700/80 text-white' }}">
                                            {{ $status_badge }}
                                        </span>
                                        @endif
                                    </div>
                                    @endif

                                    <div class="p-6 md:p-8 flex flex-col flex-grow">
                                        <div class="flex items-center justify-between text-xs text-slate-400 mb-3">
                                            <span class="font-bold text-blue-600">{{ zdate($doc->regdate, 'Y.m.d') }}</span>
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                {{ number_format($readed_count) }}
                                            </span>
                                        </div>

                                        <h4 class="text-lg font-bold text-slate-800 line-clamp-2 mb-2 group-hover:text-blue-600 transition-colors">
                                            <a href="{{ getSiteUrl('', 'document_srl', $doc->document_srl) }}">{{ $doc->title }}</a>
                                        </h4>

                                        @if($doc_summary)
                                        <p class="text-sm text-slate-500 line-clamp-2 mb-4 leading-relaxed flex-grow">
                                            {{ $doc_summary }}
                                        </p>
                                        @endif

                                        @if($deadline_text)
                                        <div class="mb-4 flex items-center gap-1.5 text-xs text-rose-500 bg-rose-50/80 px-3 py-1.5 rounded-lg font-bold w-fit">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="truncate">{{ $deadline_text }}</span>
                                        </div>
                                        @endif

                                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                                            <a href="{{ getSiteUrl('', 'document_srl', $doc->document_srl) }}" class="text-xs font-black text-slate-400 group-hover:text-blue-600 flex items-center gap-1 transition-colors">
                                                READ MORE
                                                <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
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
                    <div class="bg-white rounded-3xl border border-slate-100 p-2 overflow-hidden shadow-sm">
                        @if($layout_info->w1_mid)
                            <img src="./widgets/content/skins/default/images/widget.gif" style="display:none" />
                            @widget('content', [
                                'module_srls' => $layout_info->w1_target,
                                'skin' => $layout_info->w1_style == 'list' ? 'default' : ($layout_info->w1_style == 'webzine' ? 'webzine' : 'gallery'),
                                'list_count' => 5,
                                'title_length' => 40,
                                'option_view' => 'extra_vars',
                                'extra_var_name' => $layout_info->w1_extra_var_name,
                                'extra_var_value' => $layout_info->w1_extra_var_value
                            ])
                        @else
                            <div class="py-20 text-center text-slate-300 text-xs font-bold">관리자에서 MID를 설정해주세요</div>
                        @endif
                    </div>
                </div>
                <!-- Widget 2 -->
                <div class="widget-box">
                    <h4 class="text-lg font-black mb-6 border-l-4 border-indigo-600 pl-4">{{ $layout_info->w2_title }}</h4>
                    <div class="bg-white rounded-3xl border border-slate-100 p-2 overflow-hidden shadow-sm">
                        @if($layout_info->w2_mid)
                            @widget('content', [
                                'module_srls' => $layout_info->w2_target,
                                'skin' => $layout_info->w2_style == 'list' ? 'default' : ($layout_info->w2_style == 'webzine' ? 'webzine' : 'gallery'),
                                'list_count' => 5,
                                'title_length' => 40,
                                'option_view' => 'extra_vars',
                                'extra_var_name' => $layout_info->w2_extra_var_name,
                                'extra_var_value' => $layout_info->w2_extra_var_value
                            ])
                        @else
                            <div class="py-20 text-center text-slate-300 text-xs font-bold">관리자에서 MID를 설정해주세요</div>
                        @endif
                    </div>
                </div>
                <!-- Widget 3 -->
                <div class="widget-box">
                    <h4 class="text-lg font-black mb-6 border-l-4 border-emerald-600 pl-4">{{ $layout_info->w3_title }}</h4>
                    <div class="bg-white rounded-3xl border border-slate-100 p-2 overflow-hidden shadow-sm">
                        @if($layout_info->w3_mid)
                            @widget('content', [
                                'module_srls' => $layout_info->w3_target,
                                'skin' => $layout_info->w3_style == 'list' ? 'default' : ($layout_info->w3_style == 'webzine' ? 'webzine' : 'gallery'),
                                'list_count' => 5,
                                'title_length' => 40,
                                'option_view' => 'extra_vars',
                                'extra_var_name' => $layout_info->w3_extra_var_name,
                                'extra_var_value' => $layout_info->w3_extra_var_value
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
                <h3 class="text-4xl font-black">자료실</h3>
                <p class="text-slate-400 max-w-2xl mx-auto">사회주택과 관련된 각종 보고서 및 자료를 내려받으실 수 있습니다.</p>
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

    <footer class="py-12 border-t border-slate-100 text-center text-slate-400 text-xs font-bold uppercase tracking-widest">
        &copy; 2026 Social Housing Association. All rights reserved.
    </footer>

    <!-- Theme JS -->
    <load target="main.js" />
</body>
</html>

