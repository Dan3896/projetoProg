#!/bin/bash
set -e

# Corrige as permissões antes de subir o servidor Apache
chown -R www-data:www-data /var/www/html/user/

# Chama o Entrypoint original do sistema YOURLS
exec docker-entrypoint.sh "$@"