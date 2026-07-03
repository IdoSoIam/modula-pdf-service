$php = 'C:\xampp\php\php.exe'
$projectRoot = Split-Path -Parent $PSScriptRoot
$publicDir = Join-Path $projectRoot 'public'

& $php -S 127.0.0.1:8092 -t $publicDir
