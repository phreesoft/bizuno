# syntax=docker/dockerfile:1.6
#
# Bizuno ERP — official container image
#
# Layout matches the standalone install: Apache document root contains the
# webroot shims (index.php, .htaccess, scripts/, etc.); src/ and vendor/ live
# inside the document root in the same place a `composer install` would put
# them. Following the hardened-install pattern is also supported — see
# build/docker/README.md.
#
# Build args:
#   PHP_VERSION  — base PHP version tag (default 8.2)
#
# Runtime env:
#   See build/docker/entrypoint.sh for the full list. Minimum set for
#   unattended installs:
#     BIZUNO_BIZID    — business ID, 6+ alphanumeric chars
#     BIZUNO_DB_HOST  — database host (typically the linked mariadb service)
#     BIZUNO_DB_NAME  — database name
#     BIZUNO_DB_USER  — database user
#     BIZUNO_DB_PASS  — database password
#
# Without those, the entrypoint leaves portalCFG.php absent and the web
# installer wizard runs on first request — works fine for hand-run dev
# stacks but not great for CI/automation.

ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache AS build

# Apache: enable mod_rewrite (Bizuno's .htaccess uses it for HTTPS redirect
# and per-install URL conventions). Other modules already on by default.
RUN a2enmod rewrite headers

# PHP extensions Bizuno requires. The PHP base image ships with most, but
# pdo_mysql / gd / zip / intl / mbstring / opcache need explicit install.
# Library packages from Debian repos provide the headers ext-install needs.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        git \
        curl; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
        exif; \
    rm -rf /var/lib/apt/lists/*

# Composer — copy in from the official composer image rather than installing
# via curl, simpler and signed-by-construction.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Recommended PHP settings for an ERP. Override by mounting your own
# /usr/local/etc/php/conf.d/zz-bizuno.ini at runtime.
RUN { \
        echo "memory_limit = 256M"; \
        echo "upload_max_filesize = 32M"; \
        echo "post_max_size = 64M"; \
        echo "max_execution_time = 120"; \
        echo "date.timezone = UTC"; \
        echo "opcache.enable = 1"; \
        echo "opcache.memory_consumption = 128"; \
        echo "opcache.max_accelerated_files = 10000"; \
        echo "opcache.validate_timestamps = 1"; \
        echo "opcache.revalidate_freq = 0"; \
    } > /usr/local/etc/php/conf.d/zz-bizuno.ini

# ─── Build the application layer ────────────────────────────────────────────
#
# Build context expectation: this Dockerfile is built from the REPO ROOT
# (not from build/docker/), so that COPY directives can pull src/, scripts/,
# composer.json, etc. directly. The build.sh / CI workflow both invoke docker
# build from the repo root with `-f build/docker/Dockerfile`.
#
# Order matters for layer caching: dependency files first (composer.json /
# composer.lock), then composer install, then the actual app code. That way
# code-only changes don't bust the dep-install layer.

WORKDIR /var/www/html

# composer manifest + lock — gives `composer install` a deterministic graph
COPY composer.json composer.lock ./

# Install production deps. --no-scripts skips post-install hooks (notably
# the myExt prune-vendor.php script that doesn't apply inside a container);
# --no-dev keeps Parsedown and friends out of the image.
RUN composer install \
    --no-dev \
    --no-scripts \
    --optimize-autoloader \
    --no-progress \
    --no-interaction \
    --prefer-dist

# Application source. src/ holds the canonical PHP; webroot shims (index.php,
# .htaccess, favicon.ico, portalCFG-sample.php, scripts/) sit at the document
# root next to it — same layout as the standalone install.
COPY src/                  ./src/
COPY scripts/              ./scripts/
COPY index.php             ./
COPY portalCFG-sample.php  ./
COPY .htaccess             ./
COPY favicon.ico           ./
COPY README.md LICENSE     ./

# BIZUNO_DATA directory — created here so the image ships ready to receive
# uploads/cache/backups. The compose stack mounts a named volume at this
# path so the data survives container replacement.
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R u=rwX,g=rX,o=rX /var/www/html \
    && chmod -R u=rwX,g=rwX,o= /var/www/html/data

# Entrypoint: bridges env-var configuration to portalCFG.php, then hands
# off to the base image's apache2-foreground.
COPY build/docker/entrypoint.sh /usr/local/bin/bizuno-entrypoint
RUN chmod +x /usr/local/bin/bizuno-entrypoint

# Healthcheck — Apache returns 200 (login or installer) on /, even with no DB.
# A 5xx means the bootstrap is broken; 4xx is also actionable.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS -o /dev/null --max-time 4 http://127.0.0.1/ || exit 1

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/bizuno-entrypoint"]
CMD ["apache2-foreground"]

# ─── Image metadata ─────────────────────────────────────────────────────────
ARG VCS_REF
ARG BUILD_DATE
ARG VERSION
LABEL org.opencontainers.image.title="Bizuno ERP" \
      org.opencontainers.image.description="Full-featured open-source ERP / accounting / CRM in PHP" \
      org.opencontainers.image.source="https://github.com/phreesoft/bizuno" \
      org.opencontainers.image.documentation="https://bizuno.com/docs/" \
      org.opencontainers.image.url="https://bizuno.com" \
      org.opencontainers.image.vendor="PhreeSoft, Inc." \
      org.opencontainers.image.licenses="AGPL-3.0-or-later" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.created="${BUILD_DATE}"
