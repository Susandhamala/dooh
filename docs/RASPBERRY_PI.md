# Raspberry Pi screen

How to turn a Raspberry Pi into a real screen for the lab: it enrols with a one-time code, gets
its own client certificate, and plays the signed playlist the server sends it.

```
 Windows PC (Docker lab)                          Raspberry Pi ── HDMI ── screen
 ┌─────────────────────────────┐   LAN, mutual TLS  ┌──────────────────────────┐
 │ web :8443 ── api ── postgres │ ◄───────────────► │ dooh-player (systemd)    │
 │ scheduler = manifest signer │   port 8443 only   │  mpv full screen (no GUI)│
 └─────────────────────────────┘                    └──────────────────────────┘
```

## What happens, in short

1. In the portal the owner asks for a **connection code** for one screen (works once, 30 minutes).
2. On the Pi, `install.sh` makes an **Ed25519 key pair on the Pi** and sends only a certificate
   request (CSR) with the code. The server signs it and the Pi keeps the certificate. The private
   key never leaves the Pi (`/etc/dooh/device.key`, readable only by the `dooh` user).
3. Every minute the Pi fetches its **signed manifest** over mutual TLS. It checks the signature,
   that it's for this screen, that it's newer than the last one and not expired, and the SHA-256
   of every file before keeping it.
4. It plays each ad in its booked slot with **mpv**, straight to HDMI with no desktop. If the
   network drops, it keeps playing the saved playlist (also after a reboot) until that expires.
5. **Disconnect** in the portal revokes the certificate: the server refuses the Pi at once and
   the screen goes blank.

## You need

- Raspberry Pi 5 or 4 (2 GB is plenty), power supply, microSD card (16 GB or more)
- Micro-HDMI to HDMI cable and a screen
- The Pi and the Windows PC on the **same network** (Wi-Fi or Ethernet)
- The Docker lab running on the PC (see the README) with a screen created in the portal

## 1. Put Raspberry Pi OS Lite on the card

Use [Raspberry Pi Imager](https://www.raspberrypi.com/software/) on the PC.

1. **Device:** your Pi model. **OS:** Raspberry Pi OS (other) → **Raspberry Pi OS Lite (64-bit)**.
   Use Lite (no desktop): a desktop takes over the HDMI output and mpv can't draw on it.
2. When it asks about OS customisation, choose **Edit settings**:
   - Hostname: `dooh-pi-1`
   - Username and password: e.g. `pi` and a password you'll remember
   - Wi-Fi name and password (skip if using Ethernet). Wireless LAN country: `NP`
   - Locale: time zone `Asia/Kathmandu`
   - Services tab: **Enable SSH**. Either password authentication, or (so the PC can run the
     install for you) **Allow public-key authentication only** with the PC's key pasted in
     (`ssh-keygen -t ed25519 -f ~/.ssh/dooh_pi`, then paste `~/.ssh/dooh_pi.pub`)
3. Write the card, put it in the Pi, connect HDMI and power. The first boot takes a couple of minutes.

## 2. Get the PC ready (once)

All in **PowerShell**, in the `dooh` folder, unless it says Git Bash.

**Find the PC's address on the network.** Run `ipconfig` and note the **IPv4 Address** of the
Wi-Fi or Ethernet adapter, e.g. `192.168.1.50`. In your router, give the PC a fixed (reserved)
address, or you'll have to redo this step when it changes.

**Put that address in the server certificate**, so the Pi accepts the server (Git Bash):

```bash
bash infra/ca/lab-ca.sh server IP:192.168.1.50
docker compose up -d --force-recreate web
```

**Open port 8443** in Windows Firewall. Open PowerShell **as Administrator**:

```powershell
New-NetFirewallRule -DisplayName "DOOH screen players" -Direction Inbound -Protocol TCP -LocalPort 8443 -Action Allow -Profile Private
```

Your Wi-Fi or Ethernet network has to be set to **Private** in Windows settings (Network &
internet → your network → Network profile type).

**Check the stack is up to date:**

```powershell
docker compose up -d --build
docker compose exec api php artisan migrate --force
```

**Check the Pi will be able to reach it** (a 403 is the right answer here: no certificate yet).
Windows' own `curl.exe` can't do the Ed25519 handshake, so the check runs curl in Docker:

```powershell
docker run --rm curlimages/curl:8.11.1 -sk -o /dev/null -w "%{http_code}
" https://192.168.1.50:8443/device/v1/manifest
```

## 3. Copy the CA certificate to the Pi

The Pi uses it to check that it's talking to your server. It's public, not a secret.

```powershell
scp infra\ca\out\ca.crt pi@dooh-pi-1.local:
```

(If `dooh-pi-1.local` isn't found, use the Pi's IP address from your router's device list.)

## 4. Get a connection code

In the portal: **My organisations → your screen owner organisation → My screens**. On the screen's
card, click **Connect player**. (Locally `OWNER_REQUIRE_MFA=false` skips two-factor for owners.)
The lab has a ready owner account: `rentalnepal@screen.com` (same text as password, email sign-in;
create it with `docker compose exec api php artisan db:seed --class=RentallSeeder`). It owns
**RentALL Office Screen (Raspberry Pi)** and **RentALL Showroom Billboard**. Note the code, e.g. `K7QM-3XPA`.
It works once, for 30 minutes.

## 5. Install the player on the Pi

```powershell
ssh pi@dooh-pi-1.local
```

On the Pi:

```bash
sudo apt-get update && sudo apt-get install -y git
git clone -b mvp https://github.com/Susandhamala/dooh.git
cd dooh
sudo player/pi/install.sh --api https://192.168.1.50:8443 --ca ~/ca.crt --code K7QM-3XPA
sudo reboot     # once, so the console never blanks the screen
```

The script installs mpv, creates a `dooh` service user, installs the player in `/opt/dooh`,
enrols, and starts the `dooh-player` service, which then starts on every boot.

## 6. Check it works

- Portal → My screens: the card says **Player online** within a minute.
- On the Pi: `journalctl -u dooh-player -f` shows what it's doing:

  ```
  player 0.2.0 for screen "New Road Junction" (…), API https://192.168.1.50:8443
  accepted manifest v1793719500 with 2 items
  showing 01a1a1ff-… (slot 3)
  ```

- The screen shows an ad only in the slots and hours that are booked, and is black otherwise. To
  see one: as an advertiser, upload an ad and book this screen for the current hour; as an admin,
  approve the ad. Within a minute the manifest changes and the Pi downloads and plays it.

## Everyday tasks

| Task | How |
|---|---|
| Update the player | On the Pi: `cd ~/dooh && git pull && sudo player/pi/install.sh` (keeps the enrolment) |
| Move the Pi to another screen | Get a code for the other screen, run `install.sh` again with `--api --ca --code` |
| Lost or stolen Pi | Portal → **Disconnect**. Its certificate is refused from the next request on |
| Restart the player | `sudo systemctl restart dooh-player` |
| Stop it | `sudo systemctl disable --now dooh-player` |

## When something is wrong

| You see | Fix |
|---|---|
| `the server's certificate doesn't match … (or doesn't list this address)` | The server certificate doesn't have the address in `--api`. Redo the `lab-ca.sh server IP:…` step with the PC's current IP and recreate `web`. |
| `could not reach https://…` | PC and Pi on different networks, firewall rule missing, network not Private, or Docker not running. Try the Docker curl check from the PC, and `ping 192.168.1.50` from the Pi. |
| `That code is wrong or has expired` | Codes work once for 30 minutes, and a newer code replaces an older one. Get a new one. |
| `server refused this device (403)` | The player was disconnected, or another device was connected to the screen. Enrol again with a new code. |
| `rejected manifest: outside validity window` | The Pi's clock is wrong. `timedatectl` should say `System clock synchronized: yes`. It needs internet access for NTP. |
| `rejected manifest: bad signature` | The server's signing key changed (lab reset). Enrol again with a new code, which also fetches the new public key. |
| Logs say `showing …` but the screen is black | Check the HDMI cable and input. A desktop image holds the screen: `sudo systemctl set-default multi-user.target && sudo reboot`. `journalctl -u dooh-player` shows mpv errors. |
| `mpv did not start` | Copy any picture to the Pi and run `mpv --vo=gpu --gpu-context=drm picture.jpg` as `pi` to see the error. Add mpv options with `Environment=DOOH_MPV_ARGS=…` in `sudo systemctl edit dooh-player`. |

## Where things are on the Pi

| Path | What |
|---|---|
| `/etc/dooh/player.json` | Server address, screen and device ids, manifest public key |
| `/etc/dooh/device.key` | The Pi's private key (mode 600, user `dooh`) |
| `/etc/dooh/device.crt`, `ca.crt` | Its certificate, and the CA that signed it |
| `/var/lib/dooh/state.json` | Last accepted manifest and its version (stops replays) |
| `/var/lib/dooh/media/` | Ad files, named by SHA-256, only kept if the hash matches |
| `/etc/systemd/system/dooh-player.service` | The service (hardened: read-only system, no new privileges) |

## Not done yet

- **Proof of play:** the Pi logs each play locally (in the journal), but doesn't upload signed
  play logs yet, so charging still uses the lab simulation (`SIMULATE_PLAYS=true`). This is phase 3.
- **Certificate renewal:** device certificates are valid for a year. Re-enrol with a new code before then.
- **Remote screenshot and offline alerts** in the portal.
- The lab CA in `infra/ca/out` is for the lab only; production needs its own CA with an offline key.
