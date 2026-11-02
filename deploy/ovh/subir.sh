#!/usr/bin/env bash
set -euo pipefail
paquete=$(cd "${1:?Indica el directorio del paquete}" && pwd)
source "$(dirname "$0")/rutas.sh"
: "${OVH_SFTP_HOST:?}" "${OVH_SFTP_USUARIO:?}" "${OVH_SFTP_PASSWORD:?}"
[[ $OVH_SFTP_HOST =~ ^[a-zA-Z0-9.-]+$ && $OVH_SFTP_USUARIO =~ ^[a-zA-Z0-9._-]+$ ]] || exit 2
test -s "$paquete/codigo/VERSION" && test -s "$paquete/web/pwa/sw.js" || exit 2
cd "$paquete"
claves=$(mktemp)
trap 'rm -f "$claves"' EXIT
politica=accept-new
if [[ -n ${OVH_SFTP_KNOWN_HOSTS:-} ]]; then
  printf '%s\n' "$OVH_SFTP_KNOWN_HOSTS" > "$claves"
  politica=yes
fi
export LFTP_PASSWORD="$OVH_SFTP_PASSWORD"
lftp --env-password -u "$OVH_SFTP_USUARIO" "sftp://$OVH_SFTP_HOST" <<LFTP
set cmd:fail-exit yes
set net:max-retries 2
set net:timeout 20
set sftp:connect-program "ssh -a -x -oStrictHostKeyChecking=$politica -oUserKnownHostsFile=$claves"
set xfer:use-temp-file yes
glob --exist "$OVH_DIR_WEB" || mkdir -p "$OVH_DIR_WEB"
glob --exist "$OVH_DIR_CODIGO/config" || mkdir -p "$OVH_DIR_CODIGO/config"
mirror -R --no-perms --exclude-glob config/ --exclude-glob config.php codigo "$OVH_DIR_CODIGO"
mirror -R --no-perms --exclude-glob index.html --exclude-glob sw.js web "$OVH_DIR_WEB"
mirror -R --no-perms web "$OVH_DIR_WEB"
mirror -R --no-perms --delete --exclude-glob config/ --exclude-glob config.php codigo "$OVH_DIR_CODIGO"
mirror -R --no-perms --delete web "$OVH_DIR_WEB"
bye
LFTP
