# YOURLS Distribuido - Encurtador com Redis, MySQL e Load Balancer

> Trabalho de Sistemas Distribuidos - Universidade Positivo  
> Stack: YOURLS + PHP/Apache + MySQL 8.0 + Redis 7 + Nginx + Docker Compose

---

## Arquitetura

```text
[Navegador / curl]
        |
        v
   [Nginx :80]                  <-- Load balancer e proxy reverso
    /    |    \
   v     v     v
[yourls1][yourls2][yourls3]     <-- 3 instancias YOURLS sem estado local
   \     |     /
    v    v    v
    [Redis :6379]               <-- Sessoes PHP e cache distribuido
        |
        v
    [MySQL :3306]               <-- Banco central compartilhado
```

O usuario acessa apenas o Nginx. O Nginx encaminha as requisicoes para uma das tres instancias YOURLS. As instancias compartilham Redis e MySQL, entao qualquer replica consegue atender a mesma aplicacao.

---

## Premissas de Sistemas Distribuidos

| Premissa | Onde esta implementada |
| --- | --- |
| Transparencia de acesso | O usuario acessa `http://localhost` ou o IP publico; o Nginx esconde as replicas internas. |
| Tolerancia a falhas | Se `yourls1` parar, o Nginx tenta `yourls2` ou `yourls3`. |
| Escalabilidade horizontal | Novas replicas podem ser adicionadas no `docker-compose.yml` e no upstream do Nginx. |
| Compartilhamento de recursos | Redis e MySQL sao compartilhados por todas as instancias YOURLS. |
| Concorrencia | MySQL usa InnoDB; Redis usa operacoes atomicas para sessoes/cache. |
| Abertura | A aplicacao e acessada por HTTP, sem acoplamento ao cliente. |

---

## Estrutura de arquivos

```text
projetoProg/
├── docker-compose.yml                 # Nginx, 3 YOURLS, Redis, MySQL, redes, volumes e health checks
├── nginx/
│   └── nginx.conf                      # Load balancer entre yourls1, yourls2 e yourls3
├── mysql/
│   └── my.cnf                          # Configuracao do MySQL
└── yourls/
    ├── Dockerfile                      # Imagem YOURLS customizada com extensao Redis
    ├── redis-sessions.ini              # Sessoes PHP armazenadas no Redis
    ├── docker-entrypoint-custom.sh      # Wrapper do entrypoint oficial do YOURLS
    ├── activate-plugin.php             # Aviso operacional sobre ativacao do plugin
    └── plugins/
        └── redis-cache/
            └── plugin.php              # Plugin de cache distribuido usando Redis
```

---

## Como subir localmente

### 1. Criar o `.env`

```bash
cp .env.example .env
```

Para teste local, os valores padrao funcionam. Para uso publico, troque as senhas e ajuste `YOURLS_SITE`.

Exemplo local:

```env
YOURLS_DB_USER=yourls_user
YOURLS_DB_PASS=change_me_database_password
MYSQL_ROOT_PASSWORD=change_me_root_password
YOURLS_USER=admin
YOURLS_PASS=change_me_admin_password
YOURLS_SITE=http://localhost
```

### 2. Validar o Compose

```bash
docker compose config
```

### 3. Construir a imagem YOURLS

```bash
docker compose build
```

### 4. Subir o cluster

```bash
docker compose up -d
```

### 5. Verificar os servicos

```bash
docker compose ps
curl http://localhost/health
curl -I http://localhost/admin/install.php
```

Resposta esperada do health check:

```json
{"status":"ok","service":"yourls-nginx-lb","nodes":3}
```

### 6. Instalar o YOURLS

Acesse:

```text
http://localhost/admin/install.php
```

Depois entre no painel:

```text
http://localhost/admin/
```

Use as credenciais configuradas no `.env`.

---

## Roteiro de demonstracao

### Demo 1 - Load balancer ativo

Abra os logs do Nginx:

```bash
docker compose logs -f nginx
```

Em outro terminal, execute varias requisicoes:

```bash
for i in $(seq 1 10); do
  curl -s -o /dev/null -w "%{http_code}\n" http://localhost/admin/
done
```

No log do Nginx, observe o campo `upstream`, que mostra qual replica respondeu:

```text
upstream="172.x.x.x:8080"
```

Isso demonstra que o cliente nao acessa uma instancia especifica; ele acessa o servico pelo Nginx.

### Demo 2 - Failover sem derrubar o sistema

Pare uma replica:

```bash
docker compose stop yourls1
```

Teste novamente:

```bash
curl http://localhost/health
curl -I http://localhost/admin/
```

Resultado esperado:

- O Nginx continua respondendo.
- O YOURLS continua acessivel pelas replicas restantes.
- Os dados continuam preservados porque MySQL e Redis sao compartilhados.

Suba a replica novamente:

```bash
docker compose start yourls1
```

### Demo 3 - MySQL central compartilhado

Depois de instalar o YOURLS e criar um link curto pela interface, acesse o MySQL:

```bash
docker compose exec mysql sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Dentro do MySQL:

```sql
SHOW TABLES;
SELECT keyword, url, title FROM yourls_url ORDER BY timestamp DESC LIMIT 5;
```

Isso mostra que as tres replicas gravam e leem do mesmo banco central.

### Demo 4 - Redis para sessoes e cache

Verifique as chaves no Redis:

```bash
docker compose exec redis redis-cli KEYS "*"
```

Chaves esperadas depois de usar o sistema:

- `PHPREDIS_SESSION:*`, quando houver sessoes PHP ativas.
- `yourls_kw_*`, quando o plugin de cache Redis estiver ativado e algum link curto ja tiver sido consultado.

Para ver TTL e conteudo de uma chave:

```bash
docker compose exec redis redis-cli TTL "NOME_DA_CHAVE"
docker compose exec redis redis-cli GET "NOME_DA_CHAVE"
```

### Demo 5 - Cache distribuido do YOURLS

No painel admin do YOURLS:

1. Acesse `http://localhost/admin/plugins.php`.
2. Ative o plugin `Redis Cache Distribuido`.
3. Crie um link curto.
4. Acesse o link curto pelo navegador ou por `curl`.
5. Consulte o Redis:

```bash
docker compose exec redis redis-cli KEYS "yourls_kw_*"
```

Resultado esperado: o Redis passa a armazenar dados de lookup dos links curtos, reduzindo consultas repetidas ao MySQL.

---

## Endpoints principais

| Metodo | Endpoint | Descricao |
| --- | --- | --- |
| GET | `/health` | Health check do Nginx/load balancer. |
| GET | `/admin/install.php` | Instalacao inicial do YOURLS. |
| GET | `/admin/` | Painel administrativo do YOURLS. |
| GET | `/<keyword>` | Redirecionamento de link curto criado no YOURLS. |

---

## Comandos uteis

```bash
# Validar configuracao final do Compose
docker compose config

# Construir imagens
docker compose build

# Subir tudo
docker compose up -d

# Ver status e health checks
docker compose ps

# Logs principais
docker compose logs -f nginx yourls1 redis mysql

# Logs de todas as replicas YOURLS
docker compose logs -f yourls1 yourls2 yourls3

# Acessar Redis
docker compose exec redis redis-cli

# Acessar MySQL
docker compose exec mysql sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

# Parar tudo preservando volumes
docker compose down

# Reset completo com perda de dados locais
docker compose down -v
```

---

## Deploy Oracle Cloud Free Tier

Este projeto deve ser publicado no Oracle Cloud Free Tier usando uma unica VM, sem servicos gerenciados pagos. O Docker Compose roda Nginx, YOURLS, Redis e MySQL dentro da propria VM.

### Recursos recomendados

| Recurso | Configuracao |
| --- | --- |
| Compute | `VM.Standard.A1.Flex`, Always Free-eligible |
| Arquitetura | Ampere ARM |
| Capacidade | On-demand |
| OCPU/Memoria | Comece com `1 OCPU / 6 GB` ou `2 OCPU / 12 GB`, conforme disponibilidade |
| Imagem | Ubuntu 24.04 |
| Disco | Boot volume padrao |
| Rede | VCN com subnet publica e IPv4 publico |
| Portas | Abrir `22/tcp`, `80/tcp` e, se usar HTTPS depois, `443/tcp` |

Nao usar para este trabalho:

- Oracle Load Balancer.
- MySQL Database Service.
- Redis gerenciado.
- Block volume extra.
- Reserved public IP, a menos que voce confirme que esta dentro do limite gratuito da sua conta.
- Shape paga ou dedicada.

### Passo a passo resumido

1. Criar uma VM `VM.Standard.A1.Flex` Always Free-eligible.
2. Usar Ubuntu 24.04.
3. Selecionar subnet publica com IPv4 publico automatico.
4. Adicionar a chave SSH.
5. Manter boot volume padrao.
6. Liberar porta `80` na Security List ou Network Security Group.
7. Conectar por SSH:

```bash
ssh ubuntu@IP_PUBLICO_DA_VM
```

8. Instalar Docker e plugin Compose:

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker "$USER"
```

9. Sair e entrar de novo no SSH para aplicar o grupo `docker`.
10. Enviar ou clonar o projeto na VM.
11. Criar `.env` e trocar `YOURLS_SITE`:

```env
YOURLS_SITE=http://IP_PUBLICO_DA_VM
```

12. Trocar todas as senhas placeholder.
13. Subir o stack:

```bash
docker compose config
docker compose build
docker compose up -d
docker compose ps
curl http://localhost/health
```

14. Acessar no navegador:

```text
http://IP_PUBLICO_DA_VM/admin/install.php
```

---

## Cuidados de custo e seguranca

- Sempre confira se a shape mostra `Always Free-eligible` antes de criar a VM.
- Se aparecer custo estimado para boot volume pequeno, confira os limites gratuitos da tenancy antes de prosseguir.
- Se a `VM.Standard.A1.Flex` estiver sem capacidade, tente novamente mais tarde ou reduza para `1 OCPU / 6 GB`.
- Se uma VM antiga foi criada errada, termine a instancia e apague o boot volume associado se nao for mais usar.
- Nunca publique `.env`, dumps de banco ou chaves privadas.
- Em deploy publico, nao use `change_me_*` como senha.
- Para HTTPS, prefira configurar depois com Nginx/Certbot na propria VM, sem criar Load Balancer pago.
