FROM php@sha256:5249d94b4b00db63a0ed53e6528114926110ab5dcb0d0f07cc30d1ea7dbe747e

RUN set -eux; \
    sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list; \
    sed -i 's|security.debian.org|archive.debian.org|g' /etc/apt/sources.list; \
    sed -i 's|http://archive.debian.org|https://archive.debian.org|g' /etc/apt/sources.list; \
    if ! grep -qE '^[^#]*https://archive\.debian\.org' /etc/apt/sources.list; then \
        sed -i 's|http://|https://|g' /etc/apt/sources.list; \
    fi; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive-debian-only; \
    apt-get update \
    || ( \
        sed -i 's|https://archive.debian.org|http://archive.debian.org|g' /etc/apt/sources.list; \
        apt-get update; \
        apt-get install -y --no-install-recommends ca-certificates apt-transport-https; \
        sed -i 's|http://archive.debian.org|https://archive.debian.org|g' /etc/apt/sources.list; \
        apt-get update \
    ); \
    if grep -E '^[[:space:]]*deb[[:space:]]+http://' /etc/apt/sources.list /etc/apt/sources.list.d/*.list 2>/dev/null; then \
        echo 'FATAL: active apt sources must use https:// (no plain http:// deb lines)' >&2; \
        exit 1; \
    fi; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
