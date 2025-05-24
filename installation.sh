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

# Prompt for server IP and SSH port
read -rp "Enter the server IP address to install DockHost: " SERVER_IP
read -rp "Enter the SSH port of the server (default 22): " SSH_PORT
SSH_PORT=${SSH_PORT:-22}  # Default to 22 if input is empty
# Prompt for the remote user with sudo permissions
read -rp "Enter the SSH user with sudo permissions on the server (e.g., ubuntu): " REMOTE_USER
# Prompt for the SSH private key file path
read -rp "Enter the path to your SSH private key on this system (default: ~/.ssh/id_ed25519): " PRIVATE_KEY
PRIVATE_KEY=${PRIVATE_KEY:-~/.ssh/id_ed25519}  # Default if blank
read -rp "Main clients database name for DockHost : " DB_NAME
read -rsp "Main client database password for DockHost: " DB_PASSWORD; echo
read -rp "Enter username for the main filemanager: " USERNAME
read -rsp "Enter password the main filemanager: " PASSWORD; echo
read -rp "Domain name: " DOMAIN
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

mkdir -p docker/clients

# Create log file with parent directories
mkdir -p docker/main/nginxContainer/website/logs
touch docker/main/nginxContainer/website/logs/dockhost_register.log

# Generate docker-compose.yaml
cat > ./docker/main/compose.yaml <<EOF
services:
  nginx_main:
    image: nginx:latest
    container_name: nginx_main
    restart: always
    ports:
      - 80:80
      - 443:443
    volumes:
      - ./nginxContainer/website:/usr/share/nginx/html
      - ./nginxContainer/certs:/etc/nginx/ssl
      - ./nginxContainer/nginxConf/php.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php_main
    networks:
      - main_intranet
      - client_intranet

  php_main:
    build: ./php-fpmConf/
    container_name: php_main
    restart: always
    environment:
      - DOMAIN=${DOMAIN}
      - MYSQL_ROOT_DATABASE=${DB_NAME}
      - MYSQL_ROOT_PASSWORD=${DB_PASSWORD}
    volumes:
      - ./nginxContainer/website:/usr/share/nginx/html
      - ../clients:/clients
    command: sh -c "chown -R www-data:www-data /usr/share/nginx/html /clients && chmod -R 755 /usr/share/nginx/html /clients && php-fpm"
    networks:
      - main_intranet

  mysql_main:
    image: mysql:latest
    container_name: mysql_main
    restart: always
    environment:
      - MYSQL_ROOT_DATABASE=${DB_NAME}
      - MYSQL_ROOT_PASSWORD=${DB_PASSWORD}
    volumes:
      - db:/var/lib/mysql
    networks:
      - main_intranet

  phpmyadmin:
    image: phpmyadmin
    container_name: phpmyadmin_main
    restart: always
    ports:
      - 8080:80
    depends_on:
      - mysql_main
    environment:
      - PMA_HOST=mysql_main
    networks:
      - main_intranet

  filemanager:
    image: filebrowser/filebrowser
    restart: always
    volumes:
      - ./nginxContainer/website:/srv
      - ../clients:/srv/clients
      - ./fileBrowserContainer/.filebrowser.json:/.filebrowser.json
      - db:/db_files
    networks:
      main_intranet:
        aliases:
          - files.${DOMAIN}

volumes:
  db:

networks:
  main_intranet:
    driver: bridge
  client_intranet:mkdir -p docker/main/nginxContainer/certs

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
    driver: bridge
EOF

mkdir -p docker/main/fileBrowserContainer

# Generate bcrypt hash using htpasswd
# -B = bcrypt, -nb = no updating file, just print
BCRYPT_HASH=$(htpasswd -nbB "$USERNAME" "$PASSWORD" | cut -d':' -f2)

cat > docker/main/fileBrowserContainer/.filebrowser.json <<EOF
{
  "port": 80,
  "baseURL": "",
  "address": "",
  "log": "stdout",
  "database": "/database.db",
  "root": "/srv",
  "username": "${USERNAME}",
  "password": "${BCRYPT_HASH}"
}
EOF

echo "Created .filebrowser.json with bcrypt password hash."

fi

CONF_DIR="docker/main/nginxContainer/nginxConf"
CONF_FILE="${CONF_DIR}/php.conf"

# Create necessary directory if it doesn't exist
mkdir -p "$CONF_DIR"

# Write the nginx config with the user-provided domain
cat > "$CONF_FILE" <<EOF
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
        fastcgi_pass php_main:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root/\$fastcgi_script_name;
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

echo "Nginx config written to $CONF_FILE"

# Define inventory file path
INVENTORY_FILE="hosts"

# Write to the inventory file
cat > "$INVENTORY_FILE" <<EOF
[webserver]
web1 ansible_ssh_host=${SERVER_IP} ansible_ssh_port=${SSH_PORT}
EOF

echo "Ansible inventory written to $INVENTORY_FILE"

# Define ansible.cfg path
ANSIBLE_CFG_FILE="ansible.cfg"

# Write the Ansible config
cat > "$ANSIBLE_CFG_FILE" <<EOF
[defaults]
# Archivo de VMs
inventory = hosts
# Usuario por defecto
remote_user = ${REMOTE_USER}
# Ruta del archivo de claves ssh
private_key_file = ${PRIVATE_KEY}
EOF

echo "Ansible configuration written to $ANSIBLE_CFG_FILE"
