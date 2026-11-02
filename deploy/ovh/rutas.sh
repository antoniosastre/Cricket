# Las fases con --delete solo pueden actuar dentro de los directorios de Cricket.
: "${OVH_DIR_WEB:?Falta el directorio público}" "${OVH_DIR_CODIGO:?Falta el directorio privado}"
for ruta in "$OVH_DIR_WEB" "$OVH_DIR_CODIGO"; do
  if [[ ! $ruta =~ ^/?[a-zA-Z0-9_-]+(/[a-zA-Z0-9_-]+)+$ ]]; then
    echo 'Ruta SFTP no válida' >&2; exit 1
  fi
done
web=/${OVH_DIR_WEB#/}
codigo=/${OVH_DIR_CODIGO#/}
if [[ $web != */control || $codigo != */api || $codigo == "${web%/*}/"* || $web == "$codigo/"* ]]; then
  echo 'La web debe terminar en /control y el código en /api, fuera de la raíz web.' >&2; exit 1
fi
