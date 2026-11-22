import os
import glob

replacements = {
    '<script src="https://cdn.tailwindcss.com"></script>': '<link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">',
    'https://cdn.jsdelivr.net/npm/chart.js': '/static/vendor/js/chart.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js': '/static/vendor/js/chart.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2': '/static/vendor/js/chartjs-plugin-datalabels.min.js',
    'https://unpkg.com/@phosphor-icons/web': '/static/vendor/js/phosphor-icons.js',
    'https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js': '/static/vendor/js/xlsx.full.min.js',
    'https://unpkg.com/vis-network/standalone/umd/vis-network.min.js': '/static/vendor/js/vis-network.min.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css': '/static/vendor/css/leaflet.css',
    'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css': '/static/vendor/css/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js': '/static/vendor/js/leaflet.js',
    'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js': '/static/vendor/js/leaflet.js',
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css': '/static/vendor/swiper/swiper-bundle.min.css',
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js': '/static/vendor/swiper/swiper-bundle.min.js'
}

# Directories to search (excluding 1widgets, addons, modules, layouts/root_basic_layout, etc as per plan)
target_dirs = [
    'shm',
    'outpages',
    'stocks',
    'STOCK-GSHEET',
    'gf'
]

# Root files
root_files = ['login.php', 'print_document.php', 'layout.html', 'layout.blade.php']

files_to_process = []
for f in root_files:
    if os.path.exists(f):
        files_to_process.append(f)

for d in target_dirs:
    for root, dirs, files in os.walk(d):
        for file in files:
            if file.endswith('.php') or file.endswith('.html'):
                files_to_process.append(os.path.join(root, file))

# Additional specific layout files
additional = [
    'layouts/social_housing_onepage/layout.html',
    'layouts/social_housing_onepage/layout.blade.php',
    'includes/html_head.php'
]
for f in additional:
    if os.path.exists(f):
        files_to_process.append(f)

for file_path in files_to_process:
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original_content = content
        for k, v in replacements.items():
            content = content.replace(k, v)
            
        if content != original_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated {file_path}")
    except Exception as e:
        print(f"Error processing {file_path}: {e}")

print("Replacement script finished.")
