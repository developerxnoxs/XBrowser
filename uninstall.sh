#!/usr/bin/env bash
# =============================================================================
# Xbrowser — Uninstaller
# =============================================================================

set -euo pipefail

if [ -t 1 ]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
    CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'
else
    RED=''; GREEN=''; YELLOW=''; CYAN=''; BOLD=''; RESET=''
fi

info()  { echo -e "${CYAN}[INFO]${RESET}  $*"; }
ok()    { echo -e "${GREEN}[  OK ]${RESET}  $*"; }
warn()  { echo -e "${YELLOW}[ WARN]${RESET}  $*"; }
die()   { echo -e "${RED}[ERROR]${RESET}  $*" >&2; exit 1; }

run() {
    if [ "$(id -u)" != "0" ] && command -v sudo &>/dev/null; then
        sudo "$@"
    else
        "$@"
    fi
}

echo ""
echo "$(printf '═%.0s' {1..60})"
echo -e "${BOLD}  Xbrowser Uninstaller${RESET}"
echo "$(printf '═%.0s' {1..60})"
echo ""

# ── Hapus symlink dari PATH ────────────────────────────────────────────────────
CANDIDATES=(
    "/usr/local/bin/Xbrowser"
    "${HOME}/.local/bin/Xbrowser"
    "${PREFIX:-}/bin/Xbrowser"   # Termux
)

REMOVED_LINK=false
for link in "${CANDIDATES[@]}"; do
    [ -z "$link" ] && continue
    if [ -L "$link" ] || [ -f "$link" ]; then
        info "Menghapus: $link"
        run rm -f "$link"
        ok "Terhapus: $link"
        REMOVED_LINK=true
    fi
done

$REMOVED_LINK || warn "Symlink Xbrowser tidak ditemukan di PATH standar."

# ── Hapus direktori instalasi ──────────────────────────────────────────────────
INSTALL_DIRS=(
    "/opt/xbrowser"
    "${HOME}/.local/share/xbrowser"
    "${PREFIX:-}/opt/xbrowser"   # Termux
)

for dir in "${INSTALL_DIRS[@]}"; do
    [ -z "$dir" ] && continue
    if [ -d "$dir" ] && [ -f "${dir}/composer.json" ]; then
        echo ""
        echo -n "Hapus direktori instalasi ${BOLD}${dir}${RESET}? [y/N] "
        read -r CONFIRM
        if [[ "$CONFIRM" =~ ^[Yy]$ ]]; then
            run rm -rf "$dir"
            ok "Terhapus: $dir"
        else
            info "Dilewati: $dir"
        fi
    fi
done

# ── Hapus config ───────────────────────────────────────────────────────────────
CONFIG_DIR="${HOME}/.xbrowser"
if [ -d "$CONFIG_DIR" ]; then
    echo ""
    echo -n "Hapus config di ${BOLD}${CONFIG_DIR}${RESET} (sessions, config.json)? [y/N] "
    read -r CONFIRM
    if [[ "$CONFIRM" =~ ^[Yy]$ ]]; then
        rm -rf "$CONFIG_DIR"
        ok "Terhapus: $CONFIG_DIR"
    else
        info "Dilewati: $CONFIG_DIR (config dan sesi tetap ada)"
    fi
fi

# ── Bersihkan sisa PATH di shell config ───────────────────────────────────────
for profile in "${HOME}/.bashrc" "${HOME}/.bash_profile" "${HOME}/.zshrc" "${HOME}/.profile"; do
    [ -f "$profile" ] || continue
    if grep -q "# Xbrowser" "$profile" 2>/dev/null; then
        info "Membersihkan entri PATH dari $profile..."
        # Hapus baris komentar Xbrowser dan baris export PATH berikutnya
        sed -i '/# Xbrowser/{N;/export PATH.*xbrowser\|\.local\/bin/Id}' "$profile" 2>/dev/null || \
        sed -i '/# Xbrowser/,/export PATH/d' "$profile" 2>/dev/null || true
        ok "Dibersihkan: $profile"
    fi
done

echo ""
echo -e "${GREEN}${BOLD}✓ Uninstall selesai.${RESET}"
echo ""
