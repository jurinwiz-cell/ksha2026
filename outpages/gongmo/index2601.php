<?php
$isRhymix = defined('RX_BASEDIR') || defined('XE_CORE');

// Determine correct base URL to prevent Mixed Content errors
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$baseUrl = $host ? $scheme . '://' . $host : '';

$headContent = '
    <script>
        window.tailwind = {
            config: {
                important: "#gongmo-portal",
                corePlugins: {
                    preflight: false
                }
            }
        };
    </script>
    <script src="' . $baseUrl . '/static/vendor/js/tailwindcss.js"></script>
    <link rel="stylesheet" href="' . $baseUrl . '/static/vendor/fonts/pretendard/pretendard.css">
    
    <style>
        :root {
            --primary-indigo: #4338ca;
            --primary-blue: #2563eb;
            --primary-cyan: #0891b2;
            --primary-emerald: #059669;
        }

        /* Scoped reset and base styles for gongmo-portal */
        #gongmo-portal {
            font-family: \'Pretendard\', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        #gongmo-portal *, #gongmo-portal ::before, #gongmo-portal ::after {
            box-sizing: border-box;
            border-width: 0;
            border-style: solid;
            border-color: #e5e7eb;
        }

        #gongmo-portal img, #gongmo-portal svg {
            display: block;
            vertical-align: middle;
            max-width: 100%;
            height: auto;
        }

        #gongmo-portal a {
            text-decoration: inherit;
            color: inherit;
        }
        
        #gongmo-portal button {
            background-color: transparent;
            background-image: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 100%;
            line-height: inherit;
            margin: 0;
            padding: 0;
            text-transform: none;
        }

        #gongmo-portal table {
            text-indent: 0;
            border-color: inherit;
            border-collapse: collapse;
            width: 100%;
        }

        #gongmo-portal h1, #gongmo-portal h2, #gongmo-portal h3, #gongmo-portal h4, #gongmo-portal h5, #gongmo-portal h6 {
            font-size: inherit;
            font-weight: inherit;
            margin: 0;
        }

        #gongmo-portal p, #gongmo-portal ul, #gongmo-portal ol {
            margin: 0;
            padding: 0;
        }

        #gongmo-portal ul, #gongmo-portal ol {
            list-style: none;
        }

        #gongmo-portal .main-tab-content,
        #gongmo-portal .sub-tab-content {
            display: none;
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        #gongmo-portal .main-tab-content.active,
        #gongmo-portal .sub-tab-content.active {
            display: block;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom scrollbar for horizontal tabs */
        #gongmo-portal .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        #gongmo-portal .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        #gongmo-portal details > summary::-webkit-details-marker {
            display: none;
        }

        #gongmo-portal .glass-header {
            backdrop-filter: blur(12px);
            background-color: rgba(255, 255, 255, 0.8);
        }

        #gongmo-portal .gradient-blue {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }

        #gongmo-portal .gradient-cyan {
            background: linear-gradient(135deg, #0891b2 0%, #155e75 100%);
        }

        #gongmo-portal .gradient-emerald {
            background: linear-gradient(135deg, #059669 0%, #064e3b 100%);
        }

        #gongmo-portal .gradient-indigo {
            background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
        }
    </style>
';

if ($isRhymix) {
    Context::addHtmlHeader($headContent);
}
?>
<?php if (!$isRhymix): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공공임대주택 통합 안내 포털</title>
    <?php echo $headContent; ?>
</head>
<body class="bg-[#f8fafc] text-slate-800 p-0 md:p-6 lg:p-10 selection:bg-indigo-100 selection:text-indigo-900">
<?php endif; ?>

<div id="gongmo-portal" class="bg-[#f8fafc] text-slate-800 p-0 md:p-6 lg:p-10 selection:bg-indigo-100 selection:text-indigo-900 w-full overflow-hidden">
    <!-- Main Container -->
    <div class="max-w-7xl mx-auto bg-white shadow-[0_20px_50px_rgba(0,0,0,0.08)] md:rounded-3xl overflow-hidden border border-slate-100">

        <!-- App Header -->
        <header class="gradient-indigo text-white p-8 md:p-12 text-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 pointer-events-none"
                style="background-image: radial-gradient(rgba(255,255,255,0.4) 1px, transparent 1px); background-size: 20px 20px;">
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-3 drop-shadow-sm">공공임대주택 통합 안내 포털</h1>
                <p class="text-indigo-100 text-lg md:text-xl font-medium opacity-90">민간참여형 특화 및 공공주체별 사업 공모 안내</p>
            </div>
        </header>

        <!-- Main Tab Navigation (Level 1) -->
        <nav class="flex flex-col sm:flex-row bg-slate-50 border-b border-slate-200">
            <button
                class="main-tab-link active flex-1 text-center py-5 px-6 font-bold text-indigo-700 border-b-4 border-indigo-600 bg-white transition-all duration-300 text-lg"
                onclick="openMainTab(event, 'main-doc-1')">
                2026년 상반기 특화형 공고
            </button>
            <button
                class="main-tab-link flex-1 text-center py-5 px-6 font-bold text-slate-500 border-b-4 border-transparent hover:bg-white hover:text-indigo-600 transition-all duration-300 text-lg"
                onclick="openMainTab(event, 'main-doc-2')">
                공공주체별 공모 안내
            </button>
            <button
                class="main-tab-link flex-1 text-center py-5 px-6 font-bold text-slate-500 border-b-4 border-transparent hover:bg-white hover:text-indigo-600 transition-all duration-300 text-lg"
                onclick="openMainTab(event, 'main-doc-3')">
                연도별 현황
            </button>
        </nav>

        <!-- ==========================================
             MAIN DOCUMENT 1 : 2026 특화형 모집공고
        =========================================== -->
        <div id="main-doc-1" class="main-tab-content active">

            <!-- Doc 1 Hero Section -->
            <div class="gradient-blue text-white p-8 md:p-12 text-center relative">
                <p class="md:absolute md:top-6 md:right-8 text-sm text-blue-200 font-semibold mb-4 md:mb-0">공고일:
                    2026.04.03</p>
                <h2 class="text-2xl md:text-4xl font-bold mt-2 mb-4 leading-tight">2026년 상반기 특화형 임대주택 모집 공고</h2>
                <div
                    class="inline-block px-4 py-1 rounded-full bg-white/20 text-white font-semibold text-sm mb-6 backdrop-blur-sm">
                    민간제안형
                </div>
                <div class="flex flex-wrap justify-center gap-4 md:gap-8 text-sm md:text-base">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl px-6 py-3 border border-white/20">
                        <span class="text-blue-100 block text-xs uppercase tracking-wider mb-1">공모규모</span>
                        <strong class="text-white text-lg font-bold">1,000호 내외</strong>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl px-6 py-3 border border-white/20">
                        <span class="text-blue-100 block text-xs uppercase tracking-wider mb-1">접수마감</span>
                        <strong class="text-white text-lg font-bold">26.5.11(월) 17시</strong>
                    </div>
                </div>
            </div>

            <!-- Doc 1 Sub Tabs (Level 2) -->
            <div
                class="glass-header sticky top-0 z-20 border-b border-slate-200 flex overflow-x-auto no-scrollbar shadow-sm">
                <button
                    class="doc1-tab-link active flex-1 min-w-[120px] text-center py-4 px-4 font-bold text-blue-600 border-b-2 border-blue-600 transition-all"
                    onclick="openSubTab(event, 'doc1-sub1', 'doc1-sub-content', 'doc1-tab-link', 'blue')">
                    1. 공모개요
                </button>
                <button
                    class="doc1-tab-link flex-1 min-w-[120px] text-center py-4 px-4 font-semibold text-slate-500 border-b-2 border-transparent hover:text-blue-600 transition-all"
                    onclick="openSubTab(event, 'doc1-sub2', 'doc1-sub-content', 'doc1-tab-link', 'blue')">
                    2. 신청자격
                </button>
                <button
                    class="doc1-tab-link flex-1 min-w-[120px] text-center py-4 px-4 font-semibold text-slate-500 border-b-2 border-transparent hover:text-blue-600 transition-all"
                    onclick="openSubTab(event, 'doc1-sub3', 'doc1-sub-content', 'doc1-tab-link', 'blue')">
                    3. 주요 계획기준
                </button>
                <button
                    class="doc1-tab-link flex-1 min-w-[120px] text-center py-4 px-4 font-semibold text-slate-500 border-b-2 border-transparent hover:text-blue-600 transition-all"
                    onclick="openSubTab(event, 'doc1-sub4', 'doc1-sub-content', 'doc1-tab-link', 'blue')">
                    4. 신청방법 등
                </button>
            </div>

            <!-- Doc 1 Content Area -->
            <div class="p-6 md:p-12 lg:p-16 min-h-[600px] leading-relaxed">

                <!-- 1. 공모개요 -->
                <div id="doc1-sub1" class="sub-tab-content doc1-sub-content active">
                    <div class="flex items-center mb-8">
                        <div class="w-2 h-8 bg-blue-600 rounded-full mr-4"></div>
                        <h3 class="text-2xl md:text-3xl font-extrabold text-slate-900">공모개요</h3>
                    </div>

                    <div class="grid grid-cols-1 gap-6 mb-12">
                        <div
                            class="group bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:border-blue-200 hover:bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-start">
                                <span
                                    class="bg-blue-600 text-white w-8 h-8 rounded-lg flex items-center justify-center font-bold mr-4 flex-shrink-0 shadow-lg shadow-blue-200">1</span>
                                <div>
                                    <h4 class="text-lg font-bold text-slate-900 mb-2">사업구조</h4>
                                    <p class="text-slate-600 text-lg">민간사업자가 자유롭게 테마를 제안하여 기획·설계·시공하여 공사가 매입 후 민간사업자가
                                        테마에 따른 입주자 선발 및 차별화된 주거 서비스 제공 등 임대 운영관리까지 수행</p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="group bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:border-blue-200 hover:bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-start">
                                <span
                                    class="bg-blue-600 text-white w-8 h-8 rounded-lg flex items-center justify-center font-bold mr-4 flex-shrink-0 shadow-lg shadow-blue-200">2</span>
                                <div>
                                    <h4 class="text-lg font-bold text-slate-900 mb-2">운영테마</h4>
                                    <p class="text-slate-600 text-lg">돌봄·육아·교육, 일자리·창업지원, 귀농·귀촌, 장애인·자립지원 등 사업시행자가 자유롭게
                                        제안</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div
                                class="group bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:border-blue-200 hover:bg-white hover:shadow-xl transition-all duration-300">
                                <h4 class="text-lg font-bold text-slate-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-10V4m0 10V4m-4 4h4m-4 4h4m1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                    공모규모
                                </h4>
                                <p class="text-xl font-extrabold text-blue-700 mb-2">1,000호 내외</p>
                                <p
                                    class="text-sm text-red-500 font-medium bg-red-50 p-3 rounded-lg border border-red-100 italic">
                                    ※ 수요부족, 건축계획 등이 부적합할 경우 선정하지 아니할 수 있음</p>
                            </div>
                            <div
                                class="group bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:border-blue-200 hover:bg-white hover:shadow-xl transition-all duration-300">
                                <h4 class="text-lg font-bold text-slate-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    공모지역
                                </h4>
                                <p class="text-xl font-extrabold text-slate-900 mb-2">인구 8만 이상의 전국 대상</p>
                                <p class="text-sm text-slate-500">- 단, 8만 미만 소도시·농산어촌은 읍/면/행정복지센터 소재지 한정</p>
                            </div>
                        </div>
                    </div>

                    <h4 class="font-bold text-xl mb-6 flex items-center">
                        <span class="w-1.5 h-6 bg-slate-400 rounded-full mr-3"></span>
                        공모절차
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 relative">
                        <!-- Step 1 -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all text-center relative z-10 flex flex-col items-center">
                            <span class="text-blue-600 font-black text-2xl mb-2">01</span>
                            <div class="text-slate-900 font-bold mb-2 leading-tight">사업 공모<br><span
                                    class="text-xs text-slate-400 font-normal">(본사)</span></div>
                            <div class="text-blue-600 font-semibold text-sm bg-blue-50 px-3 py-1 rounded-full">
                                ‘26.4.3(금)</div>
                        </div>
                        <!-- Step 2 -->
                        <div
                            class="bg-blue-600 p-6 rounded-2xl shadow-xl shadow-blue-100 text-center relative z-10 flex flex-col items-center transform scale-105">
                            <span class="text-blue-200 font-black text-2xl mb-2">02</span>
                            <div class="text-white font-bold mb-2 leading-tight">사업 신청접수<br><span
                                    class="text-xs text-blue-300 font-normal">(본사)</span></div>
                            <div class="text-white font-black text-sm bg-white/20 px-3 py-1 rounded-full break-all">4.27
                                ~ 5.11</div>
                        </div>
                        <!-- Step 3 -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all text-center relative z-10 flex flex-col items-center">
                            <span class="text-blue-600 font-black text-2xl mb-2">03</span>
                            <div class="text-slate-900 font-bold mb-2 leading-tight">서류심사<br><span
                                    class="text-xs text-slate-400 font-normal">(결과통보)</span></div>
                            <div class="text-slate-500 font-semibold text-sm">‘26.6.17(수)</div>
                        </div>
                        <!-- Step 4 -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all text-center relative z-10 flex flex-col items-center">
                            <span class="text-blue-600 font-black text-2xl mb-2">04</span>
                            <div class="text-slate-900 font-bold mb-2 leading-tight">본심사<br><span
                                    class="text-xs text-slate-400 font-normal">(본사/지역본부)</span></div>
                            <div class="text-slate-500 font-semibold text-sm">‘26.7.16(목)</div>
                        </div>
                        <!-- Step 5 -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all text-center relative z-10 flex flex-col items-center">
                            <span class="text-blue-600 font-black text-2xl mb-2">05</span>
                            <div class="text-slate-900 font-bold mb-2 leading-tight">도면협의 / 감정<br><span
                                    class="text-xs text-slate-400 font-normal">(지역본부)</span></div>
                            <div class="text-slate-500 font-semibold text-sm">‘26.8~9월</div>
                        </div>
                        <!-- Step 6 -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all text-center relative z-10 flex flex-col items-center">
                            <span class="text-emerald-600 font-black text-2xl mb-2">06</span>
                            <div class="text-slate-900 font-bold mb-2 leading-tight">매입약정체결<br><span
                                    class="text-xs text-slate-400 font-normal">(지역본부)</span></div>
                            <div class="text-emerald-600 font-black text-sm bg-emerald-50 px-3 py-1 rounded-full">
                                ‘26.10월말</div>
                        </div>
                    </div>
                </div>

                <!-- 2. 신청자격 -->
                <div id="doc1-sub2" class="sub-tab-content doc1-sub-content">
                    <div class="flex items-center mb-8">
                        <div class="w-2 h-8 bg-blue-600 rounded-full mr-4"></div>
                        <h3 class="text-2xl md:text-3xl font-extrabold text-slate-900">신청자격</h3>
                    </div>
                    <div class="bg-amber-50 border border-amber-100 p-6 md:p-8 rounded-3xl mb-10 flex items-start">
                        <div class="bg-amber-400 text-white p-2 rounded-xl mr-5 shadow-lg shadow-amber-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-slate-800 text-lg font-medium leading-relaxed">
                                사업 시행자는 본 사업을 시행하고자 하는 자로 <span
                                    class="bg-white px-2 py-0.5 rounded-md font-bold text-blue-700 underline underline-offset-4 decoration-2">공고일(2026.4.3.)
                                    기준</span> 아래 자격을 충족하여야 함
                            </p>
                            <p class="mt-4 text-slate-600 text-sm flex items-center">
                                <span class="inline-block w-1.5 h-1.5 bg-amber-400 rounded-full mr-2"></span>
                                법인 정관에 주택 임대운영 목적 명시 또는 관련 실적 보유자 必
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">01</span>
                            <p class="text-slate-700 font-semibold">비영리법인</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">02</span>
                            <p class="text-slate-700 font-semibold">공익법인</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">03</span>
                            <p class="text-slate-700 font-semibold">협동조합 (사회적 포함)</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">04</span>
                            <p class="text-slate-700 font-semibold">사회적기업</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">05</span>
                            <p class="text-slate-700 font-semibold">대학교 및 전문학교</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">06</span>
                            <p class="text-slate-700 font-semibold">주택임대관리업자</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">07</span>
                            <p class="text-slate-700 font-semibold">중소기업</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">08</span>
                            <p class="text-slate-700 font-semibold">소비자생활협동조합</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">09</span>
                            <p class="text-slate-700 font-semibold">도시재생 예비사회적기업</p>
                        </div>
                        <div
                            class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center hover:bg-blue-50 hover:border-blue-100 transition-colors cursor-default group">
                            <span class="text-blue-500 font-bold mr-3 opacity-40 group-hover:opacity-100">10</span>
                            <p class="text-slate-700 font-semibold">공공기관</p>
                        </div>
                    </div>

                    <div class="bg-slate-900 text-white p-8 rounded-3xl relative overflow-hidden shadow-2xl">
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-500/20 rounded-full blur-3xl"></div>
                        <h4 class="text-xl font-bold mb-4 flex items-center">
                            <svg class="w-6 h-6 text-blue-400 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            컨소시엄 구성 안내
                        </h4>
                        <p class="text-slate-300 mb-6 leading-relaxed">컨소시엄 구성 시 <strong class="text-white">주택 운영관리 주체가
                                대표법인</strong>이어야 하며, <strong class="text-white">토지소유자를 반드시 포함</strong>하여야 합니다.</p>
                        <ul class="space-y-3 text-sm text-slate-400">
                            <li class="flex items-start"><span class="text-blue-400 mr-2">•</span> 업무/책임분담이 명기된 구성 및 선임서
                                제출 必</li>
                            <li class="flex items-start"><span class="text-blue-400 mr-2">•</span> LH는 대표법인과의 통지/협의로 전체
                                통지 갈음</li>
                        </ul>
                    </div>
                </div>

                <!-- 3. 주요 계획기준 -->
                <div id="doc1-sub3" class="sub-tab-content doc1-sub-content">
                    <div class="flex items-center mb-10">
                        <div class="w-2 h-8 bg-blue-600 rounded-full mr-4"></div>
                        <h3 class="text-2xl md:text-3xl font-extrabold text-slate-900">주요 계획기준</h3>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                        <div class="space-y-8">
                            <section class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
                                <h4 class="text-xl font-bold text-blue-700 mb-6 flex items-center">
                                    <span
                                        class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3 text-sm">A</span>
                                    공모 규모 기준
                                </h4>
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center p-4 bg-slate-50 rounded-xl">
                                        <span class="font-bold text-slate-700">광역시</span>
                                        <span class="text-blue-600 font-bold">100호 미만</span>
                                    </div>
                                    <div class="flex justify-between items-center p-4 bg-slate-50 rounded-xl">
                                        <span class="font-bold text-slate-700">비수도권</span>
                                        <span class="text-blue-600 font-bold">50호 미만</span>
                                    </div>
                                    <div class="flex justify-between items-center p-4 bg-slate-50 rounded-xl">
                                        <span class="font-bold text-slate-700">소도시/농어촌</span>
                                        <span class="text-blue-600 font-bold">10호~30호</span>
                                    </div>
                                    <div
                                        class="p-4 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-600 font-medium">
                                        ※ 수도권 150호 초과 시 '자기관리형 주택임대관리업' 자격 필수
                                    </div>
                                </div>
                            </section>

                            <section class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
                                <h4 class="text-xl font-bold text-blue-700 mb-4 flex items-center">
                                    <span
                                        class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3 text-sm">B</span>
                                    커뮤니티 시설
                                </h4>
                                <ul class="space-y-4 text-slate-600">
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-3 mt-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>세대당 <strong class="text-slate-900 underline">1.5㎡ 이상</strong> 의무 설치</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-3 mt-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>소도시/농어촌은 <strong class="text-slate-900">2.5㎡ 이상</strong></span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-3 mt-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>지상 1층 설치 원칙 (협의 가능)</span>
                                    </li>
                                </ul>
                            </section>
                        </div>

                        <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                            <h4 class="text-xl font-bold text-blue-700 mb-6 flex items-center">
                                <span
                                    class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3 text-sm">C</span>
                                주거전용면적 기준
                            </h4>
                            <div class="overflow-x-auto no-scrollbar">
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr
                                            class="text-slate-400 text-left border-b border-slate-100 uppercase tracking-wider">
                                            <th class="py-4 font-bold">유형</th>
                                            <th class="py-4 font-bold">전용면적</th>
                                            <th class="py-4 font-bold">방 개수</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <tr>
                                            <td class="py-5 font-bold text-slate-900">청년(기숙사)</td>
                                            <td class="py-5 text-blue-600 font-semibold">19~60㎡</td>
                                            <td class="py-5 text-slate-600">1개+</td>
                                        </tr>
                                        <tr>
                                            <td class="py-5 font-bold text-slate-900">다자녀</td>
                                            <td class="py-5 text-blue-600 font-semibold">46㎡ 이상</td>
                                            <td class="py-5 text-slate-600">2개+</td>
                                        </tr>
                                        <tr>
                                            <td class="py-5 font-bold text-slate-900">신혼/신생아</td>
                                            <td class="py-5 text-blue-600 font-semibold">36㎡ 이상</td>
                                            <td class="py-5 text-slate-600">2개+</td>
                                        </tr>
                                        <tr>
                                            <td class="py-5 font-bold text-slate-900">고령자</td>
                                            <td class="py-5 text-blue-600 font-semibold">29㎡ 이상</td>
                                            <td class="py-5 text-slate-600">1개+</td>
                                        </tr>
                                        <tr>
                                            <td class="py-5 font-bold text-slate-900">일반</td>
                                            <td class="py-5 text-blue-600 font-semibold">20㎡ 이상</td>
                                            <td class="py-5 text-slate-600">1개+</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. 신청방법 등 -->
                <div id="doc1-sub4" class="sub-tab-content doc1-sub-content">
                    <div class="flex items-center mb-10">
                        <div class="w-2 h-8 bg-blue-600 rounded-full mr-4"></div>
                        <h3 class="text-2xl md:text-3xl font-extrabold text-slate-900">신청방법 및 문의처</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                        <div
                            class="bg-blue-600 text-white p-10 rounded-[2.5rem] shadow-2xl relative overflow-hidden group">
                            <div
                                class="absolute top-0 right-0 p-8 opacity-10 transform group-hover:scale-110 transition-transform">
                                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z">
                                    </path>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                </svg>
                            </div>
                            <div class="relative z-10">
                                <h4 class="text-2xl font-bold mb-8">온라인 접수 안내</h4>
                                <div class="space-y-6">
                                    <div>
                                        <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1">접수기간
                                        </p>
                                        <p class="text-xl font-black">‘26.4.27(월) ~ 5.11(월) 17:00</p>
                                    </div>
                                    <div>
                                        <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1">접수방법
                                        </p>
                                        <p class="text-lg font-bold">원본 PDF 변환 후 이메일 제출</p>
                                        <a href="mailto:lh12345@lh.or.kr"
                                            class="inline-block mt-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl font-mono text-sm transition-colors border border-white/20">lh12345@lh.or.kr</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-white p-10 rounded-[2.5rem] border border-slate-100 shadow-xl flex flex-col justify-center">
                            <h4 class="text-2xl font-bold text-slate-900 mb-6 flex items-center">
                                <span class="bg-slate-100 p-3 rounded-2xl mr-4">
                                    <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                        </path>
                                    </svg>
                                </span>
                                문의처 안내
                            </h4>
                            <p class="text-slate-600 text-lg mb-6 leading-relaxed font-medium">
                                경남 진주시 충의로 19<br>
                                <span class="text-slate-900 font-bold">LH 매입임대사업처 매입임대공급운영팀</span>
                            </p>
                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                <p class="text-sm text-slate-500 font-bold mb-1">상담 전화</p>
                                <p class="text-3xl font-black text-indigo-600 tracking-tighter italic">055-922-3464,
                                    3465</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             MAIN DOCUMENT 2 : 공공주체별 공모사업 안내
        =========================================== -->
        <div id="main-doc-2" class="main-tab-content">
            <div class="gradient-cyan text-white p-12 text-center">
                <h2 class="text-3xl md:text-5xl font-black mb-4 tracking-tight">공공주체별 공모사업 안내</h2>
                <p class="text-cyan-100 text-xl opacity-90">민간참여형(민관협력형) 공공임대주택</p>
            </div>

            <div class="bg-slate-50 p-8 border-b border-slate-200">
                <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center p-4 bg-white rounded-xl shadow-sm border border-slate-100">
                        <div class="w-2 h-2 bg-cyan-500 rounded-full mr-3"></div>
                        <span class="text-sm font-semibold text-slate-700">공공주체별 최신 공고 통합 공유</span>
                    </div>
                    <div class="flex items-center p-4 bg-white rounded-xl shadow-sm border border-slate-100">
                        <div class="w-2 h-2 bg-cyan-500 rounded-full mr-3"></div>
                        <span class="text-sm font-semibold text-slate-700">연초 세부추진계획 연동 안내</span>
                    </div>
                    <div class="flex items-center p-4 bg-white rounded-xl shadow-sm border border-slate-100">
                        <div class="w-2 h-2 bg-cyan-500 rounded-full mr-3"></div>
                        <span class="text-sm font-semibold text-slate-700">특별법 기반 다양한 공고 공유</span>
                    </div>
                    <div class="flex items-center p-4 bg-white rounded-xl shadow-sm border border-slate-100">
                        <div class="w-2 h-2 bg-cyan-500 rounded-full mr-3"></div>
                        <span class="text-sm font-semibold text-slate-700">LH 점유율 약 70% 비중</span>
                    </div>
                </div>
            </div>

            <!-- Doc 2 Sub Tabs -->
            <div
                class="glass-header sticky top-0 z-20 border-b border-slate-200 flex overflow-x-auto no-scrollbar shadow-sm">
                <button
                    class="doc2-tab-link active flex-1 min-w-[150px] text-center py-5 font-bold text-cyan-700 border-b-4 border-cyan-600 bg-cyan-50/50 transition-all"
                    onclick="openSubTab(event, 'doc2-sub1', 'doc2-sub-content', 'doc2-tab-link', 'cyan')">LH
                    토지주택공사</button>
                <button
                    class="doc2-tab-link flex-1 min-w-[150px] text-center py-5 font-bold text-slate-500 border-b-4 border-transparent hover:text-cyan-700 transition-all"
                    onclick="openSubTab(event, 'doc2-sub2', 'doc2-sub-content', 'doc2-tab-link', 'cyan')">SH
                    서울주택공사</button>
                <button
                    class="doc2-tab-link flex-1 min-w-[150px] text-center py-5 font-bold text-slate-500 border-b-4 border-transparent hover:text-cyan-700 transition-all"
                    onclick="openSubTab(event, 'doc2-sub3', 'doc2-sub-content', 'doc2-tab-link', 'cyan')">GH
                    경기주택공사</button>
                <button
                    class="doc2-tab-link flex-1 min-w-[150px] text-center py-5 font-bold text-slate-500 border-b-4 border-transparent hover:text-cyan-700 transition-all"
                    onclick="openSubTab(event, 'doc2-sub4', 'doc2-sub-content', 'doc2-tab-link', 'cyan')">전북
                    전주시</button>
            </div>

            <div class="p-8 md:p-16 min-h-[500px]">
                <div id="doc2-sub1" class="sub-tab-content doc2-sub-content active">
                    <div class="flex items-center mb-8">
                        <div class="w-2 h-8 bg-cyan-600 rounded-full mr-4"></div>
                        <h3 class="text-3xl font-black text-slate-900">LH 한국토지주택공사</h3>
                    </div>
                    <div class="bg-cyan-900 text-white p-10 rounded-[3rem] shadow-2xl relative overflow-hidden mb-12">
                        <div class="absolute -right-20 -top-20 w-80 h-80 bg-cyan-500/20 rounded-full blur-3xl"></div>
                        <p class="text-xl font-bold mb-8 leading-relaxed">LH는 기존주택매입, 일반매입약정, 특화형 매입약정 등으로 공고를 진행하며 <a
                                href="https://apply.lh.or.kr/lhapply/apply/pch/list.do?mi=1076" target="_blank"
                                class="text-cyan-400 underline underline-offset-8 decoration-2 hover:text-white transition-colors">청약플러스</a>를
                            통해 실시간 확인 가능합니다.</p>
                        <ul class="space-y-4 text-cyan-100/70">
                            <li class="flex items-start"><span class="text-cyan-400 font-bold mr-3">CHECK</span> 본사 사전공고
                                후 지역본부별 개별 공고 진행</li>
                            <li class="flex items-start"><span class="text-cyan-400 font-bold mr-3">CHECK</span> 특화형은 본사
                                직접 공고 및 선정 원칙</li>
                            <li class="flex items-start"><span class="text-cyan-400 font-bold mr-3">CHECK</span> 2024
                                하반기 공모 접수는 25년 2월 예정</li>
                        </ul>
                    </div>
                </div>

                <div id="doc2-sub2" class="sub-tab-content doc2-sub-content">
                    <div class="flex items-center mb-8">
                        <div class="w-2 h-8 bg-cyan-600 rounded-full mr-4"></div>
                        <h3 class="text-3xl font-black text-slate-900">SH 서울주택도시공사</h3>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 p-10 rounded-[3rem]">
                        <p class="text-xl font-bold text-slate-800 mb-8 leading-relaxed">SH는 주로 매입약정방식의 일반 매입임대공고가 진행되고
                            있습니다.</p>
                        <div class="space-y-4 text-slate-500 font-medium">
                            <p class="flex items-center"><span class="w-2 h-2 bg-slate-300 rounded-full mr-3"></span>
                                자치구 수요자맞춤형 공모 급감 추세</p>
                            <p class="flex items-center"><span class="w-2 h-2 bg-slate-300 rounded-full mr-3"></span>
                                서울형 사회주택 신규 공모 일시 중단 상태</p>
                        </div>
                    </div>
                </div>

                <div id="doc2-sub3" class="sub-tab-content doc2-sub-content">
                    <div class="flex items-center mb-8">
                        <div class="w-2 h-8 bg-cyan-600 rounded-full mr-4"></div>
                        <h3 class="text-3xl font-black text-slate-900">GH 경기주택도시공사</h3>
                    </div>
                    <div class="bg-white border border-slate-100 shadow-xl p-10 rounded-[3rem]">
                        <p class="text-xl font-bold text-slate-800 mb-8 leading-relaxed">GH는 매입약정형 사업이 급격히 확대되고 있는
                            추세입니다.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                class="p-4 bg-cyan-50 rounded-2xl border border-cyan-100 font-bold text-cyan-800 text-sm">
                                2024.05 특화형 & 리모델링 동시 발표</div>
                            <div
                                class="p-4 bg-slate-50 rounded-2xl border border-slate-100 font-bold text-slate-700 text-sm">
                                2025.06 청년창업지원 특화형 발표</div>
                            <div
                                class="p-4 bg-slate-50 rounded-2xl border border-slate-100 font-bold text-slate-700 text-sm">
                                2025.11 민간제안형 특화형 발표</div>
                            <div
                                class="p-4 bg-cyan-600 rounded-2xl font-bold text-white text-sm shadow-lg shadow-cyan-100">
                                2025.12 GH Care Hub 위탁운영형 발표</div>
                        </div>
                    </div>
                </div>

                <div id="doc2-sub4" class="sub-tab-content doc2-sub-content">
                    <div class="flex items-center mb-8">
                        <div class="w-2 h-8 bg-cyan-600 rounded-full mr-4"></div>
                        <h3 class="text-3xl font-black text-slate-900">전북 전주시</h3>
                    </div>
                    <div class="bg-slate-50 p-10 rounded-[3rem] border border-slate-200">
                        <p class="text-xl font-bold text-slate-800 mb-4 leading-relaxed">전주시는 전주형 사회주택 공급을 지속적으로 추진
                            중입니다.</p>
                        <p class="text-slate-500 italic">기존 사회주택 사업지를 전주시 특화형 사업으로 전환하여 활발히 시공 중</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             MAIN DOCUMENT 3 : 연도별 현황
        =========================================== -->
        <div id="main-doc-3" class="main-tab-content">
            <div class="gradient-emerald text-white p-12 text-center">
                <h2 class="text-3xl md:text-5xl font-black mb-4 tracking-tight">연도별 공모 현황 및 결과</h2>
                <p class="text-emerald-100 text-xl opacity-90">최근 공모사업 진행 상황부터 과거 공모 결과까지 안내</p>
            </div>

            <div class="p-8 md:p-16 space-y-16">
                <section>
                    <div class="flex items-center justify-between mb-10">
                        <h3 class="text-3xl font-black text-slate-900 border-l-8 border-emerald-500 pl-6">2025년 주요 현황
                        </h3>
                        <span
                            class="bg-emerald-100 text-emerald-700 font-bold px-4 py-1 rounded-full text-sm">CURRENT</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div
                            class="bg-white border border-slate-100 rounded-[2rem] shadow-xl hover:shadow-2xl transition-all overflow-hidden flex flex-col h-full">
                            <div class="bg-emerald-500 p-6 text-white flex justify-between items-center">
                                <h4 class="font-black text-lg">GH 특화형 (11월)</h4>
                                <span
                                    class="bg-white/20 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-tighter">Active</span>
                            </div>
                            <div class="p-8 flex-1 flex flex-col justify-between">
                                <p class="text-slate-600 mb-6 font-medium">민간제안형 - 경기도 주민 주거안정 공급</p>
                                <div class="space-y-3 mb-8">
                                    <div class="flex items-center text-sm font-bold text-slate-800"><span
                                            class="w-1.5 h-1.5 bg-emerald-400 rounded-full mr-2"></span>접수: '26.1.5 ~
                                        1.30</div>
                                    <div class="flex items-center text-sm text-slate-500"><span
                                            class="w-1.5 h-1.5 bg-slate-200 rounded-full mr-2"></span>대상: 경기도 전역 건축예정지
                                    </div>
                                </div>
                                <a href="http://www.socialhousing.kr/notice2/427862" target="_blank"
                                    class="block text-center py-4 bg-emerald-50 text-emerald-700 font-bold rounded-2xl hover:bg-emerald-100 transition-colors">공고문
                                    확인</a>
                            </div>
                        </div>

                        <div
                            class="bg-white border border-slate-100 rounded-[2rem] shadow-xl hover:shadow-2xl transition-all overflow-hidden flex flex-col h-full">
                            <div class="bg-emerald-500 p-6 text-white flex justify-between items-center">
                                <h4 class="font-black text-lg">GH Care Hub (12월)</h4>
                                <span
                                    class="bg-white/20 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-tighter">Active</span>
                            </div>
                            <div class="p-8 flex-1 flex flex-col justify-between">
                                <p class="text-slate-600 mb-6 font-medium">위탁운영형 - 지역문제해결형 케어허브</p>
                                <div class="space-y-3 mb-8">
                                    <div class="flex items-center text-sm font-bold text-slate-800"><span
                                            class="w-1.5 h-1.5 bg-emerald-400 rounded-full mr-2"></span>접수: '26.2.9 ~
                                        2.12</div>
                                    <div class="flex items-center text-sm text-slate-500"><span
                                            class="w-1.5 h-1.5 bg-slate-200 rounded-full mr-2"></span>조건: 운영자격 보유기업 우대
                                    </div>
                                </div>
                                <a href="http://www.socialhousing.kr/notice2/427862" target="_blank"
                                    class="block text-center py-4 bg-emerald-50 text-emerald-700 font-bold rounded-2xl hover:bg-emerald-100 transition-colors">공고문
                                    확인</a>
                            </div>
                        </div>

                        <div
                            class="bg-slate-50 border border-slate-200 rounded-[2rem] p-8 flex flex-col justify-center text-center italic text-slate-400 font-medium">
                            More updates coming soon...
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <div class="bg-white p-10 rounded-[3rem] border border-slate-100 shadow-xl">
                        <h3 class="text-2xl font-black text-slate-900 mb-8 flex items-center">
                            <svg class="w-8 h-8 text-emerald-500 mr-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                                </path>
                            </svg>
                            공고 바로가기
                        </h3>
                        <div class="space-y-4">
                            <a href="https://apply.lh.or.kr" target="_blank"
                                class="flex items-center justify-between p-5 bg-slate-50 rounded-2xl hover:bg-emerald-50 hover:border-emerald-200 border border-transparent transition-all group">
                                <span class="font-bold text-slate-700 group-hover:text-emerald-700">LH 청약플러스
                                    (매도신청)</span>
                                <svg class="w-5 h-5 text-slate-300 group-hover:text-emerald-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7-7 7"></path>
                                </svg>
                            </a>
                            <a href="https://www.i-sh.co.kr" target="_blank"
                                class="flex items-center justify-between p-5 bg-slate-50 rounded-2xl hover:bg-emerald-50 hover:border-emerald-200 border border-transparent transition-all group">
                                <span class="font-bold text-slate-700 group-hover:text-emerald-700">SH 서울주택도시공사
                                    공고</span>
                                <svg class="w-5 h-5 text-slate-300 group-hover:text-emerald-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7-7 7"></path>
                                </svg>
                            </a>
                            <a href="https://gh.or.kr" target="_blank"
                                class="flex items-center justify-between p-5 bg-slate-50 rounded-2xl hover:bg-emerald-50 hover:border-emerald-200 border border-transparent transition-all group">
                                <span class="font-bold text-slate-700 group-hover:text-emerald-700">GH 경기주택도시공사
                                    공고</span>
                                <svg class="w-5 h-5 text-slate-300 group-hover:text-emerald-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="bg-slate-900 text-white p-10 rounded-[3rem] shadow-2xl flex flex-col justify-center">
                        <h3 class="text-2xl font-black mb-8 flex items-center">
                            <svg class="w-8 h-8 text-emerald-400 mr-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                                </path>
                            </svg>
                            과거 공모 아카이브
                        </h3>
                        <div class="space-y-4">
                            <details class="group bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
                                <summary
                                    class="p-5 cursor-pointer font-bold flex justify-between items-center hover:bg-white/10 transition-colors">
                                    2024년 공모 결과
                                    <svg class="w-5 h-5 transform group-open:rotate-180 transition-transform"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </summary>
                                <div class="px-8 pb-5 text-sm text-slate-400 border-t border-white/5 pt-4">
                                    LH특화형 임대주택 공모사업 마감 및 선정 완료
                                </div>
                            </details>
                            <details class="group bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
                                <summary
                                    class="p-5 cursor-pointer font-bold flex justify-between items-center hover:bg-white/10 transition-colors">
                                    2023년 공모 결과
                                    <svg class="w-5 h-5 transform group-open:rotate-180 transition-transform"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </summary>
                                <div class="px-8 pb-5 text-sm text-slate-400 border-t border-white/5 pt-4">
                                    LH특화형 226세대 선정, GH 리모델링 1건 약정 추진 중
                                </div>
                            </details>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="max-w-7xl mx-auto py-10 px-6 text-center text-slate-400 text-sm font-medium">
        &copy; 2026 공공임대주택 통합 안내 포털. All rights reserved.
    </footer>

    <script>
        function openMainTab(evt, tabId) {
            const mainContents = document.getElementsByClassName("main-tab-content");
            for (let i = 0; i < mainContents.length; i++) {
                mainContents[i].classList.remove("active");
            }

            const mainLinks = document.getElementsByClassName("main-tab-link");
            for (let i = 0; i < mainLinks.length; i++) {
                mainLinks[i].classList.remove("active", "text-indigo-700", "border-indigo-600", "bg-white");
                mainLinks[i].classList.add("text-slate-500", "border-transparent");
            }

            document.getElementById(tabId).classList.add("active");
            evt.currentTarget.classList.add("active", "text-indigo-700", "border-indigo-600", "bg-white");
            evt.currentTarget.classList.remove("text-slate-500", "border-transparent");

            // Scroll to top of the content
            const portal = document.getElementById('gongmo-portal');
            if (portal) {
                const top = portal.getBoundingClientRect().top + window.pageYOffset - 20;
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function openSubTab(evt, tabId, groupClass, linkClass, theme = 'blue') {
            const subContents = document.getElementsByClassName(groupClass);
            for (let i = 0; i < subContents.length; i++) {
                subContents[i].classList.remove("active");
            }

            const subLinks = document.getElementsByClassName(linkClass);
            const colorMap = {
                blue: 'blue-600',
                cyan: 'cyan-700',
                emerald: 'emerald-600'
            };
            const activeColor = colorMap[theme] || 'indigo-600';

            for (let i = 0; i < subLinks.length; i++) {
                subLinks[i].classList.remove("active", "text-blue-600", "text-cyan-700", "text-emerald-600", "border-blue-600", "border-cyan-700", "border-emerald-600");
                subLinks[i].classList.add("text-slate-500", "border-transparent");
            }

            document.getElementById(tabId).classList.add("active");
            evt.currentTarget.classList.add("active", "text-" + activeColor, "border-" + activeColor);
            evt.currentTarget.classList.remove("text-slate-500", "border-transparent");
        }
    </script>
</div>

<?php if (!$isRhymix): ?>
</body>

</html>
<?php endif; ?>