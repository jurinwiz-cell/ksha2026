#!/usr/bin/env python3
"""
import_sh_comments.py
-------------------------------------------------
Purpose : sh_comments.xml 에 들어 있는 <table name="sh_comments">
          레코드들을 socialhousing.sh_comments 테이블에 INSERT 합니다.
Author  : Antigravity (auto‑generated)
"""

import sys
import xml.etree.ElementTree as ET
import pymysql

# ----------------------------------------------------------------------
# 1. 설정 (필요에 따라 수정)
# ----------------------------------------------------------------------
DB_HOST = "localhost"          # DB 서버 주소
DB_USER = "db_user"            # ← 여기 ← MySQL 사용자명
DB_PASSWORD = "YOUR_PASSWORD" # ← 여기 ← 비밀번호
DB_NAME = "socialhousing"

# ----------------------------------------------------------------------
# 2. XML 파싱 → INSERT 구문 생성
# ----------------------------------------------------------------------
def parse_xml_to_rows(xml_path: str):
    """
    XML 파일을 파싱하고, <table name="sh_comments"> 요소마다
    컬럼 이름과 값을 추출해 딕셔너리 리스트를 반환합니다.
    """
    tree = ET.parse(xml_path)
    root = tree.getroot()

    rows = []
    for table in root.iter('table'):
        if table.get('name') != 'sh_comments':
            continue

        row = {}
        for col in table.findall('column'):
            col_name = col.get('name')
            # 텍스트가 없을 경우 None 로 저장 (NULL 처리)
            val = col.text if col.text is not None else None
            row[col_name] = val
        rows.append(row)
    return rows


def build_insert_sql(row: dict):
    """
    딕셔너리(row)를 받아 INSERT 구문과 파라미터 튜플을 반환합니다.
    NULL 값은 파라미터에 None 으로 넘겨 pymysql 이 자동 변환하도록 합니다.
    """
    columns = ', '.join(f"`{k}`" for k in row.keys())
    placeholders = ', '.join('%s' for _ in row)
    sql = f"INSERT INTO `sh_comments` ({columns}) VALUES ({placeholders})"
    params = tuple(row.values())
    return sql, params


# ----------------------------------------------------------------------
# 3. DB 연결 & 다중 INSERT 실행
# ----------------------------------------------------------------------
def insert_rows(rows):
    """
    주어진 row 리스트를 하나씩 INSERT 합니다.
    대량 삽입이므로 트랜잭션을 사용해 한번에 커밋합니다.
    """
    conn = pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        charset='utf8mb4',
        autocommit=False
    )
    try:
        with conn.cursor() as cur:
            for row in rows:
                sql, params = build_insert_sql(row)
                cur.execute(sql, params)
        conn.commit()
        print(f"[INFO] 성공적으로 {len(rows)} 개의 레코드를 삽입했습니다.")
    except Exception as e:
        conn.rollback()
        print("[ERROR] 삽입 중 오류 발생:", e)
        sys.exit(1)
    finally:
        conn.close()


# ----------------------------------------------------------------------
# 4. 메인 엔트리 포인트
# ----------------------------------------------------------------------
def main():
    if len(sys.argv) != 2:
        print("Usage: python import_sh_comments.py <path_to_sh_comments.xml>")
        sys.exit(1)

    xml_path = sys.argv[1]
    print(f"[INFO] XML 파일 파싱 중: {xml_path}")
    rows = parse_xml_to_rows(xml_path)

    if not rows:
        print("[WARN] 삽입할 레코드가 없습니다.")
        sys.exit(0)

    print(f"[INFO] 총 {len(rows)} 개의 레코드 발견")
    insert_rows(rows)


if __name__ == "__main__":
    main()
