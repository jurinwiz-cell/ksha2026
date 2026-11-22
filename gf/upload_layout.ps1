$files = @(
    "layout.html",
    "info.xml",
    "conf/info.xml",
    "style.css",
    "main.js"
)

$remoteBase = "/var/www/html/z/layouts/social_housing_onepage/"

Write-Host "Ensuring remote directory exists..."
ssh Z-Server "mkdir -p $remoteBase"

foreach ($f in $files) {
    if (Test-Path $f) {
        Write-Host "Uploading $f to $remoteBase..."
        scp $f "Z-Server:$remoteBase$f"
    } else {
        Write-Warning "File $f not found locally."
    }
}

Write-Host "Upload Complete."
