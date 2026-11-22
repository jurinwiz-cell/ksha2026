import csv
import json

csv_file = r'c:\Users\jurin.DESKTOP-KQQH3HQ\Z\MyHome\한국토지주택공사_임대주택단지정보_건설_20250918.csv'
with open(csv_file, 'r', encoding='euc-kr') as f:
    reader = csv.reader(f)
    headers = next(reader)
    row1 = next(reader)
    print(json.dumps({"headers": headers, "row1": row1}, ensure_ascii=False))
