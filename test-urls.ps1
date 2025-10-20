# Script de test des URLs avec index.php

Write-Host "🔗 Test des URLs FastDrop avec index.php" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green

$baseUrl = "http://192.168.56.1:8000"
$urls = @(
    "/",
    "/login", 
    "/dashboard",
    "/upload",
    "/files",
    "/admin"
)

Write-Host "`n🧪 Test des redirections automatiques:" -ForegroundColor Yellow
foreach ($url in $urls) {
    $fullUrl = "$baseUrl$url"
    try {
        $response = Invoke-WebRequest -Uri $fullUrl -Method Head -TimeoutSec 5 -MaximumRedirection 0 -ErrorAction Stop
        Write-Host "✅ $url - Status: $($response.StatusCode)" -ForegroundColor Green
    } catch {
        if ($_.Exception.Response.StatusCode -eq 301 -or $_.Exception.Response.StatusCode -eq 302) {
            $location = $_.Exception.Response.Headers.Location
            Write-Host "🔄 $url - Redirige vers: $location" -ForegroundColor Cyan
        } else {
            Write-Host "❌ $url - Erreur: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

Write-Host "`n🧪 Test des URLs directes avec index.php:" -ForegroundColor Yellow
foreach ($url in $urls) {
    $fullUrl = "$baseUrl/index.php$url"
    try {
        $response = Invoke-WebRequest -Uri $fullUrl -Method Head -TimeoutSec 5 -ErrorAction Stop
        Write-Host "✅ /index.php$url - Status: $($response.StatusCode)" -ForegroundColor Green
    } catch {
        Write-Host "❌ /index.php$url - Erreur: $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "`n📋 URLs à utiliser:" -ForegroundColor Green
Write-Host "==================" -ForegroundColor Green
Write-Host "🔐 Connexion: $baseUrl/index.php/login" -ForegroundColor Cyan
Write-Host "📊 Dashboard: $baseUrl/index.php/dashboard" -ForegroundColor Cyan
Write-Host "📤 Upload: $baseUrl/index.php/upload" -ForegroundColor Cyan
Write-Host "📁 Fichiers: $baseUrl/index.php/files" -ForegroundColor Cyan
Write-Host "⚙️ Admin: $baseUrl/index.php/admin" -ForegroundColor Cyan

Write-Host "`n👤 Compte admin: admin@fastdrop.local / admin123" -ForegroundColor Yellow

Write-Host "`n✅ Test terminé!" -ForegroundColor Green
