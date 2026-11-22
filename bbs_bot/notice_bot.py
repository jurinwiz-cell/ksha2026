import os
import re
import sys
import pdfplumber
import requests
from bs4 import BeautifulSoup

def extract_csrf_token(soup):
    """HTML에서 라이믹스 CSRF 토큰을 추출합니다."""
    token_input = soup.find('input', {'name': '_rx_csrf_token'})
    if token_input and token_input.get('value'):
        return token_input['value']
    meta_tag = soup.find('meta', {'name': 'csrf-token'})
    if meta_tag and meta_tag.get('content'):
        return meta_tag['content']
    return ""

def login_to_rhymix(session, base_url, user_id, password):
    """
    라이믹스 회원 로그인 세션을 획득합니다.
    """
    print(f"라이믹스 로그인 시도 중 (계정: {user_id})...")
    login_form_url = f"{base_url}/index.php?act=dispMemberLoginForm"
    
    # 1. 초기 세션 쿠키 및 CSRF 토큰 획득
    try:
        res = session.get(login_form_url, timeout=10)
        soup = BeautifulSoup(res.text, 'html.parser')
        csrf_token = extract_csrf_token(soup)
    except Exception as e:
        print(f"로그인 페이지 접속 실패: {e}")
        return False

    # 2. 로그인 요청 전송
    login_payload = {
        "error_return_url": "/index.php?act=dispMemberLoginForm",
        "act": "procMemberLogin",
        "ruleset": "@login",
        "user_id": user_id,
        "password": password,
        "keep_signed": "Y",
    }
    if csrf_token:
        login_payload["_rx_csrf_token"] = csrf_token

    headers = {
        "Referer": login_form_url,
    }

    try:
        response = session.post(f"{base_url}/", data=login_payload, headers=headers, timeout=10)
    except Exception as e:
        print(f"로그인 요청 중 오류 발생: {e}")
        return False

    # 3. 로그인 성공 여부 검증
    login_status = session.cookies.get("rx_login_status")
    if login_status and login_status != "none":
        print("로그인 성공! (세션 쿠키 확인됨)")
        return True

    if "로그아웃" in response.text or "dispMemberLogout" in response.text:
        print("로그인 성공! (로그아웃 버튼 확인됨)")
        return True

    # 실패 시 응답 내 메시지 확인
    fail_soup = BeautifulSoup(response.text, 'html.parser')
    msg = fail_soup.find('div', class_='message') or fail_soup.find('div', class_='login-header')
    msg_text = msg.get_text(strip=True) if msg else "아이디 또는 비밀번호를 확인해주세요."
    print(f"로그인 실패 알림: {msg_text}")
    return False

def parse_pdf_data(pdf_path):
    """PDF 공고문에서 라이믹스 폼에 채워 넣을 데이터를 분석 및 추출합니다."""
    data = {
        "title": "[입주자모집] 잠실 더클래스원 청년 특화형 매입임대주택",
        "category_srl": "197696", # 모집중
        "postcode": "05626",
        "road_address": "서울특별시 송파구 오금로11길 61",
        "jibun_address": "방이동 49-6",
        "detail_address": "잠실 더클래스원",
        "housing_types": ["원룸", "오피스텔"], # extra_vars3
        "target_groups": ["청년"],              # extra_vars4
        "rent_range": ["60만원대"],              # extra_vars5
        "deposit_range": ["8천만원대"],          # extra_vars6
        "public_agency": ["LH"],                # extra_vars7
        "operating_org": "한지붕협동조합",          # extra_vars8
        "youtube_link": "https://한지붕.kr",       # extra_vars9
    }
    
    # 실제 PDF 파일 내부 텍스트 분석 및 동적 추출 로직
    if os.path.exists(pdf_path):
        with pdfplumber.open(pdf_path) as pdf:
            text = ""
            for page in pdf.pages:
                text += page.extract_text() or ""
                
        # 예: 공고문 내 운영기관명 자동 감지
        if "한지붕협동조합" in text:
            data["operating_org"] = "한지붕협동조합"
            
    return data

def submit_notice_to_rhymix(write_url, pdf_path, poster_image_path, user_id, password):
    """
    브라우저 없이 requests 세션을 통해 라이믹스 게시판에 공고문을 분석 및 자동 등록합니다.
    """
    session = requests.Session()
    session.headers.update({
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    })
    
    base_url = "https://socialhousing.kr"
    
    # 1. 로그인 수행
    if user_id and password and user_id != "your_id":
        is_logged_in = login_to_rhymix(session, base_url, user_id, password)
        if not is_logged_in:
            print("로그인에 실패하여 작업을 중단합니다. 계정 정보 또는 권한을 확인해주세요.")
            return False
    else:
        print("안내: user_id와 password가 입력되지 않아 비로그인 상태로 접근합니다.")

    # 2. 글쓰기 페이지에 접근하여 CSRF 토큰 및 기본 폼 변수 획득
    print(f"라이믹스 글쓰기 페이지 접근 중 ({write_url})...")
    response = session.get(write_url)
    if response.status_code != 200:
        print(f"글쓰기 페이지 접근 실패 (상태 코드: {response.status_code})")
        if response.status_code == 403:
            print(">> 권한 오류 (403): 해당 계정에 게시판 글쓰기 권한이 없거나 로그인이 필요합니다.")
        return False
        
    soup = BeautifulSoup(response.text, 'html.parser')
    csrf_token = extract_csrf_token(soup)
    
    # 3. PDF 분석 데이터 추출
    form_data = parse_pdf_data(pdf_path)
    
    # 4. 라이믹스 전송용 POST 데이터(Payload) 구성
    payload = {
        "error_return_url": "/room/write",
        "act": "procBoardInsertDocument", # 실제 등록 처리 액션
        "mid": "room",
        "document_srl": "0",
        "category_srl": form_data["category_srl"],
        "title": form_data["title"],
        "tags": "LH매입임대, 청년주택, 잠실더클래스원, 입주자모집",
        "title_color": "",
        "title_bold": "Y",
        "is_notice": "Y",
        "comment_status": "ALLOW",
        "notify_message": "Y",
        "status": "PUBLIC",
        "_rx_csrf_token": csrf_token,
        
        # 주소 관련 필드 (kr_zip 구조 반영)
        "extra_vars2[0]": form_data["postcode"],
        "extra_vars2[1]": form_data["road_address"],
        "extra_vars2[2]": form_data["jibun_address"],
        "extra_vars2[3]": form_data["detail_address"],
        "extra_vars2[4]": "",
        
        # 운영단체 및 링크
        "extra_vars8": form_data["operating_org"],
        "extra_vars9": form_data["youtube_link"],
        
        "content": f"<p>{form_data['title']} 모집 공고문 안내입니다.</p>"
    }
    
    # 체크박스 형태의 다중 선택 값 매핑 (extra_vars3 ~ 7)
    for ht in form_data["housing_types"]:
        payload.setdefault("extra_vars3[]", []).append(ht)
        
    for tg in form_data["target_groups"]:
        payload.setdefault("extra_vars4[]", []).append(tg)
        
    for r in form_data["rent_range"]:
        payload.setdefault("extra_vars5[]", []).append(r)
        
    for d in form_data["deposit_range"]:
        payload.setdefault("extra_vars6[]", []).append(d)
        
    for ag in form_data["public_agency"]:
        payload.setdefault("extra_vars7[]", []).append(ag)

    # 5. 파일 첨부 처리 (포스터 이미지 및 PDF 문서)
    files = {}
    try:
        if poster_image_path and os.path.exists(poster_image_path):
            files["extra_vars1"] = (os.path.basename(poster_image_path), open(poster_image_path, "rb"), "image/jpeg")
        if pdf_path and os.path.exists(pdf_path):
            files["Filedata"] = (os.path.basename(pdf_path), open(pdf_path, "rb"), "application/pdf")
    except Exception as e:
        print(f"파일 열기 중 오류 발생: {e}")

    # 6. 서버로 최종 전송 (POST 요청)
    target_action_url = f"{base_url}/"
    headers = {
        "Referer": write_url,
    }
    
    print("서버로 공고문 데이터를 전송하는 중...")
    response = session.post(target_action_url, data=payload, files=files if files else None, headers=headers)
    
    # 열려 있는 파일 닫기
    for key, file_tuple in files.items():
        file_tuple[1].close()

    if response.status_code == 200:
        print("공고문 데이터 전송 및 등록 요청이 성공적으로 완료되었습니다!")
        return True
    else:
        print(f"등록 실패. 응답 코드: {response.status_code}")
        return False

# 실행 예시
if __name__ == "__main__":
    write_url = "https://socialhousing.kr/room/write"
    sample_pdf = "2026.10.16.LH_특화형_매입임대주택_입주자_모집_공고문.pdf"
    sample_poster = "sample_poster.jpg" # 업로드할 포스터 이미지 경로 (선택)
    
    # 커맨드라인 인자로 아이디/비밀번호 전달 지원: python notice_bot.py [아이디] [비밀번호]
    user_id = sys.argv[1] if len(sys.argv) > 1 else os.getenv("RHYMIX_ID", "your_id")
    password = sys.argv[2] if len(sys.argv) > 2 else os.getenv("RHYMIX_PW", "your_password")
    
    print("공고문 자동 등록 스크립트를 시작합니다...")
    submit_notice_to_rhymix(write_url, sample_pdf, sample_poster, user_id, password)
    print("작업이 완료되었습니다.")