import React, { useState, useEffect } from 'react';
import { initializeApp } from 'firebase/app';
import { getAuth, signInWithCustomToken, signInAnonymously, onAuthStateChanged } from 'firebase/auth';
import { getFirestore, doc, setDoc, onSnapshot, updateDoc } from 'firebase/firestore';
import { Pencil, Save, X, Lock, Unlock, CheckCircle } from 'lucide-react';

// ============================================================================
// Firebase Initialization (Strict Rules Applied)
// ============================================================================
const firebaseConfig = typeof __firebase_config !== 'undefined' ? JSON.parse(__firebase_config) : {};
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);
const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-app-id';

// ============================================================================
// Initial Default Data (Firestore Seed)
// ============================================================================
const defaultData = {
  portalTitle: "공공임대주택 통합 안내 포털",
  portalSubtitle: "민간참여형 특화 및 공공주체별 사업 공모 안내",
  
  // Tab Names
  tab1Name: "2026년 상반기 특화형 공고",
  tab2Name: "공공주체별 공모 안내",
  tab3Name: "연도별 현황",

  // Doc 1 (2026 특화형)
  doc1Date: "공고일: 2026.04.03",
  doc1Title: "2026년 상반기 특화형 임대주택 모집 공고",
  doc1Subtitle: "(민간제안형)",
  doc1Scale: "1,000호 내외",
  doc1Deadline: "~ 26.5.11(월) 17시",
  
  doc1OverviewDesc: "(사업구조) 민간사업자가 자유롭게 테마를 제안하여 기획·설계·시공하여 공사가 매입 후 민간사업자가 테마에 따른 입주자 선발 및 차별화된 주거 서비스 제공 등 임대 운영관리까지 수행",
  doc1OverviewTheme: "(운영테마) 돌봄·육아·교육, 일자리·창업지원, 귀농·귀촌, 장애인·자립지원 등 사업시행자가 자유롭게 제안",
  doc1OverviewScale: "(공모규모) 1,000호 내외",
  doc1OverviewArea: "(공모지역) 인구 8만 이상의 전국 대상",

  // Doc 2 (공공주체별)
  doc2Title: "공공주체별 공모사업 안내",
  doc2Subtitle: "민간참여형(민관협력형) 공공임대주택",
  doc2Notice1: "민간참여형(민관협력형) 공공임대주택 사업공모들을 공공주체별로 모아 공유",
  doc2Notice2: "공공주택특별법 및 민간임대주택특별법 등에 따라 다양한 공공주체가 사업 공고를 발표하고 있음",

  // Doc 3 (연도별)
  doc3Title: "연도별 공모 현황 및 결과",
  doc3Subtitle: "최근 공모사업 진행 상황부터 과거 공모 결과까지 안내",
};

// ============================================================================
// Main Application Component
// ============================================================================
export default function App() {
  const [user, setUser] = useState(null);
  const [portalData, setPortalData] = useState(defaultData);
  const [loading, setLoading] = useState(true);
  
  // Navigation State
  const [activeMainTab, setActiveMainTab] = useState(1);
  const [activeDoc1Tab, setActiveDoc1Tab] = useState(1);
  const [activeDoc2Tab, setActiveDoc2Tab] = useState(1);

  // Admin & Edit Mode State
  const [isAdmin, setIsAdmin] = useState(false);
  const [isEditMode, setIsEditMode] = useState(false);
  const [showAdminModal, setShowAdminModal] = useState(false);
  const [adminPassword, setAdminPassword] = useState('');

  // 1. Authenticate
  useEffect(() => {
    const initAuth = async () => {
      try {
        if (typeof __initial_auth_token !== 'undefined' && __initial_auth_token) {
          await signInWithCustomToken(auth, __initial_auth_token);
        } else {
          await signInAnonymously(auth);
        }
      } catch (error) {
        console.error("Auth Error:", error);
      }
    };
    initAuth();

    const unsubscribe = onAuthStateChanged(auth, (currentUser) => {
      setUser(currentUser);
    });
    return () => unsubscribe();
  }, []);

  // 2. Fetch Data from Firestore
  useEffect(() => {
    if (!user) return;

    const docRef = doc(db, 'artifacts', appId, 'public', 'data', 'portalContent', 'main');
    
    const unsubscribe = onSnapshot(docRef, (docSnap) => {
      if (docSnap.exists()) {
        setPortalData({ ...defaultData, ...docSnap.data() });
      } else {
        // Seed default data if it doesn't exist
        setDoc(docRef, defaultData).catch(console.error);
      }
      setLoading(false);
    }, (error) => {
      console.error("Firestore error:", error);
      setLoading(false);
    });

    return () => unsubscribe();
  }, [user]);

  // Handle saving edits to Firestore
  const handleSaveField = async (fieldKey, newValue) => {
    if (!user || !isAdmin) return;
    const docRef = doc(db, 'artifacts', appId, 'public', 'data', 'portalContent', 'main');
    try {
      await updateDoc(docRef, { [fieldKey]: newValue });
    } catch (error) {
      console.error("Update failed:", error);
      // If document doesn't exist yet for some reason, set it
      await setDoc(docRef, { ...portalData, [fieldKey]: newValue }, { merge: true });
    }
  };

  const handleAdminLogin = () => {
    if (adminPassword === 'admin') {
      setIsAdmin(true);
      setShowAdminModal(false);
      setAdminPassword('');
    } else {
      // In a real app we'd show an error state, here we just clear for demo
      setAdminPassword('');
    }
  };

  if (loading) {
    return <div className="min-h-screen flex items-center justify-center bg-slate-100 text-indigo-600 font-bold">데이터를 불러오는 중입니다...</div>;
  }

  return (
    <div className="bg-slate-100 text-gray-800 p-0 md:p-4 min-h-screen font-sans" style={{ fontFamily: "'Pretendard', sans-serif" }}>
      
      {/* Edit Mode Warning Banner */}
      {isAdmin && isEditMode && (
        <div className="fixed top-0 left-0 right-0 bg-yellow-400 text-yellow-900 text-center py-2 font-bold z-50 shadow-md animate-pulse">
          현재 편집 모드입니다. 노란색 영역을 클릭하여 내용을 수정할 수 있습니다.
        </div>
      )}

      <div className={`w-full bg-white shadow-xl md:rounded-2xl overflow-hidden relative ${isAdmin && isEditMode ? 'mt-10' : ''}`}>
        
        {/* ============================================================== */}
        {/* App Header */}
        {/* ============================================================== */}
        <div className="bg-indigo-800 text-white p-6 md:p-8 text-center relative">
          <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight mb-2">
            <EditableField 
              value={portalData.portalTitle} 
              onSave={(val) => handleSaveField('portalTitle', val)} 
              isEditMode={isEditMode} 
              className="inline-block"
            />
          </h1>
          <p className="text-indigo-200 text-lg">
            <EditableField 
              value={portalData.portalSubtitle} 
              onSave={(val) => handleSaveField('portalSubtitle', val)} 
              isEditMode={isEditMode} 
              className="inline-block"
            />
          </p>
        </div>

        {/* ============================================================== */}
        {/* Main Tab Navigation */}
        {/* ============================================================== */}
        <div className="flex flex-col sm:flex-row bg-indigo-50 border-b-2 border-indigo-200">
          <TabButton active={activeMainTab === 1} onClick={() => setActiveMainTab(1)} theme="indigo">
            <EditableField value={portalData.tab1Name} onSave={(val) => handleSaveField('tab1Name', val)} isEditMode={isEditMode} />
          </TabButton>
          <TabButton active={activeMainTab === 2} onClick={() => setActiveMainTab(2)} theme="indigo">
            <EditableField value={portalData.tab2Name} onSave={(val) => handleSaveField('tab2Name', val)} isEditMode={isEditMode} />
          </TabButton>
          <TabButton active={activeMainTab === 3} onClick={() => setActiveMainTab(3)} theme="indigo">
            <EditableField value={portalData.tab3Name} onSave={(val) => handleSaveField('tab3Name', val)} isEditMode={isEditMode} />
          </TabButton>
        </div>

        {/* ============================================================== */}
        {/* DOCUMENT 1 : 2026 특화형 모집공고 */}
        {/* ============================================================== */}
        {activeMainTab === 1 && (
          <div className="animate-in fade-in duration-300">
            <div className="bg-blue-600 text-white p-6 md:p-8 text-center relative">
              <p className="absolute top-4 right-6 text-sm text-blue-200">
                <EditableField value={portalData.doc1Date} onSave={(val) => handleSaveField('doc1Date', val)} isEditMode={isEditMode} />
              </p>
              <h2 className="text-2xl md:text-3xl font-bold mt-2 mb-2">
                <EditableField value={portalData.doc1Title} onSave={(val) => handleSaveField('doc1Title', val)} isEditMode={isEditMode} />
              </h2>
              <p className="text-lg text-blue-100">
                <EditableField value={portalData.doc1Subtitle} onSave={(val) => handleSaveField('doc1Subtitle', val)} isEditMode={isEditMode} />
              </p>
              <div className="mt-4 inline-flex space-x-4 text-sm bg-blue-700/50 rounded-full px-5 py-2 items-center">
                <span><strong className="text-white">공모규모:</strong> <EditableField value={portalData.doc1Scale} onSave={(val) => handleSaveField('doc1Scale', val)} isEditMode={isEditMode} className="inline-block" /></span>
                <span>|</span>
                <span><strong className="text-white">접수:</strong> <EditableField value={portalData.doc1Deadline} onSave={(val) => handleSaveField('doc1Deadline', val)} isEditMode={isEditMode} className="inline-block" /></span>
              </div>
            </div>

            <div className="flex overflow-x-auto border-b border-gray-200 bg-gray-50 text-sm md:text-base sticky top-0 z-10">
              <SubTabButton active={activeDoc1Tab === 1} onClick={() => setActiveDoc1Tab(1)} theme="blue">1. 공모개요</SubTabButton>
              <SubTabButton active={activeDoc1Tab === 2} onClick={() => setActiveDoc1Tab(2)} theme="blue">2. 신청자격</SubTabButton>
              <SubTabButton active={activeDoc1Tab === 3} onClick={() => setActiveDoc1Tab(3)} theme="blue">3. 주요 계획기준</SubTabButton>
              <SubTabButton active={activeDoc1Tab === 4} onClick={() => setActiveDoc1Tab(4)} theme="blue">4. 신청방법 등</SubTabButton>
            </div>

            <div className="p-6 md:p-10 min-h-[500px]">
              {/* Doc 1 - Sub 1: 공모개요 */}
              {activeDoc1Tab === 1 && (
                <div className="animate-in fade-in duration-300">
                  <h3 className="text-xl font-bold mb-6 text-blue-700 border-l-4 border-blue-600 pl-3">공모개요</h3>
                  <ul className="space-y-4 text-gray-700 mb-8">
                    <li className="flex items-start">
                      <span className="font-bold text-blue-600 mr-2">■</span>
                      <div className="flex-1"><EditableField multiline value={portalData.doc1OverviewDesc} onSave={(val) => handleSaveField('doc1OverviewDesc', val)} isEditMode={isEditMode} /></div>
                    </li>
                    <li className="flex items-start">
                      <span className="font-bold text-blue-600 mr-2">■</span>
                      <div className="flex-1"><EditableField multiline value={portalData.doc1OverviewTheme} onSave={(val) => handleSaveField('doc1OverviewTheme', val)} isEditMode={isEditMode} /></div>
                    </li>
                    <li className="flex items-start">
                      <span className="font-bold text-blue-600 mr-2">■</span>
                      <div className="flex-1">
                        <EditableField multiline value={portalData.doc1OverviewScale} onSave={(val) => handleSaveField('doc1OverviewScale', val)} isEditMode={isEditMode} />
                        <p className="text-sm text-red-500 mt-1">※ 공모 규모 범위 내에서 선정하되, 수요부족, 건축계획 등이 부적합할 경우 공모 규모에도 불구하고 선정하지 아니할 수 있음</p>
                      </div>
                    </li>
                    <li className="flex items-start">
                      <span className="font-bold text-blue-600 mr-2">■</span>
                      <div className="flex-1">
                        <EditableField multiline value={portalData.doc1OverviewArea} onSave={(val) => handleSaveField('doc1OverviewArea', val)} isEditMode={isEditMode} />
                        <p className="text-sm text-gray-500 mt-1">- 단, 인구 8만 미만의 소도시·농산어촌 지역의 경우 읍, 면, 행정복지센터 소재지에 한하여 허용</p>
                      </div>
                    </li>
                  </ul>
                  
                  <h4 className="font-bold text-lg mb-4">■ 공모절차 (고정형식)</h4>
                  <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
                    <ProcessBox step="①" title="사업 공모" subtitle="(본사)" date="‘26.4.3(금)" />
                    <ProcessBox step="②" title="사업 신청접수" subtitle="(본사)" date="‘26.4.27(월)~5.11(월) 17시" highlight />
                    <ProcessBox step="③" title="서류심사 결과통보" subtitle="(본사)" date="‘26.6.17(수)" />
                    <ProcessBox step="④" title="본심사 결과통보" subtitle="(본사&지역본부)" date="‘26.7.16(목)" />
                    <ProcessBox step="⑤" title="도면협의/감정평가" subtitle="(지역본부)" date="‘26.8월~9월" />
                    <ProcessBox step="⑥" title="매입약정체결" subtitle="(지역본부)" date="‘26.10월말" />
                  </div>
                </div>
              )}

              {/* Other sub tabs remain structural/static for this demo to save space, but easily convertible */}
              {activeDoc1Tab === 2 && (
                 <div className="animate-in fade-in duration-300">
                    <h3 className="text-xl font-bold mb-6 text-blue-700 border-l-4 border-blue-600 pl-3">신청자격</h3>
                    <p className="mb-4 text-gray-700 bg-yellow-50 p-4 rounded border border-yellow-200">
                        사업 시행자는 본 사업을 시행(일괄운영-마스터리스방식)하고자 하는 자로 
                        <strong className="text-blue-600"> 공고일(2026.4.3.) 기준</strong> 아래 자격을 충족하여야 함.<br/>
                        <span className="text-sm mt-2 block">※ 법인 정관에 <u>주택의 임대운영을 사업목적으로 정하고 있거나 주택(기숙사) 임대운영 실적이 있는 자</u></span>
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-gray-700 ml-4 mb-8">
                        <p>① 「민법」 제32조에 따라 허가를 얻은 <strong>비영리법인</strong></p>
                        <p>② 「공익법인의 설립·운영에 관한 법률」 제2조에 따른 <strong>공익법인</strong></p>
                        <p>③ 「협동조합 기본법」에 따른 <strong>협동조합 및 사회적 협동조합</strong></p>
                        <p>④ 「사회적기업 육성법」에 따른 <strong>사회적기업</strong></p>
                        <p>⑤ 「고등교육법」에 따른 <strong>학교</strong></p>
                        <p>⑥ 「민간임대주택에 관한 특별법」에 따른 <strong>주택임대관리업자</strong></p>
                    </div>
                 </div>
              )}
              {activeDoc1Tab === 3 && (
                  <div className="animate-in fade-in duration-300">
                    <h3 className="text-xl font-bold mb-6 text-blue-700 border-l-4 border-blue-600 pl-3">주요 계획기준</h3>
                    <p className="text-gray-500 mb-4">복잡한 표와 구조화된 데이터 영역입니다.</p>
                    <div className="bg-gray-50 border border-gray-200 rounded p-4 text-sm space-y-2">
                        <p className="font-bold text-gray-800">신청 건별 규모 상세 기준</p>
                        <p>① <strong>광역시는 100호 미만, 비수도권 지역은 50호 미만</strong>으로 계획</p>
                        <p>② <strong>소도시·농산어촌지역</strong>(인구 8만 미만)은 <strong>10호 이상 30호 미만</strong>으로 계획</p>
                    </div>
                  </div>
              )}
              {activeDoc1Tab === 4 && (
                  <div className="animate-in fade-in duration-300">
                    <h3 className="text-xl font-bold mb-6 text-blue-700 border-l-4 border-blue-600 pl-3">신청방법 및 문의처</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <div className="text-sm text-gray-500 font-bold mb-1">접수기간</div>
                            <div className="text-xl font-bold text-red-600 mb-4">‘26.4.27(월) 09:00 ~ ‘26.5.11(월) 17:00</div>
                            <div className="text-sm text-gray-500 font-bold mb-1">제출처</div>
                            <div><a href="mailto:lh12345@lh.or.kr" className="text-blue-600 hover:underline font-bold">lh12345@lh.or.kr</a></div>
                        </div>
                    </div>
                  </div>
              )}
            </div>
          </div>
        )}

        {/* ============================================================== */}
        {/* DOCUMENT 2 : 공공주체별 공모사업 안내 */}
        {/* ============================================================== */}
        {activeMainTab === 2 && (
          <div className="animate-in fade-in duration-300">
            <div className="bg-cyan-700 text-white p-6 md:p-8 text-center relative">
              <h2 className="text-2xl md:text-3xl font-bold mb-3">
                <EditableField value={portalData.doc2Title} onSave={(val) => handleSaveField('doc2Title', val)} isEditMode={isEditMode} />
              </h2>
              <p className="text-lg text-cyan-100">
                <EditableField value={portalData.doc2Subtitle} onSave={(val) => handleSaveField('doc2Subtitle', val)} isEditMode={isEditMode} />
              </p>
            </div>

            <div className="bg-slate-50 p-6 md:p-8 border-b border-gray-200">
              <ul className="space-y-3 text-gray-700">
                <li className="flex items-start">
                  <CheckCircle className="w-5 h-5 mr-3 text-cyan-600 mt-0.5 flex-shrink-0" />
                  <div className="flex-1"><EditableField value={portalData.doc2Notice1} onSave={(val) => handleSaveField('doc2Notice1', val)} isEditMode={isEditMode} multiline/></div>
                </li>
                <li className="flex items-start">
                  <CheckCircle className="w-5 h-5 mr-3 text-cyan-600 mt-0.5 flex-shrink-0" />
                  <div className="flex-1"><EditableField value={portalData.doc2Notice2} onSave={(val) => handleSaveField('doc2Notice2', val)} isEditMode={isEditMode} multiline/></div>
                </li>
              </ul>
            </div>

            <div className="flex overflow-x-auto border-b border-gray-200 bg-white sticky top-0 z-10 text-sm md:text-base shadow-sm">
              <SubTabButton active={activeDoc2Tab === 1} onClick={() => setActiveDoc2Tab(1)} theme="cyan">LH 한국토지주택공사</SubTabButton>
              <SubTabButton active={activeDoc2Tab === 2} onClick={() => setActiveDoc2Tab(2)} theme="cyan">SH 서울주택도시공사</SubTabButton>
              <SubTabButton active={activeDoc2Tab === 3} onClick={() => setActiveDoc2Tab(3)} theme="cyan">GH 경기주택도시공사</SubTabButton>
            </div>

            <div className="p-6 md:p-10 min-h-[400px]">
              {activeDoc2Tab === 1 && (
                <div className="animate-in fade-in duration-300">
                   <h3 className="text-2xl font-bold mb-6 text-cyan-900 border-l-4 border-cyan-600 pl-3">LH 한국토지주택공사</h3>
                   <div className="bg-cyan-50/50 rounded-lg p-6 md:p-8 border border-cyan-100">
                      <p className="font-bold text-gray-900 text-lg mb-5 flex items-start">
                          <span className="text-cyan-600 mr-2 mt-0.5">■</span> 
                          <span>LH는 기존주택매입, 일반매입약정, 특화형 매입약정 등으로 청약플러스를 통해 공고합니다.</span>
                      </p>
                      <ul className="list-disc pl-8 space-y-3 text-gray-700 leading-relaxed marker:text-cyan-400">
                          <li>일반적으로 LH 본사에서 개괄적인 방침을 담은 사전공고를 내고 이어 지역본부에서 개별 공고를 진행</li>
                          <li>2024년 하반기 공모 공고, 접수는 25년 2월부터 예정</li>
                      </ul>
                   </div>
                </div>
              )}
              {activeDoc2Tab === 2 && (
                <div className="animate-in fade-in duration-300">
                   <h3 className="text-2xl font-bold mb-6 text-cyan-900 border-l-4 border-cyan-600 pl-3">SH 서울주택도시공사</h3>
                   <div className="bg-gray-50 rounded-lg p-6 md:p-8 border border-gray-200">
                      <p className="font-bold text-gray-900 text-lg mb-5 flex items-start">
                          <span className="text-cyan-600 mr-2 mt-0.5">■</span> 
                          <span>SH는 일반 매입임대공고(주로 매입약정방식)가 진행되고 있습니다.</span>
                      </p>
                   </div>
                </div>
              )}
              {activeDoc2Tab === 3 && (
                <div className="animate-in fade-in duration-300">
                   <h3 className="text-2xl font-bold mb-6 text-cyan-900 border-l-4 border-cyan-600 pl-3">GH 경기주택도시공사</h3>
                   <div className="bg-gray-50 rounded-lg p-6 md:p-8 border border-gray-200">
                      <p className="font-bold text-gray-900 text-lg mb-5 flex items-start">
                          <span className="text-cyan-600 mr-2 mt-0.5">■</span> 
                          <span>GH는 기존주택매입방식 위주에서 매입약정형이 확대되고 있습니다.</span>
                      </p>
                   </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* ============================================================== */}
        {/* DOCUMENT 3 : 연도별 현황 */}
        {/* ============================================================== */}
        {activeMainTab === 3 && (
          <div className="animate-in fade-in duration-300">
            <div className="bg-emerald-700 text-white p-6 md:p-8 text-center relative">
              <h2 className="text-2xl md:text-3xl font-bold mb-3">
                <EditableField value={portalData.doc3Title} onSave={(val) => handleSaveField('doc3Title', val)} isEditMode={isEditMode} />
              </h2>
              <p className="text-lg text-emerald-100">
                <EditableField value={portalData.doc3Subtitle} onSave={(val) => handleSaveField('doc3Subtitle', val)} isEditMode={isEditMode} />
              </p>
            </div>
            
            <div className="p-6 md:p-10 space-y-12">
               <section>
                  <h3 className="text-2xl font-bold mb-6 text-emerald-800 border-l-4 border-emerald-600 pl-3">2025년 주요 공모 현황</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                      <div className="bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition overflow-hidden">
                          <div className="bg-emerald-50 p-4 border-b border-gray-200 flex justify-between items-center">
                              <h4 className="font-bold text-emerald-900 text-lg">GH 특화형(11월)</h4>
                              <span className="bg-blue-100 text-blue-800 text-xs font-bold px-2.5 py-1 rounded">진행중</span>
                          </div>
                          <div className="p-5 text-sm text-gray-700 space-y-3">
                              <p><strong className="text-gray-900">민간제안형</strong> - 청년, 신혼부부 등 경기도 주민들의 주거안정을 위한 공공민간협력 공급</p>
                              <ul className="list-disc pl-5 space-y-1 marker:text-emerald-500">
                                  <li>대상주택: 착공신고 전 건축 예정 주택</li>
                                  <li>대상지역: 경기도 전역</li>
                              </ul>
                          </div>
                      </div>
                  </div>
               </section>
            </div>
          </div>
        )}
      </div>

      {/* ============================================================== */}
      {/* Admin Controls (Floating) */}
      {/* ============================================================== */}
      <div className="fixed bottom-6 right-6 flex flex-col gap-3 z-50">
        {isAdmin ? (
          <>
            <button 
              onClick={() => setIsEditMode(!isEditMode)}
              className={`flex items-center gap-2 px-4 py-3 rounded-full shadow-lg font-bold text-white transition-all ${isEditMode ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-500 hover:bg-emerald-600'}`}
            >
              {isEditMode ? <><Save size={20}/> 편집 완료</> : <><Pencil size={20}/> 편집 모드 켜기</>}
            </button>
            <button 
              onClick={() => { setIsAdmin(false); setIsEditMode(false); }}
              className="flex items-center justify-center gap-2 px-4 py-2 rounded-full shadow bg-gray-800 text-white hover:bg-gray-700 text-sm"
            >
              <Unlock size={16}/> 로그아웃
            </button>
          </>
        ) : (
          <button 
            onClick={() => setShowAdminModal(true)}
            className="flex items-center justify-center w-14 h-14 rounded-full shadow-lg bg-gray-800 text-white hover:bg-gray-700 transition-all"
            title="관리자 로그인"
          >
            <Lock size={24}/>
          </button>
        )}
      </div>

      {/* Admin Login Modal */}
      {showAdminModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[100]">
          <div className="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative animate-in zoom-in-95 duration-200">
            <button onClick={() => setShowAdminModal(false)} className="absolute top-4 right-4 text-gray-400 hover:text-gray-800"><X size={20}/></button>
            <h3 className="text-xl font-bold mb-4 flex items-center gap-2"><Lock className="text-indigo-600"/> 관리자 로그인</h3>
            <p className="text-sm text-gray-500 mb-4">내용을 수정하려면 관리자 비밀번호를 입력하세요. (데모 비밀번호: <strong>admin</strong>)</p>
            <input 
              type="password" 
              value={adminPassword}
              onChange={(e) => setAdminPassword(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleAdminLogin()}
              className="w-full border border-gray-300 rounded-lg px-4 py-2 mb-4 focus:ring-2 focus:ring-indigo-500 outline-none"
              placeholder="비밀번호 입력"
            />
            <button 
              onClick={handleAdminLogin}
              className="w-full bg-indigo-600 text-white font-bold py-2 rounded-lg hover:bg-indigo-700"
            >
              로그인
            </button>
          </div>
        </div>
      )}

    </div>
  );
}

// ============================================================================
// Reusable UI Components
// ============================================================================

const TabButton = ({ active, onClick, children, theme }) => {
  const baseClasses = "flex-1 text-center py-4 px-4 font-bold whitespace-nowrap transition text-base md:text-lg border-b-4";
  const activeClasses = active 
    ? `text-${theme}-800 border-${theme}-800 bg-white` 
    : `text-gray-500 border-transparent hover:bg-${theme}-100 hover:text-${theme}-700`;
  
  return (
    <button className={`${baseClasses} ${activeClasses}`} onClick={onClick}>
      {children}
    </button>
  );
};

const SubTabButton = ({ active, onClick, children, theme }) => {
  const baseClasses = "flex-1 text-center py-4 px-4 font-semibold whitespace-nowrap transition border-b-2";
  const activeClasses = active 
    ? `text-${theme}-700 border-${theme}-700 bg-${theme}-50` 
    : `text-gray-500 border-transparent hover:text-${theme}-700 hover:border-${theme}-300`;
  
  return (
    <button className={`${baseClasses} ${activeClasses}`} onClick={onClick}>
      {children}
    </button>
  );
};

const ProcessBox = ({ step, title, subtitle, date, highlight }) => (
  <div className="bg-blue-50 p-4 rounded-lg border border-blue-100 flex flex-col justify-center h-full">
    <div className="text-blue-800 font-bold mb-1 whitespace-pre-line">{step} {title}<br/>{subtitle}</div>
    <div className={`${highlight ? 'text-red-600 font-bold' : 'text-gray-600'} text-sm break-keep`}>{date}</div>
  </div>
);

// ============================================================================
// The Core Editable Field Component
// ============================================================================
const EditableField = ({ value, onSave, isEditMode, multiline = false, className = "" }) => {
  const [isEditing, setIsEditing] = useState(false);
  const [tempVal, setTempVal] = useState(value);

  // Sync state if external value changes (from Firebase)
  useEffect(() => { setTempVal(value); }, [value]);

  if (!isEditMode) {
    return <span className={className}>{value}</span>;
  }

  const handleSave = () => {
    if (tempVal !== value) {
      onSave(tempVal);
    }
    setIsEditing(false);
  };

  const handleCancel = () => {
    setTempVal(value);
    setIsEditing(false);
  };

  if (isEditing) {
    return (
      <div className={`relative inline-flex items-center gap-2 w-full max-w-full ${className}`}>
        {multiline ? (
          <textarea 
            value={tempVal} 
            onChange={(e) => setTempVal(e.target.value)}
            className="w-full text-gray-900 border-2 border-yellow-400 bg-yellow-50 rounded p-3 text-base outline-none min-h-[150px] whitespace-pre-wrap leading-relaxed"
            autoFocus
          />
        ) : (
          <input 
            type="text" 
            value={tempVal} 
            onChange={(e) => setTempVal(e.target.value)}
            className="w-full text-gray-900 border-2 border-yellow-400 bg-yellow-50 rounded px-2 py-1 outline-none font-inherit text-inherit"
            autoFocus
            onKeyDown={(e) => e.key === 'Enter' && handleSave()}
          />
        )}
        <div className="flex flex-col gap-1 absolute -right-10 top-0 z-10">
           <button onClick={handleSave} className="bg-emerald-500 text-white p-1 rounded hover:bg-emerald-600 shadow"><CheckCircle size={16}/></button>
           <button onClick={handleCancel} className="bg-red-500 text-white p-1 rounded hover:bg-red-600 shadow"><X size={16}/></button>
        </div>
      </div>
    );
  }

  return (
    <span 
      onClick={() => setIsEditing(true)} 
      className={`cursor-pointer inline-flex items-center gap-1 bg-yellow-100 border border-yellow-300 border-dashed rounded px-1 text-inherit hover:bg-yellow-200 transition-colors ${className}`}
      title="클릭하여 수정"
      style={multiline ? { whiteSpace: 'pre-wrap', wordBreak: 'break-word', display: 'block' } : {}}
    >
      {value} <Pencil size={14} className="text-yellow-700 opacity-70 inline-block align-baseline ml-1" />
    </span>
  );
};
