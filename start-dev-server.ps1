# Script PowerShell pour démarrer FastDrop avec configuration optimisée

Write-Host "Démarrage du serveur FastDrop avec configuration optimisée..." -ForegroundColor Green

# Configuration PHP optimisée pour l'upload
$phpIniFile = Join-Path $PSScriptRoot "php.ini"

# Vérifier si le fichier php.ini existe
if (-not (Test-Path $phpIniFile)) {
    Write-Host "Création du fichier php.ini..." -ForegroundColor Yellow
    
    $phpConfig = @"
upload_max_filesize = 2G
post_max_size = 2G
max_file_uploads = 20
max_execution_time = 0
max_input_time = -1
memory_limit = 512M
output_buffering = Off
implicit_flush = On
log_errors = On
date.timezone = Europe/Paris
"@
    
    $phpConfig | Out-File -FilePath $phpIniFile -Encoding UTF8
}

Write-Host "Configuration PHP chargée depuis: $phpIniFile" -ForegroundColor Cyan
Write-Host ""
Write-Host "Limites d'upload:" -ForegroundColor Yellow
Write-Host "- Taille max fichier: 2GB" -ForegroundColor White
Write-Host "- Taille max POST: 2GB" -ForegroundColor White
Write-Host "- Chunk size: 1MB" -ForegroundColor White
Write-Host "- Mémoire: 512MB" -ForegroundColor White
Write-Host ""

# Démarrer le serveur
Write-Host "Démarrage du serveur sur http://127.0.0.1:8000" -ForegroundColor Green
php -S 127.0.0.1:8000 -t public/ -c $phpIniFile
