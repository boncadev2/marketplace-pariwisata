#!/usr/bin/env sh
set -eu

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 ABSOLUTE_BACKUP_DIRECTORY" >&2
    exit 64
fi

backup_dir=$1
case "$backup_dir" in
    /*) ;;
    *) echo "Backup directory must be absolute." >&2; exit 64 ;;
esac

mkdir -p "$backup_dir"
chmod 700 "$backup_dir"

database_dump="$backup_dir/database.sql"
media_archive="$backup_dir/private-media.tar.gz"

docker compose exec -T mysql sh -c 'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > "$database_dump"
tar -C backend/storage/app -czf "$media_archive" private
chmod 600 "$database_dump" "$media_archive"
shasum -a 256 "$database_dump" "$media_archive" > "$backup_dir/SHA256SUMS"
chmod 600 "$backup_dir/SHA256SUMS"

echo "Backup created in $backup_dir"
