FROM yourls:latest

# Instala a extensão Redis para o PHP
RUN apt-get update \
    && apt-get install -y --no-install-recommends libssl-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Configura o Redis como handler de sessão
COPY redis-sessions.ini /usr/local/etc/php/conf.d/99-redis-sessions.ini

# Copia os plugins para o conteiner (garanta que o plugin do Redis foi baixado localmente nessa pasta)
COPY plugins/ /var/www/html/user/plugins/

# Copia arquivos e scripts de inicialização
COPY activate-plugin.php /usr/local/bin/activate-plugin.php
COPY docker-entrypoint-custom.sh /usr/local/bin/docker-entrypoint-custom.sh

# Ajusta permissões
RUN chmod +x /usr/local/bin/docker-entrypoint-custom.sh \
    && chown -R www-data:www-data /var/www/html/user/

ENTRYPOINT ["docker-entrypoint-custom.sh"]
CMD ["apache2-foreground"]
