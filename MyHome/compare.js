const fs = require('fs');

function parseCSV(text) {
    const lines = text.split('\n');
    const headers = lines[0].trim().split(',');
    const result = [];
    for (let i = 1; i < lines.length; i++) {
        let line = lines[i].trim();
        if (!line) continue;
        
        let row = [];
        let inQuotes = false;
        let start = 0;
        for (let j = 0; j < line.length; j++) {
            if (line[j] === '"') {
                inQuotes = !inQuotes;
            } else if (line[j] === ',' && !inQuotes) {
                row.push(line.substring(start, j).replace(/^"|"$/g, ''));
                start = j + 1;
            }
        }
        row.push(line.substring(start).replace(/^"|"$/g, ''));
        
        const obj = {};
        for(let j=0; j<headers.length; j++) {
            obj[headers[j]] = row[j];
        }
        result.push(obj);
    }
    return result;
}

const csvBuf = fs.readFileSync('한국토지주택공사_임대주택단지정보_건설_20250918.csv');
const decoder = new TextDecoder('euc-kr');
const csvText = decoder.decode(csvBuf);
const lhDataRaw = parseCSV(csvText);

// 1) 건설형 임대주택 csv 데이터를 별도의 json 파일로 생성
// Convert to match housing_data.json keys somewhat, or just leave as is. The user said "건설형 임대주택 csv 데이터를 별도의 json 파일로 생성"
fs.writeFileSync('lh_data.json', JSON.stringify(lhDataRaw, null, 2), 'utf-8');

const housingBuf = fs.readFileSync('housing_data.json');
const housingData = JSON.parse(housingBuf.toString('utf-8'));

// 2) 기존 json 데이터와 비교
let shNames = new Set();
let shAddresses = new Set();

let shKookminCount = 0;
let shPublicCount = 0;
for (const h of housingData) {
    if(h['단지명']) shNames.add(h['단지명'].trim());
    if(h['도로명주소']) {
        // Remove parens for better match
        let addr = h['도로명주소'].split('(')[0].trim();
        shAddresses.add(addr);
    }
    if(h['주택종류'] === '국민임대' || h['임대종류'] === '국민임대') shKookminCount++;
    if(h['주택종류'] === '공공임대' || h['임대종류'] === '공공임대') shPublicCount++;
}

let overlappingByName = 0;
let overlappingByAddress = 0;
let exactMatchCount = 0;
let uniqueLh = 0;

let overlaps = [];

// Since CSV has multiple rows per 단지코드 (different areas/sizes), 
// let's count unique complexes in LH data
let lhComplexes = new Map();
for (const lh of lhDataRaw) {
    if(!lhComplexes.has(lh['단지코드'])) {
        lhComplexes.set(lh['단지코드'], lh);
    }
}

for (const lh of lhComplexes.values()) {
    let nameMatch = false;
    let addrMatch = false;
    
    let lhName = (lh['단지명'] || '').trim();
    if(lhName && shNames.has(lhName)) nameMatch = true;
    
    let addrClean = (lh['주소'] || '').split('(')[0].trim();
    if(addrClean && shAddresses.has(addrClean)) addrMatch = true;
    
    if(nameMatch) overlappingByName++;
    if(addrMatch) overlappingByAddress++;
    
    if (nameMatch || addrMatch) {
        exactMatchCount++;
        if(overlaps.length < 10) overlaps.push({ lhName, lhAddr: addrClean });
    } else {
        uniqueLh++;
    }
}

const report = {
    "기존 SH 데이터 (housing_data.json)": {
        "전체 레코드 수": housingData.length,
        "국민임대 수": shKookminCount,
        "공공임대 수": shPublicCount
    },
    "신규 LH 건설형 데이터 (csv)": {
        "전체 레코드 수": lhDataRaw.length,
        "고유 단지 수": lhComplexes.size,
    },
    "비교 결과 (단지 단위)": {
        "단지명 일치": overlappingByName,
        "주소 일치": overlappingByAddress,
        "단지명 또는 주소 일치 (중복으로 추정)": exactMatchCount,
        "완전 신규 단지 (추가해야 할 대상)": uniqueLh
    },
    "중복 추정 샘플 (최대 10개)": overlaps
};

fs.writeFileSync('compare_report.json', JSON.stringify(report, null, 2), 'utf-8');
console.log("Done");
