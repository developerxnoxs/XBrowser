FROM php:8.4-cli-bookworm

LABEL maintainer="Xbrowser Contributors <contributors@xbrowser.dev>"
LABEL description="Xbrowser — terminal browser & automation library with bundled Chromium"

# ── System dependencies ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Chromium dan dependencies-nya
    chromium \
    chromium-sandbox \
    # Font agar rendering halaman tidak rusak
    fonts-liberation \
    fonts-noto \
    fonts-noto-cjk \
    # Tools umum
    curl \
    git \
    unzip \
    ca-certificates \
    # Shared libs yang dibutuhkan Chromium headless
    libglib2.0-0 \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Xbrowser sandbox setup ────────────────────────────────────────────────────
# Buat user non-root — Chromium tidak bisa jalan sebagai root tanpa --no-sandbox
RUN groupadd -r xbrowser && useradd -r -g xbrowser -d /app -s /bin/bash xbrowser

WORKDIR /app

# ── Install dependencies (layer terpisah agar cache efisien) ──────────────────
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# ── Copy source ───────────────────────────────────────────────────────────────
COPY . .

# Set permission
RUN chown -R xbrowser:xbrowser /app

# ── Environment ───────────────────────────────────────────────────────────────
ENV XBROWSER_CHROMIUM=/usr/bin/chromium
ENV XBROWSER_NO_SANDBOX=true
ENV XBROWSER_HEADLESS=true
ENV XBROWSER_TIMEOUT=30000

# ── Default user ─────────────────────────────────────────────────────────────
USER xbrowser

# ── Entrypoint ────────────────────────────────────────────────────────────────
ENTRYPOINT ["php", "bin/Xbrowser"]
CMD ["--help"]
