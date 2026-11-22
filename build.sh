#!/bin/bash
cd /var/www/html/z
printf '@import "tailwindcss";\n' > input.css
./tailwindcss -i ./input.css -o ./static/vendor/css/tailwind.min.css --minify
