import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { ArrowRight, Home, Users, Leaf, Heart, TrendingUp, FileText, Megaphone, Calendar } from "lucide-react";
import { useState, useEffect } from "react";

/**
 * Warm Humanism Design System - Social Housing Homepage
 * Colors: Terracotta (#c85a2f), Mint (#6ee7b7), Cream (#fffbf0), Warm Brown (#5a4a42)
 * Typography: Noto Sans KR (body) + Noto Serif KR (accents)
 * Aesthetic: Curved elements, soft shadows, warm tones, community-focused
 */

// SVG Divider Component - Curved wave divider
const CurvedDivider = ({ direction = "down", color = "#c85a2f" }: { direction?: "up" | "down"; color?: string }) => {
  if (direction === "up") {
    return (
      <svg className="w-full h-20 -mb-px" viewBox="0 0 1200 120" preserveAspectRatio="none" style={{ display: 'block' }}>
        <path
          d="M0,60 Q300,0 600,60 T1200,60 L1200,120 L0,120 Z"
          fill={color}
          fillOpacity="0.1"
        />
      </svg>
    );
  }
  return (
    <svg className="w-full h-20 -mb-px" viewBox="0 0 1200 120" preserveAspectRatio="none" style={{ display: 'block' }}>
      <path
        d="M0,60 Q300,120 600,60 T1200,60 L1200,0 L0,0 Z"
        fill={color}
        fillOpacity="0.1"
      />
    </svg>
  );
};

// Counter Component - Animated number counter
const Counter = ({ target, label }: { target: number; label: string }) => {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let current = 0;
    const increment = target / 30;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        setCount(target);
        clearInterval(timer);
      } else {
        setCount(Math.floor(current));
      }
    }, 50);
    return () => clearInterval(timer);
  }, [target]);

  return (
    <div className="text-center">
      <div className="text-4xl font-bold text-[#c85a2f] mb-2">
        {count.toLocaleString()}
      </div>
      <div className="text-sm text-[#5a4a42]">{label}</div>
    </div>
  );
};

export default function HomePage() {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 50);
    };
    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  return (
    <div className="min-h-screen bg-[#fffbf0]">
      {/* Header */}
      <header
        className={`fixed top-0 w-full z-50 transition-all duration-300 ${
          scrolled ? "bg-white shadow-lg" : "bg-transparent"
        }`}
      >
        <div className="container mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Home className="w-8 h-8 text-[#c85a2f]" />
            <h1 className="text-2xl font-bold text-[#c85a2f]">한국사회주택협회</h1>
          </div>
          <nav className="hidden md:flex gap-8 text-[#5a4a42]">
            <a href="#meaning" className="hover:text-[#c85a2f] transition">의미</a>
            <a href="#value" className="hover:text-[#c85a2f] transition">가치</a>
            <a href="#innovation" className="hover:text-[#c85a2f] transition">혁신</a>
            <a href="#competitions" className="hover:text-[#c85a2f] transition">공모</a>
            <a href="#recruitment" className="hover:text-[#c85a2f] transition">입주</a>
          </nav>
          <Button className="bg-[#c85a2f] hover:bg-[#a85a2f] text-white">문의하기</Button>
        </div>
      </header>

      {/* Hero Section */}
      <section className="pt-32 pb-20 relative overflow-hidden">
        <div
          className="absolute inset-0 bg-cover bg-center opacity-40"
          style={{
            backgroundImage: "url('https://d2xsxph8kpxj0f.cloudfront.net/310519663051041558/mmDmTpzKw4cFqwk3SzPWJw/hero-background-korean-MhTMRp6Rqf7EkhJ9mf8MFt.webp')",
          }}
        />
        <div className="container mx-auto px-4 relative z-10">
          <div className="max-w-2xl">
            <h2 className="text-5xl md:text-6xl font-bold text-[#c85a2f] mb-6 leading-tight">
              더 나은 주거,<br />함께하는 미래
            </h2>
            <p className="text-lg text-[#5a4a42] mb-8 leading-relaxed">
              사회주택은 공공성과 민간의 혁신이 만나는 곳입니다. 저렴하고 안정적인 주거를 통해 모든 사람이 누릴 수 있는 주택의 가치를 실현합니다.
            </p>
            <div className="flex gap-4">
              <Button className="bg-[#c85a2f] hover:bg-[#a85a2f] text-white px-8 py-6 text-lg flex items-center gap-2">
                사업공모 보기 <ArrowRight className="w-5 h-5" />
              </Button>
              <Button variant="outline" className="border-[#c85a2f] text-[#c85a2f] hover:bg-[#c85a2f] hover:text-white px-8 py-6 text-lg">
                입주자 모집 확인
              </Button>
            </div>
          </div>
        </div>
      </section>

      {/* What is Social Housing */}
      <section id="meaning" className="py-20 bg-white">
        <CurvedDivider direction="up" color="#ffffff" />
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-4 text-center">사회주택이란?</h2>
          <p className="text-center text-[#5a4a42] mb-16 max-w-2xl mx-auto">
            사회적 경제주체가 공급하고 운영하는 저렴한 임대주택으로, 저소득층의 주거 안정성을 확보하고 공동체 가치를 실현합니다.
          </p>

          <div className="grid md:grid-cols-3 gap-8">
            {[
              {
                icon: <TrendingUp className="w-12 h-12" />,
                title: "저렴한 임대료",
                description: "시장 임대료 대비 20-30% 저렴하여 저소득층의 주거비 부담을 경감합니다.",
              },
              {
                icon: <Calendar className="w-12 h-12" />,
                title: "안정적 거주",
                description: "5년 이상의 장기 임차를 보장하여 주거의 안정성을 제공합니다.",
              },
              {
                icon: <Users className="w-12 h-12" />,
                title: "공동체 중심",
                description: "입주자 간 상호 지원과 교류를 통해 따뜻한 공동체를 형성합니다.",
              },
            ].map((item, index) => (
              <Card key={index} className="p-8 bg-[#fffbf0] border-[#e5d5cc] hover:shadow-lg transition-all duration-300 transform hover:-translate-y-2">
                <div className="text-[#c85a2f] mb-4">{item.icon}</div>
                <h3 className="text-xl font-bold text-[#5a4a42] mb-3">{item.title}</h3>
                <p className="text-[#5a4a42] leading-relaxed">{item.description}</p>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Value Proposition */}
      <section id="value" className="py-20 relative">
        <div
          className="absolute inset-0 bg-cover bg-center opacity-30"
          style={{
            backgroundImage: "url('https://d2xsxph8kpxj0f.cloudfront.net/310519663051041558/mmDmTpzKw4cFqwk3SzPWJw/community-value-h3pQgz2JVoNdcCKh6w6TwM.webp')",
          }}
        />
        <div className="container mx-auto px-4 relative z-10">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-16 text-center">사회주택의 가치</h2>

          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <div className="space-y-8">
                {[
                  {
                    title: "경제적 접근성",
                    description: "저소득층의 주거 안정성 확보와 경제적 부담 경감",
                  },
                  {
                    title: "사회적 포용성",
                    description: "다양한 계층의 공존과 상호 이해 증진",
                  },
                  {
                    title: "지역 재생",
                    description: "낙후 지역의 활성화 및 지역사회 커뮤니티 형성",
                  },
                  {
                    title: "지속가능성",
                    description: "환경 친화적이고 장기적인 주거 해결책 제시",
                  },
                ].map((item, index) => (
                  <div key={index} className="bg-white rounded-2xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 className="text-xl font-bold text-[#c85a2f] mb-2">{item.title}</h3>
                    <p className="text-[#5a4a42]">{item.description}</p>
                  </div>
                ))}
              </div>
            </div>
            <div className="hidden md:block">
            <img
              src="https://d2xsxph8kpxj0f.cloudfront.net/310519663051041558/mmDmTpzKw4cFqwk3SzPWJw/community-value-korean-dpVNnbKc3ZsGw9ixUDdZdS.webp"
              alt="한국 커뮤니티 - 사회주택의 가치"
              className="rounded-2xl shadow-lg"
            />
            </div>
          </div>
        </div>
      </section>

      {/* Innovation Models */}
      <section id="innovation" className="py-20 bg-white">
        <CurvedDivider direction="up" color="#ffffff" />
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-16 text-center">혁신 모델</h2>

          <div className="grid md:grid-cols-2 gap-8 mb-12">
            {[
              {
                title: "토지임대부 사회주택",
                description: "공공 토지를 활용하여 저렴한 주택 공급",
                icon: "🏗️",
              },
              {
                title: "매입임대 사회주택",
                description: "공공매입임대주택을 사회연대경제조직 등이 위탁받아 수요맞춤 운영",
                icon: "🏠",
              },
              {
                title: "특화형 사회주택",
                description: "청년, 신혼부부, 노인 등 대상별 맞춤형",
                icon: "👨‍👩‍👧‍👦",
              },
              {
                title: "공동주택형 사회주택",
                description: "공동생활 공간을 포함한 커뮤니티형",
                icon: "🤝",
              },
            ].map((model, index) => (
              <Card
                key={index}
                className="p-8 bg-gradient-to-br from-[#fffbf0] to-[#f9f5f0] border-[#e5d5cc] hover:shadow-lg transition-all duration-300"
              >
                <div className="text-4xl mb-4">{model.icon}</div>
                <h3 className="text-xl font-bold text-[#5a4a42] mb-3">{model.title}</h3>
                <p className="text-[#5a4a42]">{model.description}</p>
              </Card>
            ))}
          </div>

          <div className="bg-gradient-to-r from-[#c85a2f] to-[#a85a2f] rounded-2xl p-12 text-white text-center">
            <img
              src="https://d2xsxph8kpxj0f.cloudfront.net/310519663051041558/mmDmTpzKw4cFqwk3SzPWJw/innovation-section-korean-UoTf4JTDMdmKR6GTGteGwH.webp"
              alt="한국 사회주택 혁신 모델"
              className="rounded-xl mb-8 max-h-64 mx-auto"
            />
            <h3 className="text-2xl font-bold mb-4">다양한 혁신 모델로 주거 문제를 해결합니다</h3>
            <p className="text-lg opacity-90">
              사회주택은 지역의 특성과 주민의 필요에 맞춘 맞춤형 해결책을 제공합니다.
            </p>
          </div>
        </div>
      </section>

      {/* Current Competitions */}
      <section id="competitions" className="py-20">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-4 text-center">현재 진행 중인 사업공모</h2>
          <p className="text-center text-[#5a4a42] mb-12">
            새로운 사회주택 프로젝트에 참여할 수 있는 기회를 확인하세요.
          </p>

          <div className="grid md:grid-cols-2 gap-8">
            {[
              {
                title: "2026년 LH 상반기 특화형 임대주택 모집 공고",
                scale: "1,000호 내외",
                deadline: "2026.05.11(월) 17시",
                status: "진행중",
              },
              {
                title: "2026년 LH 특화형 임대주택\n(특정테마형) 사업자 모집 공고",
                scale: "500호 내외",
                deadline: "공모 예상: 2026.09.30",
                status: "예정",
              },
            ].map((comp, index) => (
              <Card key={index} className="p-8 bg-white border-[#e5d5cc] hover:shadow-lg transition-all">
                <div className="flex items-start justify-between mb-4">
                  <h3 className="text-xl font-bold text-[#5a4a42] flex-1">{comp.title}</h3>
                  <span className={`px-3 py-1 rounded-full text-sm font-semibold ${
                    comp.status === "진행중" ? "bg-[#c85a2f] text-white" : "bg-[#6ee7b7] text-[#1a4d3e]"
                  }`}>
                    {comp.status}
                  </span>
                </div>
                <div className="space-y-2 mb-6">
                  <p className="text-[#5a4a42]"><span className="font-semibold">공모 규모:</span> {comp.scale}</p>
                  <p className="text-[#5a4a42]"><span className="font-semibold">신청 기한:</span> {comp.deadline}</p>
                </div>
                <Button className="w-full bg-[#c85a2f] hover:bg-[#a85a2f] text-white">상세보기</Button>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Recruitment */}
      <section id="recruitment" className="py-20 bg-white">
        <CurvedDivider direction="up" color="#ffffff" />
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-4 text-center">입주자 모집 공고</h2>
          <p className="text-center text-[#5a4a42] mb-12">
            지금 입주할 수 있는 사회주택 프로젝트를 확인하세요.
          </p>

          <div className="grid md:grid-cols-3 gap-8">
            {[
              {
                name: "아츠스테이 성산1호점",
                location: "서울시 마포구 성산",
                target: "청년 (만 19-39세)",
                period: "상시 모집",
              },
              {
                name: "캔자스대저택",
                location: "서울시 강서구",
                target: "저소득 가구",
                period: "2026.03.02 ~ 03.31",
              },
              {
                name: "사회주택 프로젝트",
                location: "서울시 도봉구",
                target: "신혼부부",
                period: "2026.04.15 ~ 05.15",
              },
            ].map((project, index) => (
              <Card key={index} className="p-6 bg-gradient-to-br from-[#fffbf0] to-[#f9f5f0] border-[#e5d5cc] hover:shadow-lg transition-all">
                <h3 className="text-lg font-bold text-[#5a4a42] mb-4">{project.name}</h3>
                <div className="space-y-3 mb-6">
                  <p className="text-sm text-[#5a4a42]"><span className="font-semibold">위치:</span> {project.location}</p>
                  <p className="text-sm text-[#5a4a42]"><span className="font-semibold">대상:</span> {project.target}</p>
                  <p className="text-sm text-[#5a4a42]"><span className="font-semibold">기간:</span> {project.period}</p>
                </div>
                <Button variant="outline" className="w-full border-[#c85a2f] text-[#c85a2f] hover:bg-[#c85a2f] hover:text-white">
                  상세보기
                </Button>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Member News */}
      <section className="py-20">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-4 text-center">회원사 소식</h2>
          <p className="text-center text-[#5a4a42] mb-12">
            협회 회원사들의 최신 소식과 프로젝트를 확인하세요.
          </p>

          <div className="grid md:grid-cols-2 gap-8">
            {[
              {
                title: "사회주택 입주자 만족도 조사 결과 발표",
                date: "2026.04.20",
                summary: "사회주택 입주자의 93%가 주거 만족도를 높게 평가했습니다.",
              },
              {
                title: "새로운 회원사 3곳 가입",
                date: "2026.04.15",
                summary: "사회주택 공급 확대를 위해 새로운 사회적 경제주체들이 협회에 가입했습니다.",
              },
            ].map((news, index) => (
              <Card key={index} className="p-8 bg-white border-[#e5d5cc] hover:shadow-lg transition-all">
                <div className="flex items-start justify-between mb-4">
                  <h3 className="text-lg font-bold text-[#5a4a42] flex-1">{news.title}</h3>
                  <Megaphone className="w-5 h-5 text-[#c85a2f] flex-shrink-0" />
                </div>
                <p className="text-sm text-[#9ca3af] mb-3">{news.date}</p>
                <p className="text-[#5a4a42] mb-4">{news.summary}</p>
                <Button variant="ghost" className="text-[#c85a2f] hover:text-[#a85a2f] p-0">
                  더 보기 →
                </Button>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Research & Policy */}
      <section className="py-20 bg-white">
        <CurvedDivider direction="up" color="#ffffff" />
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-4 text-center">연구 및 정책 동향</h2>
          <p className="text-center text-[#5a4a42] mb-12">
            사회주택 관련 최신 연구 자료와 정책 정보를 제공합니다.
          </p>

          <div className="grid md:grid-cols-2 gap-8">
            {[
              {
                title: "2026년 정부 주택공급 확대방안",
                date: "2026.04.10",
                type: "정책자료",
              },
              {
                title: "사회주택 정책의 발전 방안에 관한 연구",
                date: "2026.03.25",
                type: "연구보고서",
              },
            ].map((item, index) => (
              <Card key={index} className="p-8 bg-gradient-to-br from-[#fffbf0] to-[#f9f5f0] border-[#e5d5cc] hover:shadow-lg transition-all">
                <div className="flex items-start justify-between mb-4">
                  <h3 className="text-lg font-bold text-[#5a4a42] flex-1">{item.title}</h3>
                  <FileText className="w-5 h-5 text-[#6ee7b7] flex-shrink-0" />
                </div>
                <p className="text-sm text-[#9ca3af] mb-2">{item.date}</p>
                <span className="inline-block px-3 py-1 bg-[#6ee7b7] text-[#1a4d3e] text-xs font-semibold rounded-full mb-4">
                  {item.type}
                </span>
                <Button variant="ghost" className="text-[#c85a2f] hover:text-[#a85a2f] p-0">
                  다운로드 →
                </Button>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Statistics */}
      <section className="py-20 bg-gradient-to-r from-[#c85a2f] to-[#a85a2f] text-white">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold mb-16 text-center">사회주택 공급 현황</h2>

          <div className="grid md:grid-cols-4 gap-8">
            <Counter target={2500} label="누적 공급 주택수" />
            <Counter target={6200} label="누적 입주자수" />
            <Counter target={45} label="회원사 수" />
            <Counter target={850} label="누적 투자 규모 (억원)" />
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20">
        <div className="container mx-auto px-4 text-center">
          <h2 className="text-4xl font-bold text-[#c85a2f] mb-6">사회주택에 함께하세요</h2>
          <p className="text-lg text-[#5a4a42] mb-12 max-w-2xl mx-auto">
            사회주택은 더 나은 주거 환경을 만들기 위해 함께 노력하는 모든 사람을 환영합니다.
          </p>

          <div className="grid md:grid-cols-3 gap-8">
            {[
              { title: "사업 참여 문의", icon: "📞" },
              { title: "회원 가입", icon: "✍️" },
              { title: "뉴스레터 구독", icon: "📧" },
            ].map((action, index) => (
              <Button
                key={index}
                className="h-auto py-8 bg-[#c85a2f] hover:bg-[#a85a2f] text-white text-lg font-semibold rounded-2xl"
              >
                <span className="text-3xl mr-3">{action.icon}</span>
                {action.title}
              </Button>
            ))}
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-[#5a4a42] text-white py-12">
        <div className="container mx-auto px-4">
          <div className="grid md:grid-cols-4 gap-8 mb-8">
            <div>
              <h3 className="font-bold mb-4">한국사회주택협회</h3>
              <p className="text-sm text-gray-300">
                사회주택을 통해 더 나은 주거 환경을 만들어가고 있습니다.
              </p>
            </div>
            <div>
              <h3 className="font-bold mb-4">빠른 링크</h3>
              <ul className="text-sm text-gray-300 space-y-2">
                <li><a href="#" className="hover:text-white">사업공모</a></li>
                <li><a href="#" className="hover:text-white">입주자 모집</a></li>
                <li><a href="#" className="hover:text-white">회원사</a></li>
              </ul>
            </div>
            <div>
              <h3 className="font-bold mb-4">연락처</h3>
              <p className="text-sm text-gray-300">
                전화: 02-XXXX-XXXX<br />
                이메일: info@socialhousing.kr
              </p>
            </div>
            <div>
              <h3 className="font-bold mb-4">소셜 미디어</h3>
              <div className="flex gap-4">
                <a href="#" className="hover:text-[#6ee7b7]">Facebook</a>
                <a href="#" className="hover:text-[#6ee7b7]">Instagram</a>
              </div>
            </div>
          </div>

          <div className="border-t border-gray-700 pt-8 text-center text-sm text-gray-400">
            <p>&copy; 2026 한국사회주택협회. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
