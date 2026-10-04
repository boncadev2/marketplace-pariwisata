#!/usr/bin/env sh
set -eu

environment=${DEPLOY_ENVIRONMENT:-}
case "$environment" in
    staging|production) ;;
    *) echo "DEPLOY_ENVIRONMENT must be staging or production." >&2; exit 64 ;;
esac

if [ "${APP_DEBUG:-}" != "false" ]; then
    echo "APP_DEBUG=false is mandatory for deployment." >&2
    exit 65
fi

if [ -z "${BACKUP_DIRECTORY:-}" ]; then
    echo "BACKUP_DIRECTORY is required." >&2
    exit 64
fi

for url in "${APP_URL:-}" "${FRONTEND_URL:-}" "${APP_SMOKE_URL:-}"; do
    case "$url" in
        https://*) ;;
        *) echo "APP_URL, FRONTEND_URL and APP_SMOKE_URL must use HTTPS through the deployment TLS endpoint." >&2; exit 65 ;;
    esac
done

if [ "${SESSION_SECURE_COOKIE:-}" != "true" ]; then
    echo "SESSION_SECURE_COOKIE=true is mandatory for deployment." >&2
    exit 65
fi

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"

export APP_ENV="$environment"
export APP_DEBUG=false
export FRONTEND_COMMAND='npm run start'

"$script_dir/backup-local.sh" "$BACKUP_DIRECTORY"
docker compose build backend frontend
docker compose up -d mysql redis mailpit
docker compose run --rm -T backend php artisan migrate --force
docker compose up -d backend
docker compose exec -T backend php artisan config:cache
docker compose exec -T backend php artisan route:cache
docker compose exec -T backend php artisan view:cache
docker compose up -d worker scheduler frontend proxy
docker compose restart worker scheduler

curl --fail --silent --show-error "${APP_SMOKE_URL:?APP_SMOKE_URL is required}/up" >/dev/null
curl --fail --silent --show-error "${APP_SMOKE_URL}/api/v1/health/dependencies" >/dev/null

echo "Deployment completed for $environment"
