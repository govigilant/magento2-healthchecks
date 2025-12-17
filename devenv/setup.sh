#!/usr/bin/env bash
set -euo pipefail

umask 0002
export COMPOSER_ALLOW_SUPERUSER=1

MAGENTO_ROOT=${MAGENTO_ROOT:-/var/www/html}
MODULE_PATH="$MAGENTO_ROOT/app/code/Vigilant/MagentoHealthchecks"
HOST_MODULE_PATH=${HOST_MODULE_PATH:-/srv/module}
HOST_BASE_PATH=${HOST_BASE_PATH:-/srv/vigilant-healthchecks-base}

DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-magento}
DB_USER=${DB_USER:-magento}
DB_PASSWORD=${DB_PASSWORD:-magento}

REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}

OPENSEARCH_HOST=${OPENSEARCH_HOST:-opensearch}
OPENSEARCH_PORT=${OPENSEARCH_PORT:-9200}

MAGENTO_VERSION=${MAGENTO_VERSION:-2.4.7-p1}
MAGENTO_BASE_URL=${MAGENTO_BASE_URL:-http://localhost:8000/}
MAGENTO_BASE_URL_SECURE=${MAGENTO_BASE_URL_SECURE:-https://magento.localhost/}
MAGENTO_BACKEND_FRONTNAME=${MAGENTO_BACKEND_FRONTNAME:-admin}
MAGENTO_ADMIN_EMAIL=${MAGENTO_ADMIN_EMAIL:-admin@example.com}
MAGENTO_ADMIN_FIRSTNAME=${MAGENTO_ADMIN_FIRSTNAME:-Admin}
MAGENTO_ADMIN_LASTNAME=${MAGENTO_ADMIN_LASTNAME:-User}
MAGENTO_ADMIN_USER=${MAGENTO_ADMIN_USER:-admin}
MAGENTO_ADMIN_PASSWORD=${MAGENTO_ADMIN_PASSWORD:-Admin123!}
MAGENTO_LANGUAGE=${MAGENTO_LANGUAGE:-en_US}
MAGENTO_CURRENCY=${MAGENTO_CURRENCY:-USD}
MAGENTO_TIMEZONE=${MAGENTO_TIMEZONE:-UTC}

log() {
    printf '[magento-dev] %s\n' "$*"
}

clean_magento_root() {
    if [[ ! -d "$MAGENTO_ROOT" ]]; then
        mkdir -p "$MAGENTO_ROOT"
        return
    fi

    if [[ -z "$(ls -A "$MAGENTO_ROOT" 2>/dev/null)" ]]; then
        return
    fi

    log "Cleaning existing Magento root directory..."
    find "$MAGENTO_ROOT" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
}

ensure_trailing_slash() {
    local value="$1"

    if [[ -n "$value" && "$value" != */ ]]; then
        value+="/"
    fi

    printf '%s' "$value"
}

wait_for_service() {
    local host="$1"
    local port="$2"
    local name="$3"

    for attempt in $(seq 1 60); do
        if nc -z "$host" "$port" >/dev/null 2>&1; then
            log "$name is available."
            return 0
        fi

        if [[ $attempt -eq 60 ]]; then
            log "Timed out waiting for $name on ${host}:${port}"
            exit 1
        fi

        sleep 2
    done
}

ensure_magento_sources() {
    if [[ -f "$MAGENTO_ROOT/bin/magento" ]]; then
        return
    fi

    clean_magento_root

    log "Installing Magento ${MAGENTO_VERSION} via fooman mirror (this may take a few minutes)..."
    composer create-project \
        --no-interaction \
        --no-progress \
        --no-install \
        --repository-url=https://repo-magento-mirror.fooman.co.nz/ \
        "magento/project-community-edition=${MAGENTO_VERSION}" \
        "$MAGENTO_ROOT"
}

ensure_dependencies() {
    if [[ ! -f "$MAGENTO_ROOT/composer.json" ]]; then
        return
    fi

    configure_mirror_repositories

    if composer --working-dir="$MAGENTO_ROOT" show govigilant/magento2-healthchecks >/dev/null 2>&1; then
        return
    fi

    log "Installing Vigilant Magento healthcheck dependencies via Composer..."
    if ! composer require --working-dir="$MAGENTO_ROOT" --no-interaction --no-progress govigilant/magento2-healthchecks:dev-main; then
        log "Composer install failed; Illuminate requires symfony/http-foundation ^7.x but Magento pins 6.4.x."
    fi
}

configure_mirror_repositories() {
    local mirror="https://repo-magento-mirror.fooman.co.nz/"
    local base_path="$HOST_BASE_PATH"
    local module_path="$HOST_MODULE_PATH"

    if [[ ! -f "$MAGENTO_ROOT/composer.json" ]]; then
        return
    fi

    if [[ ! -d "$base_path" ]]; then
        base_path=""
    fi

    if [[ ! -d "$module_path" ]]; then
        module_path=""
    fi

    php -r '$path=$argv[1];$mirror=$argv[2];$module=$argv[3];$base=$argv[4];$data=json_decode(file_get_contents($path), true);if(!is_array($data)) exit(0);$repositories=[];if($module){$repositories[]=["type"=>"path","url"=>$module,"options"=>["symlink"=>true]];}if($base){$repositories[]=["type"=>"path","url"=>$base,"options"=>["symlink"=>true]];}$repositories[]=["type"=>"composer","url"=>$mirror];$data["repositories"]=$repositories;file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");' "$MAGENTO_ROOT/composer.json" "$mirror" "$module_path" "$base_path"
}

install_composer_dependencies() {
    configure_mirror_repositories

    if [[ -d "$MAGENTO_ROOT/vendor" && -f "$MAGENTO_ROOT/vendor/autoload.php" ]]; then
        return
    fi

    log "Installing Magento composer dependencies via mirror..."
    composer install --working-dir="$MAGENTO_ROOT" --no-interaction --no-progress
}

install_magento() {
    wait_for_service "$DB_HOST" "$DB_PORT" "database"
    wait_for_service "$REDIS_HOST" "$REDIS_PORT" "redis"
    wait_for_service "$OPENSEARCH_HOST" "$OPENSEARCH_PORT" "opensearch"

    log "Running Magento setup install..."
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" setup:install \
        --base-url="$MAGENTO_BASE_URL" \
        --base-url-secure="$MAGENTO_BASE_URL_SECURE" \
        --db-host="${DB_HOST}:${DB_PORT}" \
        --db-name="$DB_NAME" \
        --db-user="$DB_USER" \
        --db-password="$DB_PASSWORD" \
        --backend-frontname="$MAGENTO_BACKEND_FRONTNAME" \
        --admin-email="$MAGENTO_ADMIN_EMAIL" \
        --admin-firstname="$MAGENTO_ADMIN_FIRSTNAME" \
        --admin-lastname="$MAGENTO_ADMIN_LASTNAME" \
        --admin-user="$MAGENTO_ADMIN_USER" \
        --admin-password="$MAGENTO_ADMIN_PASSWORD" \
        --language="$MAGENTO_LANGUAGE" \
        --currency="$MAGENTO_CURRENCY" \
        --timezone="$MAGENTO_TIMEZONE" \
        --use-rewrites=1 \
        --use-secure=0 \
        --use-secure-admin=0 \
        --session-save=redis \
        --session-save-redis-host="$REDIS_HOST" \
        --session-save-redis-port="$REDIS_PORT" \
        --cache-backend=redis \
        --cache-backend-redis-server="$REDIS_HOST" \
        --cache-backend-redis-db=0 \
        --page-cache=redis \
        --page-cache-redis-server="$REDIS_HOST" \
        --page-cache-redis-db=1 \
        --search-engine=opensearch \
        --opensearch-host="$OPENSEARCH_HOST" \
        --opensearch-port="$OPENSEARCH_PORT" \
        --opensearch-index-prefix=magento2 \
        --opensearch-timeout=15
}

enable_module() {
    if [[ ! -f "$MAGENTO_ROOT/app/etc/env.php" ]]; then
        return
    fi

    log "Enabling Vigilant Magento healthcheck module..."
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" module:enable Vigilant_MagentoHealthchecks || true
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" setup:upgrade --keep-generated
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" deploy:mode:set developer || true
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" cache:flush || true
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" config:set web/unsecure/base_url "$MAGENTO_BASE_URL" || true
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" config:set web/secure/base_url "$MAGENTO_BASE_URL" || true
}

disable_twofactor() {
    if [[ ! -f "$MAGENTO_ROOT/app/etc/env.php" ]]; then
        return
    fi

    log "Disabling Magento two-factor authentication..."
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth || true
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" cache:flush || true
}

enable_oauth_bearer_tokens() {
    if [[ ! -f "$MAGENTO_ROOT/app/etc/env.php" ]]; then
        return
    fi

    log "Allowing OAuth access tokens as Bearer headers..."
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" config:set oauth/consumer/enable_integration_as_bearer 1 || true
}

set_permissions() {
    if [[ ! -d "$MAGENTO_ROOT" ]]; then
        return
    fi

    log "Setting writable permissions..."
    chown -R www-data:www-data \
        "$MAGENTO_ROOT/var" \
        "$MAGENTO_ROOT/generated" \
        "$MAGENTO_ROOT/pub/static" \
        "$MAGENTO_ROOT/pub/media" \
        "$MAGENTO_ROOT/app/etc" 2>/dev/null || true

    find "$MAGENTO_ROOT/var" "$MAGENTO_ROOT/generated" "$MAGENTO_ROOT/pub/static" "$MAGENTO_ROOT/pub/media" \
        -type d -exec chmod 775 {} + 2>/dev/null || true
    find "$MAGENTO_ROOT/var" "$MAGENTO_ROOT/generated" "$MAGENTO_ROOT/pub/static" "$MAGENTO_ROOT/pub/media" \
        -type f -exec chmod 664 {} + 2>/dev/null || true
}

main() {
    MAGENTO_BASE_URL=$(ensure_trailing_slash "$MAGENTO_BASE_URL")
    MAGENTO_BASE_URL_SECURE=$(ensure_trailing_slash "$MAGENTO_BASE_URL_SECURE")

    ensure_magento_sources
    cd "$MAGENTO_ROOT"

    install_composer_dependencies
    ensure_dependencies

    if [[ ! -f "$MAGENTO_ROOT/app/etc/env.php" ]]; then
        install_magento
    fi

    enable_module
    disable_twofactor
    enable_oauth_bearer_tokens
    set_permissions
}

main "$@"
