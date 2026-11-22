<?php
$replacements = [
    '<script src="https://cdn.tailwindcss.com"></script>' => '<link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">',
    'https://cdn.jsdelivr.net/npm/chart.js' => '/static/vendor/js/chart.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js' => '/static/vendor/js/chart.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2' => '/static/vendor/js/chartjs-plugin-datalabels.min.js',
    'https://unpkg.com/@phosphor-icons/web' => '/static/vendor/js/phosphor-icons.js',
    'https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js' => '/static/vendor/js/xlsx.full.min.js',
    'https://unpkg.com/vis-network/standalone/umd/vis-network.min.js' => '/static/vendor/js/vis-network.min.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' => '/static/vendor/css/leaflet.css',
    'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css' => '/static/vendor/css/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js' => '/static/vendor/js/leaflet.js',
    'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js' => '/static/vendor/js/leaflet.js',
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css' => '/static/vendor/swiper/swiper-bundle.min.css',
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js' => '/static/vendor/swiper/swiper-bundle.min.js'
];

$target_dirs = ['shm', 'outpages', 'stocks', 'STOCK-GSHEET', 'gf'];
$root_files = ['login.php', 'print_document.php', 'layout.html', 'layout.blade.php', 'layouts/social_housing_onepage/layout.html', 'layouts/social_housing_onepage/layout.blade.php', 'includes/html_head.php'];

$files_to_process = [];
foreach ($root_files as $f) {
    if (file_exists($f)) $files_to_process[] = $f;
}

foreach ($target_dirs as $d) {
    if (!is_dir($d)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
            if ($ext === 'php' || $ext === 'html') {
                $files_to_process[] = $file->getPathname();
            }
        }
    }
}

foreach ($files_to_process as $file_path) {
    $content = file_get_contents($file_path);
    if ($content === false) continue;
    
    $original_content = $content;
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $original_content) {
        file_put_contents($file_path, $content);
        echo "Updated $file_path\n";
    }
}
echo "Done.\n";
