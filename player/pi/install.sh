#!/usr/bin/env bash
# Turn a Raspberry Pi (Raspberry Pi OS Lite, 64-bit) into a Rentall Screen Ads player.
# Run it on the Pi, from a clone of the repository. Full guide: docs/RASPBERRY_PI.md
#
#   First time:   sudo player/pi/install.sh --api https://192.168.1.50:8443 --ca ~/ca.crt --code ABCD-EFGH
#   Update:       git pull && sudo player/pi/install.sh          (keeps the enrolment)
#   New screen:   sudo player/pi/install.sh --api ... --ca ... --code <new code>
set -euo pipefail

API="" CA="" CODE=""
while [ $# -gt 0 ]; do
  case "$1" in
    --api) API="$2"; shift 2 ;;
    --ca) CA="$2"; shift 2 ;;
    --code) CODE="$2"; shift 2 ;;
    -h|--help) sed -n '2,8p' "$0"; exit 0 ;;
    *) echo "unknown option: $1 (see --help)" >&2; exit 1 ;;
  esac
done

die() { echo "error: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }

[ "$(id -u)" = 0 ] || die "run with sudo"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
[ -f "$REPO/player/pyproject.toml" ] || die "run this from a clone of the dooh repository"
if [ -n "$CODE" ]; then
  [ -n "$API" ] && [ -n "$CA" ] || die "--code needs --api and --ca too"
  [ -f "$CA" ] || die "CA certificate not found: $CA (copy infra/ca/out/ca.crt from the server)"
elif [ ! -f /etc/dooh/player.json ]; then
  die "not enrolled yet: pass --api, --ca and --code (get the code in the portal: My screens → Connect device)"
fi

step "Installing mpv and Python"
apt-get update -qq
apt-get install -y -qq --no-install-recommends mpv python3-venv python3-pip ca-certificates

step "Creating the dooh service user"
if ! id dooh >/dev/null 2>&1; then
  useradd --system --home-dir /var/lib/dooh --shell /usr/sbin/nologin dooh
fi
# video/render: draw on the HDMI output without a desktop.
for group in video render; do
  getent group "$group" >/dev/null && usermod -aG "$group" dooh
done
install -d -o dooh -g dooh -m 750 /var/lib/dooh

step "Installing the player into /opt/dooh"
[ -x /opt/dooh/venv/bin/python ] || python3 -m venv /opt/dooh/venv
/opt/dooh/venv/bin/pip install -q --upgrade pip
/opt/dooh/venv/bin/pip install -q --upgrade "$REPO/player"
echo "player $(/opt/dooh/venv/bin/dooh-player --version)"

if [ -n "$CODE" ]; then
  step "Enrolling: making this device's key and getting its certificate"
  # The key is written by (and only readable by) the dooh user; it never leaves this Pi.
  install -d -o root -g dooh -m 770 /etc/dooh
  install -o root -g dooh -m 644 "$CA" /etc/dooh/ca.crt
  systemctl stop dooh-player 2>/dev/null || true
  sudo -u dooh /opt/dooh/venv/bin/dooh-player enrol --config-dir /etc/dooh \
    --api "$API" --ca /etc/dooh/ca.crt --code "$CODE"
fi
chown -R root:dooh /etc/dooh
chmod 750 /etc/dooh
chown dooh:dooh /etc/dooh/device.key /etc/dooh/device.crt /etc/dooh/player.json
chmod 600 /etc/dooh/device.key

step "Kiosk settings"
CMDLINE=/boot/firmware/cmdline.txt
[ -f "$CMDLINE" ] || CMDLINE=/boot/cmdline.txt
if [ -f "$CMDLINE" ]; then
  # Never blank the screen, and no blinking console cursor between ads (takes effect after a reboot).
  for opt in consoleblank=0 vt.global_cursor_default=0; do
    grep -qw "$opt" "$CMDLINE" || sed -i "1 s/\$/ $opt/" "$CMDLINE"
  done
fi
if [ "$(systemctl get-default)" = graphical.target ]; then
  echo "warning: this Pi boots to a desktop, which holds the HDMI output so mpv can't draw on it."
  echo "         Use Raspberry Pi OS Lite, or run: sudo systemctl set-default multi-user.target && sudo reboot"
fi
timedatectl show -p NTPSynchronized --value 2>/dev/null | grep -q yes \
  || echo "note: the clock isn't synchronised yet; manifests are only accepted with the right time."

step "Starting the player service"
install -m 644 "$REPO/player/pi/dooh-player.service" /etc/systemd/system/dooh-player.service
systemctl daemon-reload
systemctl enable dooh-player >/dev/null
systemctl restart dooh-player
sleep 3
systemctl --no-pager --lines=8 status dooh-player || true

echo
echo "Done. Follow the player with:  journalctl -u dooh-player -f"
echo "The portal (My screens) shows this screen as Online within a minute."
