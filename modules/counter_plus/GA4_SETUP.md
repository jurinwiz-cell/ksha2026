# GA4 연동 설정 가이드

counter_plus 모듈에서 Google Analytics 4 데이터를 불러오기 위한 설정 안내입니다.

---

## 준비물

- Google 계정 (GA4 속성에 **뷰어 이상** 권한이 있는 계정)
- Google Analytics 4 속성 (GA4, UA 아님)
- Google Cloud Console 접근 권한

---

## STEP 1 — GA4 속성 ID 확인

1. [Google Analytics](https://analytics.google.com) 접속
2. 좌측 하단 **⚙ 관리** 클릭
3. **속성** 열 → **속성 세부정보** 클릭
4. **속성 ID** (숫자만, 예: `15064118477`) 복사해 둡니다

> ⚠️ UA-XXXXXXX-X 형식의 유니버설 애널리틱스(UA) 속성은 지원되지 않습니다.

---

## STEP 2 — Google Cloud 프로젝트 생성 / 선택

1. [Google Cloud Console](https://console.cloud.google.com) 접속
2. 상단 프로젝트 선택기 → **새 프로젝트** (또는 기존 프로젝트 선택)
3. 프로젝트 이름 입력 후 **만들기**

---

## STEP 3 — Google Analytics Data API 활성화

1. Cloud Console 좌측 메뉴 → **API 및 서비스** → **라이브러리**
2. 검색창에 `Google Analytics Data API` 입력
3. 결과 클릭 → **사용 설정** 버튼 클릭

---

## STEP 4 — OAuth 동의 화면 구성

1. **API 및 서비스** → **OAuth 동의 화면**
2. User Type: **외부** 선택 → **만들기**
3. 앱 이름, 사용자 지원 이메일 입력
4. **저장 후 계속** 클릭 (나머지 단계는 기본값으로 진행)
5. **테스트 사용자** 단계에서 GA4에 접근할 Google 계정 이메일 추가
6. **대시보드로 돌아가기** 클릭

> 앱을 게시(프로덕션)하지 않아도 테스트 사용자로 추가된 계정은 정상 사용 가능합니다.

---

## STEP 5 — OAuth 2.0 클라이언트 ID 생성

1. **API 및 서비스** → **사용자 인증 정보**
2. 상단 **+ 사용자 인증 정보 만들기** → **OAuth 클라이언트 ID**
3. 애플리케이션 유형: **웹 애플리케이션** 선택
4. 이름 입력 (예: `counter_plus`)
5. **승인된 리디렉션 URI** 항목에 아래 주소 추가:

   counter_plus 관리자 설정 페이지 (`⚙ GA4 설정`)에 표시된 **리디렉션 URI**를 그대로 복사해서 붙여넣습니다.

   ```
   예시: http://localhost/xe/?module=admin&act=dispCounter_plusAdminOAuthCallback
   ```

   > ⚠️ URI가 한 글자라도 다르면 `redirect_uri_mismatch` 오류가 발생합니다.  
   > 설정 페이지의 URI를 복사/붙여넣기로 정확히 입력하세요.

6. **만들기** 클릭
7. **클라이언트 ID** 와 **클라이언트 보안 비밀** 복사

---

## STEP 6 — counter_plus 모듈 설정

1. Rhymix 관리자 → **counter_plus** → **⚙ GA4 설정**
2. **STEP 1** 항목 입력:
   - GA4 속성 ID: `15064118477` (본인 속성 ID)
   - OAuth2 클라이언트 ID: 위에서 복사한 값
   - OAuth2 클라이언트 보안 비밀: 위에서 복사한 값
3. **저장** 버튼 클릭

---

## STEP 7 — Google 계정 연동

1. 저장 후 페이지가 새로고침되면 **STEP 2** 섹션에 구글 로그인 버튼이 표시됩니다
2. **Google 계정으로 로그인** 클릭
3. GA4 속성에 접근 권한이 있는 구글 계정으로 로그인
4. 권한 요청 화면에서 **허용** 클릭
5. 설정 페이지로 돌아오며 **✅ 연동됨** 상태로 변경되면 완료

---

## 연동 후 데이터 확인

- 관리자 → counter_plus → **GA4 탭**
- 기간 선택 후 **조회** 버튼 클릭
- 서브탭: **[요약] [인기 페이지] [방문자 분석] [실시간]**

---

## 자주 발생하는 오류

| 오류 | 원인 | 해결 |
|------|------|------|
| `redirect_uri_mismatch` | 리디렉션 URI 불일치 | Cloud Console에 설정 페이지의 URI를 정확히 등록 |
| `403 insufficient permissions` | 계정에 GA4 접근 권한 없음 | GA4 속성에서 해당 계정에 뷰어 권한 부여 |
| `액세스 토큰 없음 — 재연동 필요` | refresh_token 만료 또는 삭제 | GA4 설정 페이지에서 **연동 해제** 후 재연동 |
| `GA4 설정이 없습니다` | 속성 ID 또는 OAuth 정보 미입력 | STEP 6 설정 확인 |
| API 호출 실패 (SSL 오류) | XAMPP CA 번들 경로 불일치 | `GA4Client.php`의 `XAMPP_CA` 경로 확인 |

---

## 토큰 재발급 (자동)

- **access_token** 은 1시간 유효하며, 만료 시 **refresh_token** 으로 자동 재발급됩니다.
- **refresh_token** 은 Google 계정에서 앱 접근 권한을 수동으로 해제하거나,  
  동의 화면 설정을 변경하지 않는 한 영구 유효합니다.
- 재연동이 필요한 경우: 설정 페이지 → **연동 해제** → **Google 계정으로 로그인**

---

## 참고 링크

- [Google Cloud Console](https://console.cloud.google.com)
- [Google Analytics Data API 문서](https://developers.google.com/analytics/devguides/reporting/data/v1)
- [GA4 Realtime API 지원 Dimension/Metric](https://developers.google.com/analytics/devguides/reporting/data/v1/realtime-api-schema)
- [OAuth 2.0 동의 화면 설정](https://support.google.com/cloud/answer/10311615)
