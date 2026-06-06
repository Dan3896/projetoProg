<?php
/*
Plugin Name: Redis Cache Distribuído
Plugin URI: https://github.com/
Description: Sistema de cache em memória utilizando Redis para alta performance do cluster. Desenvolvido para a disciplina de Sistemas Distribuídos.
Version: 1.0
Author: Sua Equipe
Author URI: http://seu-dominio/
*/

// Segurança: Impede acesso direto ao ficheiro
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// 1. Inicializa a ligação ao Redis quando os plugins carregam
yourls_add_action( 'plugins_loaded', 'cluster_redis_init' );
function cluster_redis_init() {
    global $redis_cache;
    if (class_exists('Redis')) {
        $redis_cache = new Redis();
        // Liga ao host 'redis' que configurámos no docker-compose.yml
        @$redis_cache->connect('redis', 6379);
    }
}

// 2. Interceta a busca: Tenta ler a URL do cache antes de ir ao MySQL
yourls_add_filter( 'shunt_get_keyword_infos', 'cluster_redis_get_cache' );
function cluster_redis_get_cache( $false, $keyword ) {
    global $redis_cache;
    // Verifica se o Redis está vivo
    if( isset($redis_cache) && $redis_cache->ping() ) {
        $cached = $redis_cache->get("yourls_kw_" . $keyword);
        if( $cached !== false ) {
            // Se encontrou no cache, devolve sem sobrecarregar o banco
            return unserialize($cached);
        }
    }
    return false;
}

// 3. Salva no cache: Após ir ao MySQL, guarda o resultado no Redis por 1 hora
yourls_add_filter( 'get_keyword_infos', 'cluster_redis_set_cache' );
function cluster_redis_set_cache( $infos, $keyword ) {
    global $redis_cache;
    if( isset($redis_cache) && $redis_cache->ping() ) {
        // Guarda na memória por 3600 segundos (1 hora)
        $redis_cache->setex("yourls_kw_" . $keyword, 3600, serialize($infos));
    }
    return $infos;
}
?>