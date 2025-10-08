@echo off
echo Démarrage du serveur FastDrop avec configuration optimisée...

REM Configuration PHP optimisée pour l'upload
set PHP_INI_SCAN_DIR=%CD%
set PHP_INI_FILE=%CD%\php.ini

REM Vérifier si le fichier php.ini existe
if not exist "%PHP_INI_FILE%" (
    echo Fichier php.ini non trouvé. Création...
    echo upload_max_filesize = 2G > %PHP_INI_FILE%
    echo post_max_size = 2G >> %PHP_INI_FILE%
    echo max_file_uploads = 20 >> %PHP_INI_FILE%
    echo max_execution_time = 0 >> %PHP_INI_FILE%
    echo max_input_time = -1 >> %PHP_INI_FILE%
    echo memory_limit = 512M >> %PHP_INI_FILE%
    echo output_buffering = Off >> %PHP_INI_FILE%
    echo implicit_flush = On >> %PHP_INI_FILE%
)

echo Configuration PHP chargée depuis: %PHP_INI_FILE%
echo.
echo Limites d'upload:
echo - Taille max fichier: 2GB
echo - Taille max POST: 2GB
echo - Chunk size: 1MB
echo - Mémoire: 512MB
echo.

REM Démarrer le serveur
php -S 127.0.0.1:8000 -t public/ -c "%PHP_INI_FILE%"

pause
