#!/bin/bash
set -e

# Corrige as permissões antes de subir o servidor Apache
chown -R www-data:www-data /var/www/html/user/

# Chama o Entrypoint original da imagem YOURLS
exec container-entrypoint.sh "$@"
