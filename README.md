# Carteira Financeira

Uma carteira financeira digital construída com PHP/Laravel. Permite cadastro de usuários, autenticação via JWT, depósitos, transferências entre usuários e reversão de operações — tudo rodando em containers Docker.

## Pré-requisitos

- Docker e Docker Compose instalados
- Portas 8080, 3306 e 6379 disponíveis na máquina

## Subindo o projeto

```bash
# Clone o repositório e entre na pasta
cd carteira-financeira

# Copie o arquivo de ambiente (se ainda não existir)
cp .env.example .env

# Suba os containers
docker-compose up -d --build

# Instale as dependências do PHP
docker-compose exec app composer install

# Rode as migrações do banco
docker-compose exec app php artisan migrate --force
```

Pronto. A aplicação estará disponível em **http://localhost:8080**.

## Acessos

| Serviço | Endereço |
|---------|----------|
| Aplicação (navegador) | http://localhost:8080 |
| API REST | http://localhost:8080/api |
| MySQL | localhost:3306 |
| Redis | localhost:6379 |

## Credenciais do banco

| Campo | Valor |
|-------|-------|
| Banco de dados | wallet |
| Usuário | wallet_user |
| Senha | secret |
| Senha root | rootsecret |

## Usando pelo navegador

Acesse http://localhost:8080 e você será redirecionado para a tela de login. Se não tem conta, clique em "Cadastre-se".

Após o login, o painel exibe:
- Saldo atual (atualizado automaticamente)
- Formulário de depósito
- Formulário de transferência (com lista de destinatários cadastrados)
- Extrato completo com opção de reverter transações

## Usando pela API

### Cadastro

```bash
curl -X POST http://localhost:8080/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@email.com",
    "cpf": "12345678901",
    "password": "senhasegura123"
  }'
```

### Login

```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao@email.com",
    "password": "senhasegura123"
  }'
```

O login retorna um token JWT. Use ele no header `Authorization: Bearer <token>` nas rotas protegidas.

### Consultar saldo

```bash
curl http://localhost:8080/api/wallet/balance \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Depositar

```bash
curl -X POST http://localhost:8080/api/wallet/deposit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{"amount": 500.00}'
```

### Transferir

```bash
curl -X POST http://localhost:8080/api/wallet/transfer \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "receiver_id": 2,
    "amount": 100.00
  }'
```

### Extrato

```bash
curl http://localhost:8080/api/wallet/statement \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Reverter transação

```bash
curl -X POST http://localhost:8080/api/wallet/reverse/1 \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Logout

```bash
curl -X POST http://localhost:8080/api/logout \
  -H "Authorization: Bearer SEU_TOKEN"
```

## Rotas da API

| Método | Rota | Descrição | Autenticação |
|--------|------|-----------|:------------:|
| POST | /api/register | Cadastro de usuário | Não |
| POST | /api/login | Login (retorna JWT) | Não |
| POST | /api/logout | Invalidar token | Sim |
| GET | /api/users | Listar usuários (para transferência) | Sim |
| GET | /api/wallet/balance | Consultar saldo | Sim |
| GET | /api/wallet/statement | Extrato de transações | Sim |
| POST | /api/wallet/deposit | Realizar depósito | Sim |
| POST | /api/wallet/transfer | Transferir para outro usuário | Sim |
| POST | /api/wallet/reverse/{id} | Reverter uma transação | Sim |

## Rodando os testes

```bash
docker-compose exec app php artisan test
```

Os testes rodam com SQLite em memória, então não afetam o banco MySQL.

## Tecnologias utilizadas

- **Backend:** PHP 8.3, Laravel 11
- **Autenticação:** JWT (tymon/jwt-auth)
- **Banco de dados:** MySQL 8
- **Cache e controle de requisições:** Redis 7
- **Frontend:** Blade + Vue.js 3 (via CDN)
- **Infraestrutura:** Docker, Nginx

## Decisões de projeto

- Todas as operações financeiras (depósito, transferência, reversão) usam transações atômicas no banco de dados
- Transferências usam travamento pessimista (`SELECT FOR UPDATE`) para evitar condições de corrida
- Reversões permitem saldo negativo — isso é intencional para manter a consistência do histórico
- Controle de requisições: 5 por minuto no login, 60 por minuto nas operações financeiras
- Logs estruturados em JSON para facilitar monitoramento
- Senhas armazenadas com bcrypt

## Estrutura do projeto

```
├── app/
│   ├── Http/
│   │   ├── Controllers/Auth/     # Registro e login
│   │   ├── Controllers/Wallet/   # Operações financeiras
│   │   ├── Middleware/           # Autenticação JWT
│   │   └── Requests/            # Validação de entrada
│   ├── Models/                   # Usuário, Carteira, Transação
│   └── Services/                 # Serviço da carteira (lógica de negócio)
├── database/migrations/          # Estrutura do banco
├── docker/                       # Configs do Nginx e script de inicialização
├── resources/views/              # Templates Blade com Vue.js
├── routes/
│   ├── api.php                   # Rotas da API
│   └── web.php                   # Rotas do navegador
├── tests/Feature/                # Testes automatizados
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

## Problemas comuns

**Erro de permissão no storage:**
```bash
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

**Porta 8080 em uso:**
Edite a porta no `docker-compose.yml` na seção do nginx:
```yaml
ports:
  - "OUTRA_PORTA:80"
```

**Quero resetar o banco:**
```bash
docker-compose exec app php artisan migrate:fresh
```
