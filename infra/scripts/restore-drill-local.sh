#!/usr/bin/env sh
set -eu

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 ABSOLUTE_DATABASE_DUMP" >&2
    exit 64
fi

database_dump=$1
restore_database=wisata_restore_drill

case "$database_dump" in
    /*) ;;
    *) echo "Database dump path must be absolute." >&2; exit 64 ;;
esac

if [ ! -f "$database_dump" ]; then
    echo "Database dump does not exist: $database_dump" >&2
    exit 66
fi

source_database=$(docker compose exec -T mysql sh -c 'printf %s "$MYSQL_DATABASE"')
if [ "$source_database" = "$restore_database" ]; then
    echo "Refusing to use the restore target as the source database." >&2
    exit 65
fi

started_at=$(date +%s)

cleanup() {
    docker compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS wisata_restore_drill"' >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

docker compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS wisata_restore_drill; CREATE DATABASE wisata_restore_drill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" wisata_restore_drill' < "$database_dump"

source_tables=$(docker compose exec -T mysql sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$MYSQL_DATABASE'\'';"')
restored_tables=$(docker compose exec -T mysql sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''wisata_restore_drill'\'';"')
source_migrations=$(docker compose exec -T mysql sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM migrations;"')
restored_migrations=$(docker compose exec -T mysql sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" wisata_restore_drill -e "SELECT COUNT(*) FROM migrations;"')

if [ "$source_tables" != "$restored_tables" ] || [ "$source_migrations" != "$restored_migrations" ]; then
    echo "Restore verification failed: source/restored counts differ." >&2
    exit 1
fi

finished_at=$(date +%s)
echo "Restore verified: tables=$restored_tables migrations=$restored_migrations duration_seconds=$((finished_at - started_at))"
