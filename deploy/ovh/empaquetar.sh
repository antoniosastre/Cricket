#!/usr/bin/env bash
set -euo pipefail
# Salida nueva y vacía. Las dos rutas remotas usan la misma raíz SFTP.
destino=${1:?Indica un directorio nuevo para el paquete}
version=${2:?Indica el commit a desplegar}
raiz=$(cd "$(dirname "$0")/../.." && pwd)
cd "$raiz"
source deploy/ovh/rutas.sh
mkdir "$destino"
destino=$(cd "$destino" && pwd)
mkdir -p "$destino/web/api" "$destino/codigo"
cp -R api/src api/public api/migrations "$destino/codigo/"
printf '%s\n' "$version" > "$destino/codigo/VERSION"
cp deploy/ovh/{.htaccess,index.html} "$destino/web/"
cp -R admin/dist "$destino/web/admin"
cp -R pwa/dist "$destino/web/pwa"
test -s "$destino/web/admin/index.html"
test -s "$destino/web/pwa/sw.js"
relativa=$(realpath -ms --relative-to="/${OVH_DIR_WEB#/}/api" "/${OVH_DIR_CODIGO#/}/public/index.php")
printf "<?php\nrequire __DIR__ . '/%s';\n" "$relativa" > "$destino/web/api/index.php"
{ head -n 5 deploy/ovh/.htaccess; cat api/public/.htaccess; } > "$destino/web/api/.htaccess"
