$replacements = @{
    '<script src="https://cdn.tailwindcss.com"></script>' = '<link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">';
    'https://cdn.jsdelivr.net/npm/chart.js' = '/static/vendor/js/chart.min.js';
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js' = '/static/vendor/js/chart.min.js';
    'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2' = '/static/vendor/js/chartjs-plugin-datalabels.min.js';
    'https://unpkg.com/@phosphor-icons/web' = '/static/vendor/js/phosphor-icons.js';
    'https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js' = '/static/vendor/js/xlsx.full.min.js';
    'https://unpkg.com/vis-network/standalone/umd/vis-network.min.js' = '/static/vendor/js/vis-network.min.js';
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' = '/static/vendor/css/leaflet.css';
    'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css' = '/static/vendor/css/leaflet.css';
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js' = '/static/vendor/js/leaflet.js';
    'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js' = '/static/vendor/js/leaflet.js';
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css' = '/static/vendor/swiper/swiper-bundle.min.css';
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js' = '/static/vendor/swiper/swiper-bundle.min.js'
}

$targetDirs = @("shm", "outpages", "stocks", "STOCK-GSHEET", "gf")
$rootFiles = @("login.php", "print_document.php", "layout.html", "layout.blade.php", "layouts\social_housing_onepage\layout.html", "layouts\social_housing_onepage\layout.blade.php", "includes\html_head.php")

$filesToProcess = @()
foreach ($f in $rootFiles) {
    if (Test-Path $f) {
        $filesToProcess += $f
    }
}

foreach ($d in $targetDirs) {
    if (Test-Path $d) {
        $files = Get-ChildItem -Path $d -Recurse -Include *.php, *.html -File
        foreach ($f in $files) {
            $filesToProcess += $f.FullName
        }
    }
}

foreach ($file in $filesToProcess) {
    $content = Get-Content -Path $file -Raw -Encoding UTF8
    if ($null -ne $content) {
        $original = $content
        foreach ($key in $replacements.Keys) {
            # Use escape to ensure strings are treated literally, not as regex
            $content = $content -replace [regex]::Escape($key), $replacements[$key]
        }
        if ($content -cne $original) {
            Set-Content -Path $file -Value $content -Encoding UTF8
            Write-Host "Updated $file"
        }
    }
}
Write-Host "Replacement complete."
