#!/bin/bash

# FastDrop Deployment Script
# This script sets up the FastDrop file transfer platform

set -e

echo "🚀 FastDrop Deployment Script"
echo "=============================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/fastdrop"
NGINX_CONFIG="/etc/nginx/sites-available/fastdrop"
NGINX_ENABLED="/etc/nginx/sites-enabled/fastdrop"
SYSTEMD_SERVICE="/etc/systemd/system/fastdrop-worker.service"
PHP_FPM_POOL="/etc/php/8.2/fpm/pool.d/fastdrop.conf"

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root"
   exit 1
fi

# Update system packages
print_status "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
print_status "Installing required packages..."
apt install -y \
    nginx \
    php8.2-fpm \
    php8.2-cli \
    php8.2-common \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-gd \
    php8.2-curl \
    php8.2-intl \
    php8.2-bcmath \
    php8.2-pgsql \
    php8.2-redis \
    postgresql \
    postgresql-contrib \
    redis-server \
    certbot \
    python3-certbot-nginx \
    ufw \
    curl \
    wget \
    unzip

# Configure PostgreSQL
print_status "Configuring PostgreSQL..."
sudo -u postgres psql -c "CREATE DATABASE \"fma-bdd\";"
sudo -u postgres psql -c "CREATE USER \"fma-user\" WITH PASSWORD 'secure_password_change_me';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE \"fma-bdd\" TO \"fma-user\";"
sudo -u postgres psql -c "ALTER USER \"fma-user\" CREATEDB;"

print_warning "PostgreSQL configured with default credentials. Please change them in production!"

# Configure Redis
print_status "Configuring Redis..."
systemctl enable redis-server
systemctl start redis-server

# Configure PHP-FPM
print_status "Configuring PHP-FPM..."
cat > $PHP_FPM_POOL << EOF
[fastdrop]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm-fastdrop.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
EOF

# Create project directory
print_status "Creating project directory..."
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# Install Composer if not present
if ! command -v composer &> /dev/null; then
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

# Install Symfony CLI if not present
if ! command -v symfony &> /dev/null; then
    print_status "Installing Symfony CLI..."
    curl -sS https://get.symfony.com/cli/installer | bash
    mv /root/.symfony/bin/symfony /usr/local/bin/symfony
    chmod +x /usr/local/bin/symfony
fi

print_status "Setting up FastDrop application..."
# Note: In a real deployment, you would clone from git or copy files here
# For this script, we assume the application files are already in place

# Set proper permissions
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 777 $PROJECT_DIR/var

# Create storage directory
mkdir -p $PROJECT_DIR/var/storage
chown -R www-data:www-data $PROJECT_DIR/var/storage
chmod -R 755 $PROJECT_DIR/var/storage

# Configure Nginx
print_status "Configuring Nginx..."
cat > $NGINX_CONFIG << EOF
server {
    listen 80;
    server_name fastdrop.example.com;  # Change this to your domain
    root $PROJECT_DIR/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Main location
    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    # PHP-FPM configuration
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.2-fpm-fastdrop.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_param HTTPS \$https if_not_empty;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ \.(env|log|ini)$ {
        deny all;
    }

    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # File upload limits
    client_max_body_size 10G;
    client_body_timeout 300s;
    client_header_timeout 300s;
    
    # Proxy timeouts for large uploads
    proxy_connect_timeout 300s;
    proxy_send_timeout 300s;
    proxy_read_timeout 300s;
}
EOF

# Enable the site
ln -sf $NGINX_CONFIG $NGINX_ENABLED
nginx -t && systemctl reload nginx

# Configure systemd service for background workers
print_status "Configuring systemd service..."
cat > $SYSTEMD_SERVICE << EOF
[Unit]
Description=FastDrop Background Worker
After=network.target redis.service postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/php $PROJECT_DIR/bin/console messenger:consume async --time-limit=3600 --memory-limit=512M
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

# Configure firewall
print_status "Configuring firewall..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Create cron jobs
print_status "Setting up cron jobs..."
cat > /etc/cron.d/fastdrop << EOF
# FastDrop maintenance tasks
# Purge expired files every day at 2 AM
0 2 * * * www-data cd $PROJECT_DIR && php bin/console app:purge-expired-files --days=7

# Clean up audit logs every week on Sunday at 3 AM
0 3 * * 0 www-data cd $PROJECT_DIR && php bin/console app:cleanup-audit-logs --days=365

# Optimize database every month
0 4 1 * * www-data cd $PROJECT_DIR && php bin/console doctrine:query:sql "VACUUM ANALYZE;"
EOF

# Start and enable services
print_status "Starting services..."
systemctl enable php8.2-fpm
systemctl start php8.2-fpm
systemctl enable nginx
systemctl start nginx
systemctl enable postgresql
systemctl start postgresql
systemctl enable redis-server
systemctl start redis-server

# Create SSL certificate (optional)
print_status "SSL Certificate setup..."
echo "To set up SSL certificate, run:"
echo "certbot --nginx -d your-domain.com"

print_success "FastDrop deployment completed!"
echo ""
echo "Next steps:"
echo "1. Update your domain name in $NGINX_CONFIG"
echo "2. Run 'certbot --nginx -d your-domain.com' to enable SSL"
echo "3. Create an admin user: cd $PROJECT_DIR && php bin/console app:create-admin-user"
echo "4. Run database migrations: cd $PROJECT_DIR && php bin/console doctrine:migrations:migrate"
echo "5. Configure your .env file with production settings"
echo "6. Start the background worker: systemctl enable fastdrop-worker && systemctl start fastdrop-worker"
echo ""
echo "Default database credentials:"
echo "Database: fma-bdd"
echo "User: fma-user"
echo "Password: secure_password_change_me"
echo ""
print_warning "Please change the database password and other sensitive settings in production!"
