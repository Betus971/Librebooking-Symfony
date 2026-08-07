#!/usr/bin/env bash
# =============================================================================
#  harden.sh — Durcissement d'un VPS Ubuntu/Debian hébergeant Librebooking
# -----------------------------------------------------------------------------
#  À LANCER EN ROOT (ou avec sudo) sur un VPS FRAÎCHEMENT installé.
#
#      sudo bash harden.sh
#
#  ⚠️  LIS LES VARIABLES CI-DESSOUS AVANT DE LANCER.
#  ⚠️  Garde une 2e session SSH ouverte pendant l'exécution : si tu te fais
#      éjecter par le pare-feu / SSH, tu pourras corriger sans être verrouillé.
# =============================================================================
set -euo pipefail

# ----------------------------- À PERSONNALISER -------------------------------
ADMIN_USER="deploy"          # compte non-root qui gérera l'app (créé s'il n'existe pas)
SSH_PORT="22"                # change-le (ex: 2222) pour réduire le bruit des bots
ALLOW_PASSWORD_SSH="no"      # "no" = connexion par clé SSH uniquement (recommandé)
                             #   ⚠️  mets "yes" si tu n'as PAS encore mis ta clé publique !
TIMEZONE="Europe/Paris"
# -----------------------------------------------------------------------------

log() { echo -e "\n\033[1;32m▶ $*\033[0m"; }
warn() { echo -e "\033[1;33m⚠ $*\033[0m"; }

if [[ $EUID -ne 0 ]]; then echo "Lance ce script en root (sudo)."; exit 1; fi

log "Mise à jour du système"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y && apt-get upgrade -y

log "Fuseau horaire → $TIMEZONE"
timedatectl set-timezone "$TIMEZONE" || true

log "Installation des outils de sécurité"
apt-get install -y ufw fail2ban unattended-upgrades curl ca-certificates

# ---------------------------------------------------------------------------
log "Compte non-root : $ADMIN_USER"
if ! id "$ADMIN_USER" &>/dev/null; then
    adduser --disabled-password --gecos "" "$ADMIN_USER"
    usermod -aG sudo "$ADMIN_USER"
    warn "Compte $ADMIN_USER créé SANS mot de passe. Ajoute ta clé SSH :"
    warn "  mkdir -p /home/$ADMIN_USER/.ssh && nano /home/$ADMIN_USER/.ssh/authorized_keys"
    install -d -m 700 -o "$ADMIN_USER" -g "$ADMIN_USER" "/home/$ADMIN_USER/.ssh"
fi

# ---------------------------------------------------------------------------
log "Pare-feu UFW (deny entrant par défaut, autorise SSH + HTTP/HTTPS)"
ufw default deny incoming
ufw default allow outgoing
ufw allow "${SSH_PORT}/tcp" comment 'SSH'
ufw allow 80/tcp  comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw --force enable
ufw status verbose

# ---------------------------------------------------------------------------
log "Durcissement SSH"
SSHD=/etc/ssh/sshd_config.d/99-hardening.conf
cat > "$SSHD" <<EOF
Port ${SSH_PORT}
PermitRootLogin no
PasswordAuthentication ${ALLOW_PASSWORD_SSH/yes/yes}
PubkeyAuthentication yes
X11Forwarding no
MaxAuthTries 3
LoginGraceTime 30
ClientAliveInterval 300
ClientAliveCountMax 2
EOF
# Normalise la valeur (yes/no)
if [[ "$ALLOW_PASSWORD_SSH" == "no" ]]; then
    sed -i 's/^PasswordAuthentication .*/PasswordAuthentication no/' "$SSHD"
    warn "Connexion SSH par MOT DE PASSE DÉSACTIVÉE — assure-toi que ta clé fonctionne !"
else
    sed -i 's/^PasswordAuthentication .*/PasswordAuthentication yes/' "$SSHD"
fi
sshd -t && systemctl reload ssh || systemctl reload sshd || true

# ---------------------------------------------------------------------------
log "fail2ban (bannit les IP qui bruteforcent SSH)"
cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = ${SSH_PORT}
EOF
systemctl enable --now fail2ban
systemctl restart fail2ban

# ---------------------------------------------------------------------------
log "Mises à jour de sécurité automatiques"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
systemctl enable --now unattended-upgrades || true

# ---------------------------------------------------------------------------
log "Durcissement noyau (sysctl)"
cat > /etc/sysctl.d/99-hardening.conf <<'EOF'
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.tcp_syncookies = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
kernel.randomize_va_space = 2
EOF
sysctl --system >/dev/null

log "Durcissement terminé ✅"
echo
warn "AVANT DE FERMER TA SESSION : ouvre une NOUVELLE connexion SSH sur le port ${SSH_PORT}"
warn "pour vérifier que tu n'es pas verrouillé (clé SSH + bon port)."
echo "Étapes suivantes : installer Nginx/PHP/PostgreSQL puis déployer l'app (voir docs/securite-vps.md)."
