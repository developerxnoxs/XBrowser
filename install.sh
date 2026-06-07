#!/usr/bin/env bash
# =============================================================================
# Xbrowser — Universal Installer
# Mendukung: Termux (Android), Ubuntu/Debian, Fedora/RHEL, Arch Linux,
#            Alpine Linux, macOS (Homebrew), NixOS, Docker/CI
# Cara pakai:
#   bash install.sh           # install standar
#   bash install.sh --dir /opt/xbrowser   # install ke direktori kustom
#   bash install.sh --no-chromium         # skip install Chromium
#   bash install.sh --user                # install ke ~/.local/bin (tanpa sudo)
# =============================================================================

set -euo pipefail

# ── Warna output ──────────────────────────────────────────────────────────────
if [ -t 1 ]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
    CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'
else
    RED=''; GREEN=''; YELLOW=''; CYAN=''; BOLD=''; RESET=''
fi

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
ok()      { echo -e "${GREEN}[  OK ]${RESET}  $*"; }
warn()    { echo -e "${YELLOW}[ WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET}  $*" >&2; }
header()  { echo -e "\n${BOLD}$*${RESET}"; echo "$(printf '─%.0s' {1..60})"; }
die()     { error "$*"; exit 1; }

# ── Argumen ───────────────────────────────────────────────────────────────────
INSTALL_DIR=""
SKIP_CHROMIUM=false
USER_INSTALL=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dir)        INSTALL_DIR="$2"; shift 2 ;;
        --no-chromium) SKIP_CHROMIUM=true; shift ;;
        --user)       USER_INSTALL=true; shift ;;
        --help|-h)
            echo "Cara pakai: bash install.sh [opsi]"
            echo "  --dir <path>     Direktori install kustom"
            echo "  --no-chromium    Skip install Chromium"
            echo "  --user           Install ke ~/.local/bin (tanpa sudo)"
            exit 0 ;;
        *) warn "Opsi tidak dikenal: $1"; shift ;;
    esac
done

# ── Deteksi environment ───────────────────────────────────────────────────────
detect_env() {
    if [ -n "${TERMUX_VERSION:-}" ] || [ -d "/data/data/com.termux" ]; then
        echo "termux"
    elif [ -f /etc/alpine-release ]; then
        echo "alpine"
    elif [ -f /etc/arch-release ]; then
        echo "arch"
    elif [ -f /etc/fedora-release ] || [ -f /etc/redhat-release ]; then
        echo "fedora"
    elif [ -f /etc/debian_version ] || grep -qi debian /etc/os-release 2>/dev/null || grep -qi ubuntu /etc/os-release 2>/dev/null; then
        echo "debian"
    elif [ "$(uname)" = "Darwin" ]; then
        echo "macos"
    elif [ -f /etc/nixos/configuration.nix ] || command -v nix-env &>/dev/null; then
        echo "nixos"
    else
        echo "linux"
    fi
}

ENV=$(detect_env)
info "Environment terdeteksi: ${BOLD}${ENV}${RESET}"

# ── Tentukan PATH global ───────────────────────────────────────────────────────
if [ "$ENV" = "termux" ]; then
    BIN_DIR="${PREFIX}/bin"
    INSTALL_DIR="${INSTALL_DIR:-${PREFIX}/opt/xbrowser}"
    CAN_SUDO=false
elif [ "$USER_INSTALL" = true ]; then
    BIN_DIR="${HOME}/.local/bin"
    INSTALL_DIR="${INSTALL_DIR:-${HOME}/.local/share/xbrowser}"
    CAN_SUDO=false
elif [ "$(id -u)" = "0" ]; then
    BIN_DIR="/usr/local/bin"
    INSTALL_DIR="${INSTALL_DIR:-/opt/xbrowser}"
    CAN_SUDO=false  # sudah root
else
    BIN_DIR="/usr/local/bin"
    INSTALL_DIR="${INSTALL_DIR:-/opt/xbrowser}"
    CAN_SUDO=true
fi

info "Akan install ke: ${BOLD}${INSTALL_DIR}${RESET}"
info "Symlink di:      ${BOLD}${BIN_DIR}/Xbrowser${RESET}"

# ── Helper: jalankan dengan/tanpa sudo ────────────────────────────────────────
run() {
    if [ "$CAN_SUDO" = true ]; then
        sudo "$@"
    else
        "$@"
    fi
}

# ── 1. Cek PHP 8.4+ ───────────────────────────────────────────────────────────
header "1/5  PHP 8.4+"

install_php() {
    info "Menginstall PHP 8.4..."
    case "$ENV" in
        termux)
            pkg update -y && pkg install -y php
            ;;
        debian)
            # Coba install lewat ondrej/php PPA untuk PHP 8.4
            run apt-get update -qq
            if ! run apt-get install -y php8.4-cli php8.4-mbstring php8.4-curl 2>/dev/null; then
                warn "PHP 8.4 tidak tersedia langsung. Mencoba via PPA ondrej/php..."
                run apt-get install -y software-properties-common lsb-release ca-certificates apt-transport-https
                run add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
                run apt-get update -qq
                run apt-get install -y php8.4-cli php8.4-mbstring php8.4-curl
            fi
            ;;
        fedora)
            run dnf install -y php-cli php-mbstring php-curl
            ;;
        arch)
            run pacman -Sy --noconfirm php
            ;;
        alpine)
            run apk add --no-cache php84 php84-mbstring php84-curl
            # Buat alias php → php84 jika belum ada
            if ! command -v php &>/dev/null; then
                run ln -sf /usr/bin/php84 /usr/local/bin/php
            fi
            ;;
        macos)
            if command -v brew &>/dev/null; then
                brew install php
            else
                die "Homebrew tidak ditemukan. Install dari https://brew.sh dulu."
            fi
            ;;
        nixos)
            warn "NixOS: tambahkan 'php84' ke environment.systemPackages atau pakai 'nix-env -iA nixpkgs.php84'"
            die "Install PHP 8.4 dulu, lalu jalankan ulang install.sh"
            ;;
        *)
            die "Tidak bisa install PHP otomatis untuk $ENV. Install PHP 8.4+ secara manual."
            ;;
    esac
}

if command -v php &>/dev/null; then
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
    PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
    PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
    if [ "$PHP_MAJOR" -ge 8 ] && [ "$PHP_MINOR" -ge 4 ]; then
        ok "PHP $PHP_VER sudah tersedia"
    else
        warn "PHP $PHP_VER ditemukan tapi butuh 8.4+. Menginstall versi baru..."
        install_php
    fi
else
    install_php
fi

# Verifikasi ulang
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null) || die "PHP gagal diinstall."
ok "PHP $PHP_VER siap"

# ── 2. Cek Composer ───────────────────────────────────────────────────────────
header "2/5  Composer"

install_composer() {
    info "Menginstall Composer..."
    EXPECTED_SIG="$(curl -fsSL https://composer.github.io/installer.sig 2>/dev/null || true)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php

    if [ -n "$EXPECTED_SIG" ]; then
        ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
        [ "$EXPECTED_SIG" = "$ACTUAL_SIG" ] || die "Installer Composer corrupt! Checksum tidak cocok."
    fi

    php /tmp/composer-setup.php --quiet --install-dir=/tmp --filename=composer
    rm -f /tmp/composer-setup.php

    # Pindahkan ke PATH
    if [ "$ENV" = "termux" ]; then
        mv /tmp/composer "${PREFIX}/bin/composer"
        chmod +x "${PREFIX}/bin/composer"
    elif [ "$USER_INSTALL" = true ]; then
        mkdir -p "${HOME}/.local/bin"
        mv /tmp/composer "${HOME}/.local/bin/composer"
        chmod +x "${HOME}/.local/bin/composer"
    else
        run mv /tmp/composer /usr/local/bin/composer
        run chmod +x /usr/local/bin/composer
    fi
}

if command -v composer &>/dev/null; then
    ok "Composer sudah tersedia ($(composer --version --no-ansi 2>/dev/null | head -1))"
else
    install_composer
    ok "Composer berhasil diinstall"
fi

# ── 3. Install Chromium ───────────────────────────────────────────────────────
header "3/5  Chromium"

CHROMIUM_INSTALLED=false

detect_chromium() {
    for cmd in chromium chromium-browser google-chrome google-chrome-stable; do
        if command -v "$cmd" &>/dev/null; then
            echo "$cmd"
            return
        fi
    done
    # Termux path khusus
    [ -f "${PREFIX}/bin/chromium" ] && echo "${PREFIX}/bin/chromium" && return
    # macOS app bundle
    [ -f "/Applications/Chromium.app/Contents/MacOS/Chromium" ] && echo "/Applications/Chromium.app/Contents/MacOS/Chromium" && return
    echo ""
}

if [ "$SKIP_CHROMIUM" = true ]; then
    warn "Skip install Chromium (--no-chromium). Pastikan Chromium sudah ada di PATH."
    CHROMIUM_INSTALLED=true
else
    CHROMIUM_CMD=$(detect_chromium)
    if [ -n "$CHROMIUM_CMD" ]; then
        ok "Chromium sudah tersedia: $CHROMIUM_CMD"
        CHROMIUM_INSTALLED=true
    else
        info "Chromium tidak ditemukan. Menginstall..."
        case "$ENV" in
            termux)
                pkg install -y chromium
                ;;
            debian)
                run apt-get update -qq
                run apt-get install -y chromium || run apt-get install -y chromium-browser || \
                    warn "Chromium tidak tersedia di repo. Install manual: sudo apt install chromium"
                ;;
            fedora)
                run dnf install -y chromium || warn "Chromium tidak tersedia. Install manual: sudo dnf install chromium"
                ;;
            arch)
                run pacman -Sy --noconfirm chromium
                ;;
            alpine)
                run apk add --no-cache chromium
                ;;
            macos)
                if command -v brew &>/dev/null; then
                    brew install --cask chromium || brew install --cask google-chrome
                else
                    warn "Install Chromium manual dari https://www.chromium.org/getting-involved/download-chromium"
                fi
                ;;
            nixos)
                warn "NixOS: tambahkan 'chromium' ke environment.systemPackages atau pakai 'nix-env -iA nixpkgs.chromium'"
                ;;
            *)
                warn "Tidak bisa install Chromium otomatis. Install manual sesuai OS kamu."
                ;;
        esac
        CHROMIUM_CMD=$(detect_chromium)
        [ -n "$CHROMIUM_CMD" ] && ok "Chromium berhasil diinstall: $CHROMIUM_CMD" && CHROMIUM_INSTALLED=true || \
            warn "Chromium belum ditemukan di PATH. Set XBROWSER_CHROMIUM=/path/to/chromium jika perlu."
    fi
fi

# ── 4. Install Xbrowser ───────────────────────────────────────────────────────
header "4/5  Install Xbrowser"

# Tentukan sumber — apakah kita sudah di dalam repo, atau perlu clone
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "${SCRIPT_DIR}/composer.json" ] && grep -q '"xbrowser/xbrowser"' "${SCRIPT_DIR}/composer.json" 2>/dev/null; then
    SRC_DIR="$SCRIPT_DIR"
    info "Menggunakan sumber lokal: $SRC_DIR"
else
    # Clone dari remote
    if ! command -v git &>/dev/null; then
        case "$ENV" in
            termux)  pkg install -y git ;;
            debian)  run apt-get install -y git ;;
            fedora)  run dnf install -y git ;;
            arch)    run pacman -Sy --noconfirm git ;;
            alpine)  run apk add --no-cache git ;;
            macos)   brew install git ;;
            *)       die "git tidak ditemukan. Install git terlebih dahulu." ;;
        esac
    fi
    info "Cloning Xbrowser ke ${INSTALL_DIR}..."
    if [ -d "$INSTALL_DIR" ]; then
        warn "Direktori $INSTALL_DIR sudah ada — update via git pull..."
        git -C "$INSTALL_DIR" pull --ff-only 2>/dev/null || true
    else
        run mkdir -p "$(dirname "$INSTALL_DIR")"
        git clone https://github.com/your-org/xbrowser.git "$INSTALL_DIR"
    fi
    SRC_DIR="$INSTALL_DIR"
fi

# Install ke INSTALL_DIR jika berbeda dari SRC_DIR
if [ "$SRC_DIR" != "$INSTALL_DIR" ]; then
    info "Menyalin file ke $INSTALL_DIR..."
    run mkdir -p "$INSTALL_DIR"
    run cp -r "${SRC_DIR}/." "$INSTALL_DIR/"
fi

# Jalankan composer install
info "Menjalankan composer install..."
(cd "$INSTALL_DIR" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)
ok "Dependencies terinstall"

# Fix permission bin
chmod +x "${INSTALL_DIR}/bin/Xbrowser"

# ── 5. Daftarkan ke PATH ──────────────────────────────────────────────────────
header "5/5  Daftarkan ke PATH"

mkdir -p "$BIN_DIR"

LINK_TARGET="${BIN_DIR}/Xbrowser"
REAL_TARGET="${INSTALL_DIR}/bin/Xbrowser"

# Hapus symlink lama jika ada
[ -L "$LINK_TARGET" ] && rm -f "$LINK_TARGET"
[ -f "$LINK_TARGET" ] && run rm -f "$LINK_TARGET"

# Buat symlink
if [ "$CAN_SUDO" = true ] && [ "$BIN_DIR" = "/usr/local/bin" ]; then
    sudo ln -sf "$REAL_TARGET" "$LINK_TARGET"
else
    ln -sf "$REAL_TARGET" "$LINK_TARGET"
fi

ok "Symlink dibuat: $LINK_TARGET → $REAL_TARGET"

# Pastikan BIN_DIR ada di PATH
ensure_in_path() {
    local dir="$1"
    local profile_file=""

    echo "$PATH" | tr ':' '\n' | grep -qx "$dir" && return

    warn "$dir belum ada di PATH."

    # Deteksi shell config
    if [ -n "${BASH_VERSION:-}" ] || [ "$(basename "${SHELL:-bash}")" = "bash" ]; then
        [ -f "${HOME}/.bashrc" ] && profile_file="${HOME}/.bashrc" || profile_file="${HOME}/.bash_profile"
    elif [ -n "${ZSH_VERSION:-}" ] || [ "$(basename "${SHELL:-}")" = "zsh" ]; then
        profile_file="${HOME}/.zshrc"
    else
        profile_file="${HOME}/.profile"
    fi

    # Khusus Termux — .bashrc sudah otomatis di-source
    [ "$ENV" = "termux" ] && profile_file="${HOME}/.bashrc"

    if [ -n "$profile_file" ]; then
        echo "" >> "$profile_file"
        echo "# Xbrowser" >> "$profile_file"
        echo "export PATH=\"$dir:\$PATH\"" >> "$profile_file"
        info "Ditambahkan ke $profile_file — jalankan: source $profile_file"
    else
        warn "Tambahkan manual ke shell config kamu: export PATH=\"$dir:\$PATH\""
    fi
}

ensure_in_path "$BIN_DIR"

# ── Simpan config Chromium jika ditemukan ─────────────────────────────────────
if [ -n "${CHROMIUM_CMD:-}" ] && [ "$CHROMIUM_CMD" != "" ]; then
    CONFIG_DIR="${HOME}/.xbrowser"
    mkdir -p "$CONFIG_DIR"
    CONFIG_FILE="${CONFIG_DIR}/config.json"

    NO_SANDBOX="false"
    # Container / CI / Termux → wajib no_sandbox
    if [ "$ENV" = "termux" ] || [ -f /.dockerenv ] || [ -n "${CI:-}" ]; then
        NO_SANDBOX="true"
    fi

    cat > "$CONFIG_FILE" <<EOF
{
    "chromium_path":   "${CHROMIUM_CMD}",
    "no_sandbox":      ${NO_SANDBOX},
    "startup_timeout": 60000,
    "headless":        true,
    "stealth":         true
}
EOF
    ok "Config disimpan: $CONFIG_FILE"
fi

# ── Verifikasi akhir ──────────────────────────────────────────────────────────
echo ""
echo "$(printf '═%.0s' {1..60})"
echo -e "${BOLD}  Verifikasi Instalasi${RESET}"
echo "$(printf '═%.0s' {1..60})"

if command -v Xbrowser &>/dev/null || [ -x "$LINK_TARGET" ]; then
    XBROWSER_BIN="${LINK_TARGET}"
    VERSION=$(php "$XBROWSER_BIN" --version 2>/dev/null | head -1 || echo "(tidak bisa baca versi)")
    ok "Xbrowser terinstall: $XBROWSER_BIN"
    ok "Versi: $VERSION"
else
    warn "Xbrowser belum bisa dipanggil langsung. Mungkin perlu reload shell:"
    echo "    source ~/.bashrc   atau   source ~/.zshrc"
fi

echo ""
echo -e "${BOLD}Cara pakai:${RESET}"
echo "  Xbrowser open https://example.com"
echo "  Xbrowser shell"
echo "  Xbrowser screenshot https://example.com hasil.png"
echo ""

if [ "$ENV" = "termux" ]; then
    echo -e "${YELLOW}Catatan Termux:${RESET}"
    echo "  Chromium di Termux butuh X11 atau VNC untuk GUI."
    echo "  Untuk headless (tanpa layar): sudah dikonfigurasi otomatis."
    echo "  Jika ada error sandbox: XBROWSER_NO_SANDBOX=true sudah diset."
    echo ""
fi

echo -e "${BOLD}Uninstall:${RESET}"
echo "  bash ${INSTALL_DIR}/uninstall.sh"
echo ""
echo -e "${GREEN}${BOLD}✓ Instalasi selesai!${RESET}"
