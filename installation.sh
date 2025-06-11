#!/bin/bash

set -e

if ! command -v ansible >/dev/null 2>&1; then
  echo "Error: ansible command not found. Please install Ansible before running this script." >&2
  exit 1
fi

# Check if community.docker collection is installed
if ! ansible-galaxy collection list community.docker >/dev/null 2>&1; then
  echo "Error: community.docker collection is not installed."
  echo "Install it by running: ansible-galaxy collection install community.docker"
  exit 1
fi

echo "Ansible and community.docker collection are installed."

# Check if htpasswd is installed
if ! command -v htpasswd >/dev/null 2>&1; then
  echo "Error: htpasswd command not found. Please install it (e.g., 'sudo apt install apache2-utils')." >&2
  exit 1
fi

echo "htpasswd is installed"

# Prompt for the server IP and SSH port 
read -rp "Enter the server IP address to install DockHost: " SERVER_IP
read -rp "Enter the SSH port of the server (default 22): " SSH_PORT
SSH_PORT=${SSH_PORT:-22}  # Default to 22 if input is empty
# Prompt for the server remote user with sudo permissions
read -rp "Enter the SSH user with sudo permissions on the server (e.g., ubuntu): " REMOTE_USER
# Prompt for the SSH private key file path (not required but recommended)
read -rp "Enter the path to your SSH private key on this system (default: ~/.ssh/id_ed25519): " PRIVATE_KEY
PRIVATE_KEY=${PRIVATE_KEY:-~/.ssh/id_ed25519}  # Default if blank
# Database parameters for the clients database (for mariadb/mysql)
#read -rp "Main clients database name for DockHost: " DB_NAME
#read -rsp "Main client database password for DockHost: " DB_PASSWORD; echo
# Filebrowser parameters for accesing all the app files and the clients' too
read -rp "Enter username for the main filemanager: " USERNAME
read -rsp "Enter password the main filemanager: " PASSWORD; echo
#Domain settings for the project
read -rp "Domain name (e.g example.com): " DOMAIN
read -rp "Do you have an SSL certificate and private key for your domain? [y/N]: " HAS_CERT

mkdir -p docker/main/nginxContainer/certs

if [[ "$HAS_CERT" =~ ^[Yy]$ ]]; then
    read -rp "Enter the full path to your SSL certificate (e.g. /path/to/cert.pem): " CERT_PATH
    read -rp "Enter the full path to your private key (e.g. /path/to/privkey.pem): " KEY_PATH

    if [[ ! -f "$CERT_PATH" ]]; then
        echo "Error: Certificate file not found at $CERT_PATH" >&2
        exit 1
    fi

    if [[ ! -f "$KEY_PATH" ]]; then
        echo "Error: Private key file not found at $KEY_PATH" >&2
        exit 1
    fi

    echo "Certificate and private key files found. Proceeding..."
    # Optionally copy to destination
    mkdir -p docker/main/nginxContainer/certs
    cp "$CERT_PATH" docker/main/nginxContainer/certs/fullchain.cer
    cp "$KEY_PATH" docker/main/nginxContainer/certs/privkey.key
else
    echo "Warning: No SSL certificate provided. You must configure Let's Encrypt or another provider manually."
fi

mkdir -p docker/clients

# Create log file with parent directories
mkdir -p docker/main/nginxContainer/website/logs
touch docker/main/nginxContainer/website/logs/dockhost_register.log

# Generate docker-compose.yaml
cat > docker/main/compose.yaml <<EOF
services:
  nginx:
    image: nginx:stable-alpine
    restart: always
    ports:
      - 80:80
      - 443:443
    volumes:
      - ./nginxContainer/website:/usr/share/nginx/html:ro
      - ./nginxContainer/certs:/etc/nginx/ssl:ro
      - ./nginxContainer/nginxConf/php.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      default:
      client_intranet:
  php:
    build: ./php-fpmConf/
    restart: always
    environment:
      - DOMAIN=${DOMAIN}
      - SQLITE_DB_FILE=/db/clients.sqlite
    volumes:
      - ./nginxContainer/website:/usr/share/nginx/html
      - ../clients:/clients
      - db:/db
    command: >
      sh -c "touch /db/clients.sqlite && chown -R www-data:www-data /usr/share/nginx/html /clients /db && chmod -R 755 /usr/share/nginx/html /clients /db && php-fpm"
  filebrowser:
    image: filebrowser/filebrowser
    restart: always
    volumes:
      - ./fileBrowserContainer/.filebrowser.json:/.filebrowser.json:ro
      - ./nginxContainer/website:/srv/website
      - ../clients:/srv/clients
      - db:/srv/db_files:ro
volumes:
  db:
networks:
  client_intranet:
EOF

mkdir -p docker/main/fileBrowserContainer

# Generate bcrypt hash using htpasswd
# -B = bcrypt, -nb = no updating file, just print
BCRYPT_HASH=$(htpasswd -nbB "$USERNAME" "$PASSWORD" | cut -d':' -f2)

cat > docker/main/fileBrowserContainer/.filebrowser.json <<EOF
{
  "port": 80,
  "baseURL": "/files",
  "address": "",
  "log": "stdout",
  "database": "/database.db",
  "root": "/srv",
  "username": "${USERNAME}",
  "password": "${BCRYPT_HASH}"
}
EOF

echo "Created .filebrowser.json with bcrypt password hash."

# Create necessary directory if it mysqldoesn't exist
mkdir -p docker/main/nginxContainer/nginxConf

# Write the nginx config with the user-provided domain
cat > docker/main/nginxContainer/nginxConf/php.conf <<EOF
server {
    listen 443 ssl;
    server_name ${DOMAIN};
    ssl_certificate /etc/nginx/ssl/fullchain.cer;
    ssl_certificate_key /etc/nginx/ssl/privkey.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    client_max_body_size 100M;
    root /usr/share/nginx/html/public;
    location / {
        index index.php;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root/\$fastcgi_script_name;
    }
    location ^~ /files/ {
        proxy_pass http://filebrowser/;
    }
}
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl;
    server_name *.${DOMAIN};
    resolver 127.0.0.11;
    location / {
        proxy_pass http://\$host\$request_uri;
    }
}
EOF

echo "Nginx config written to docker/main/nginxContainer/nginxConf/php.conf"

# Write to the inventory file
cat > hosts <<EOF
[webserver]
web1 ansible_ssh_host=${SERVER_IP} ansible_ssh_port=${SSH_PORT}
EOF
echo "Ansible inventory written to hosts"

cat > ansible.cfg <<EOF
[defaults]
# Archivo de VMs
inventory = hosts
# Usuario por defecto
remote_user = ${REMOTE_USER}
# Ruta del archivo de claves ssh
private_key_file = ${PRIVATE_KEY}
EOF

echo "Ansible configuration written to ansible.cfg"

# Write the Ansible config
cat > autoDockerUp/docker-compose-watch.sh <<EOF
#!/bin/bash
WATCH_DIR="/docker/clients"
LOG_FILE="/docker/main/nginxContainer/website/logs/dockhost_dockerinfo.log"
MAX_RETRIES=6
SLEEP_INTERVAL=10
inotifywait -m -e create --format "%f" "\$WATCH_DIR" | while read NEW_ENTRY; do
    NEW_PATH="\$WATCH_DIR/\$NEW_ENTRY"
    if [ -d "\$NEW_PATH" ]; then
        echo "\$(date) - Detected new directory: \$NEW_PATH" >> "\$LOG_FILE"
        ATTEMPT=1
        while [ \$ATTEMPT -le \$MAX_RETRIES ]; do
            if [ -f "\$NEW_PATH/compose.yaml" ] || [ -f "\$NEW_PATH/docker-compose.yml" ]; then
                echo "\$(date) - Found compose file in \$NEW_PATH (attempt \$ATTEMPT)" >> "\$LOG_FILE"
                (
                    cd "\$NEW_PATH"
                    safeUsername=\$(basename "\$NEW_PATH" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
                    export COMPOSE_BAKE=true
                    docker compose up -d >> "\$LOG_FILE" 2>&1
                    echo "\$(date) - Client \$safeUsername READY" >> "\$LOG_FILE"
                )
                break
            else
                echo "\$(date) - Compose file not found in \$NEW_PATH (attempt \$ATTEMPT)" >> "\$LOG_FILE"
                sleep "\$SLEEP_INTERVAL"
                ((ATTEMPT++))
            fi
        done
        if [ \$ATTEMPT -gt \$MAX_RETRIES ]; then
            echo "\$(date) - Failed to find compose file in \$NEW_PATH after \$MAX_RETRIES attempts" >> "\$LOG_FILE"
        fi
    fi
done
EOF