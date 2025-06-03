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
# Database paramenters for the clients database
read -rp "Main clients database name for DockHost: " DB_NAME
read -rsp "Main client database password for DockHost: " DB_PASSWORD; echo
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
      - ./nginxContainer/nginxConf/php.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php
    networks:
      intranet:
      client_intranet:
  php:
    build: ./php-fpmConf/
    restart: always
    environment:
      - DOMAIN=${DOMAIN}
      - DATABASE_NAME=${DB_NAME}
      - DATABASE_ROOT_PASSWORD=${DB_PASSWORD}
    volumes:
      - ./nginxContainer/website:/usr/share/nginx/html
      - ../clients:/clients
    command: sh -c "chown -R www-data:www-data /usr/share/nginx/html /clients && chmod -R 755 /usr/share/nginx/html /clients && php-fpm"
    networks:
      intranet:
  mariadb:
    image: mariadb:11.8.1-ubi9-rc
    restart: always
    environment:
      - MARIADB_DATABASE=${DB_NAME}
      - MARIADB_ROOT_PASSWORD=${DB_PASSWORD}
    volumes:
      - db:/var/lib/mysql
    networks:
      intranet:
  phpmyadmin:
    image: phpmyadmin
    restart: always
    depends_on:
      - mariadb
    environment:
      - PMA_HOST=mariadb
      - PMA_ABSOLUTE_URI=https://${DOMAIN}/phpmyadmin/
    networks:
      intranet:
  filebrowser:
    image: filebrowser/filebrowser
    restart: always
    volumes:
      - ./nginxContainer/website:/srv
      - ../clients:/srv/clients
      - ./fileBrowserContainer/.filebrowser.json:/.filebrowser.json:ro
      - db:/srv/db_files:ro
    networks:
      intranet:
volumes:
  db:
networks:
  intranet:
    driver: bridge
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
    location ^~ /phpmyadmin/ {
        proxy_pass http://phpmyadmin/;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_redirect off;
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

# Write the Ansible config
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

cat > autoDockerUp/docker-compose-watch.sh <<EOF
#!/bin/bash

WATCH_DIR="/docker/clients"
LOG_FILE="/docker/main/nginxContainer/website/logs/dockhost_dockerinfo.log"
MAX_RETRIES=6
SLEEP_INTERVAL=10
inotifywait -m -e create --format "%f"Let me know if you want to define static IPs or isolate containers further (e.g., VLAN-style segmentation or proxy-only access). "\$WATCH_DIR" | while read NEW_ENTRY; do
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
                    mkdir -p ./website
                    mkdir -p ./admin
                    cat > ./admin/index.html <<HTML
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard</title>
    <style>
      :root {
        --bg-color: #121212;
        --text-color: #f1f1f1;
        --card-bg: #1e1e1e;
        --border-radius: 8px;
        --font: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        background-color: var(--bg-color);
        color: var(--text-color);
        font-family: var(--font);
        display: flex;
        flex-direction: column;
        height: 100vh;
      }
      header {
        text-align: center;
        padding: 1rem;
        background-color: var(--card-bg);
        color: var(--text-color);
        font-size: 1.5rem;
      }
      .iframe-container {
        flex: 1;
        display: flex;
        gap: 1rem;
        padding: 1rem;
      }

      iframe {
        flex: 1;
        height: 100%;
        border: none;
        border-radius: var(--border-radius);
        background-color: var(--card-bg);
      }
    </style>
  </head>
  <body>
    <header>Dashboard - \$safeUsername</header>
    <div class="iframe-container">
      <iframe src="https://\$safeUsername.${DOMAIN}/admin/files/" title="FILES"></iframe>
      <div>
        <h3>MariaDB Database connection parameters for PHP</h3>
        <p>User: root</p>
        <p>Password: the password set when registering</p>
        <p>Server: mariadb\$safeUsername</p>
        <p>Port: 3306</p>
        <p>Keep in mind that you cannot connect to the database ouside the website hosted in DockHost</p>
        <h4>PHPMyAdmin</h4>
        <p>Use the credentials for the database</p>
        <a href="https://\$safeUsername.${DOMAIN}/admin/phpmyadmin/">PHPMyAdmin panel</a>
      </div>
      
    </div>
  </body>
  </html>
HTML
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
