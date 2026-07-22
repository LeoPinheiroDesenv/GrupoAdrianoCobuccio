# Documento de Requisitos

## Introdução

Este documento descreve os requisitos para uma aplicação de carteira financeira digital. A aplicação permite que usuários se cadastrem, autentiquem, realizem depósitos, enviem e recebam transferências de saldo. O sistema é construído com PHP/Laravel, banco de dados SQL, e ambiente containerizado com Docker. O foco está em segurança, reversibilidade de operações e integridade de dados financeiros.

## Glossário

- **Sistema**: A aplicação de carteira financeira como um todo
- **Usuário**: Pessoa cadastrada que possui uma carteira no sistema
- **Carteira**: Conta financeira associada a um usuário, contendo saldo
- **Transferência**: Operação de movimentação de saldo entre duas carteiras
- **Depósito**: Operação de adição de saldo à carteira do usuário
- **Transação**: Registro imutável de qualquer operação financeira (depósito ou transferência)
- **Reversão**: Operação que desfaz uma transação previamente realizada
- **Autenticador**: Módulo responsável pela verificação de identidade do usuário
- **Validador_de_Saldo**: Módulo responsável por verificar se o usuário possui saldo suficiente para uma operação

## Requisitos

### Requisito 1: Cadastro de Usuário

**User Story:** Como um visitante, eu quero me cadastrar no sistema, para que eu possa ter uma carteira financeira e realizar operações.

#### Critérios de Aceitação

1. WHEN um visitante submete dados válidos de cadastro (nome, e-mail, senha, CPF), THE Sistema SHALL criar uma conta de usuário e uma carteira associada com saldo inicial zero
2. WHEN um visitante submete um e-mail já cadastrado, THE Sistema SHALL retornar um erro informando que o e-mail já está em uso
3. WHEN um visitante submete um CPF já cadastrado, THE Sistema SHALL retornar um erro informando que o CPF já está em uso
4. THE Sistema SHALL armazenar a senha do usuário utilizando hash bcrypt
5. WHEN um visitante submete dados incompletos ou inválidos, THE Sistema SHALL retornar mensagens de validação específicas para cada campo inválido

### Requisito 2: Autenticação de Usuário

**User Story:** Como um usuário cadastrado, eu quero me autenticar no sistema, para que eu possa acessar minha carteira e realizar operações financeiras de forma segura.

#### Critérios de Aceitação

1. WHEN um usuário submete credenciais válidas (e-mail e senha), THE Autenticador SHALL gerar um token JWT e retorná-lo ao usuário
2. WHEN um usuário submete credenciais inválidas, THE Autenticador SHALL retornar um erro de autenticação sem revelar qual campo está incorreto
3. WHILE um usuário possui um token válido, THE Sistema SHALL permitir acesso às rotas protegidas
4. WHEN um token expira ou é inválido, THE Sistema SHALL retornar erro 401 e negar acesso à operação solicitada
5. WHEN um usuário solicita logout, THE Autenticador SHALL invalidar o token atual do usuário

### Requisito 3: Consulta de Saldo e Extrato

**User Story:** Como um usuário autenticado, eu quero consultar meu saldo e extrato, para que eu possa acompanhar minhas finanças.

#### Critérios de Aceitação

1. WHEN um usuário autenticado solicita o saldo, THE Sistema SHALL retornar o saldo atual da carteira do usuário
2. WHEN um usuário autenticado solicita o extrato, THE Sistema SHALL retornar a lista de transações ordenadas por data decrescente
3. THE Sistema SHALL exibir para cada transação: tipo (depósito, transferência enviada, transferência recebida, reversão), valor, data e status

### Requisito 4: Depósito

**User Story:** Como um usuário autenticado, eu quero depositar dinheiro na minha carteira, para que eu possa ter saldo disponível para transferências.

#### Critérios de Aceitação

1. WHEN um usuário autenticado submete um valor de depósito positivo, THE Sistema SHALL adicionar o valor ao saldo da carteira do usuário e registrar a transação
2. WHEN um usuário autenticado submete um valor de depósito menor ou igual a zero, THE Sistema SHALL rejeitar a operação e retornar erro de validação
3. WHILE o saldo da carteira do usuário é negativo, THE Sistema SHALL aplicar o valor do depósito somando ao saldo existente (ex: saldo -50 + depósito 100 = saldo 50)
4. THE Sistema SHALL registrar cada depósito como uma transação com tipo "depósito", valor, data e identificador único

### Requisito 5: Transferência entre Usuários

**User Story:** Como um usuário autenticado, eu quero transferir saldo para outro usuário, para que eu possa enviar dinheiro de forma rápida e segura.

#### Critérios de Aceitação

1. WHEN um usuário autenticado solicita uma transferência com valor positivo para um destinatário válido, THE Validador_de_Saldo SHALL verificar se o remetente possui saldo suficiente
2. WHEN o remetente possui saldo suficiente, THE Sistema SHALL debitar o valor da carteira do remetente, creditar na carteira do destinatário, e registrar a transação para ambos
3. WHEN o remetente não possui saldo suficiente, THE Sistema SHALL rejeitar a transferência e retornar erro informando saldo insuficiente
4. WHEN o destinatário informado não existe, THE Sistema SHALL rejeitar a transferência e retornar erro informando que o destinatário não foi encontrado
5. WHEN um usuário tenta transferir para si mesmo, THE Sistema SHALL rejeitar a operação e retornar erro de validação
6. THE Sistema SHALL executar débito e crédito de forma atômica utilizando transação de banco de dados

### Requisito 6: Reversão de Operações

**User Story:** Como um usuário autenticado, eu quero reverter uma transação, para que eu possa corrigir erros ou inconsistências.

#### Critérios de Aceitação

1. WHEN um usuário solicita a reversão de uma transação própria, THE Sistema SHALL criar uma transação inversa, restaurando o saldo das carteiras envolvidas
2. WHEN um usuário solicita a reversão de uma transferência enviada, THE Sistema SHALL debitar o valor da carteira do destinatário e creditar na carteira do remetente original
3. WHEN um usuário solicita a reversão de um depósito, THE Sistema SHALL subtrair o valor da carteira do usuário
4. IF uma reversão resultaria em saldo negativo para qualquer carteira envolvida, THEN THE Sistema SHALL permitir a reversão e registrar o saldo negativo resultante
5. WHEN uma transação já foi revertida anteriormente, THE Sistema SHALL rejeitar a nova reversão e retornar erro informando que a transação já foi revertida
6. THE Sistema SHALL executar a reversão de forma atômica utilizando transação de banco de dados
7. IF ocorre uma falha durante qualquer operação financeira, THEN THE Sistema SHALL realizar rollback completo garantindo consistência dos dados

### Requisito 7: Segurança e Proteção de Dados

**User Story:** Como um usuário, eu quero que meus dados e operações financeiras estejam protegidos, para que eu tenha confiança na segurança do sistema.

#### Critérios de Aceitação

1. THE Sistema SHALL validar e sanitizar todos os dados de entrada antes de processá-los
2. THE Sistema SHALL utilizar consultas parametrizadas para todas as interações com o banco de dados
3. THE Sistema SHALL registrar tentativas de acesso não autorizado em log de segurança
4. THE Sistema SHALL aplicar rate limiting nas rotas de autenticação e operações financeiras
5. WHEN uma requisição não contém token de autenticação válido, THE Sistema SHALL negar acesso e retornar erro 401

### Requisito 8: Ambiente Containerizado com Docker

**User Story:** Como um desenvolvedor, eu quero que a aplicação execute em containers Docker, para que o ambiente seja reproduzível e fácil de configurar.

#### Critérios de Aceitação

1. THE Sistema SHALL fornecer um arquivo docker-compose.yml que configure todos os serviços necessários (aplicação PHP/Laravel, banco de dados SQL, cache)
2. THE Sistema SHALL fornecer um Dockerfile que construa a imagem da aplicação com todas as dependências
3. WHEN um desenvolvedor executa docker-compose up, THE Sistema SHALL inicializar todos os serviços e tornar a aplicação acessível
4. THE Sistema SHALL utilizar variáveis de ambiente para configurações sensíveis (credenciais de banco, chaves de aplicação)
5. THE Sistema SHALL fornecer volume persistente para dados do banco de dados

### Requisito 9: Testes Automatizados

**User Story:** Como um desenvolvedor, eu quero testes automatizados, para que eu possa garantir a qualidade e a corretude do sistema.

#### Critérios de Aceitação

1. THE Sistema SHALL possuir testes unitários para regras de negócio (validação de saldo, cálculos financeiros)
2. THE Sistema SHALL possuir testes de integração para fluxos completos (cadastro, autenticação, depósito, transferência, reversão)
3. FOR ALL transações válidas de depósito seguidas de consulta de saldo, depositar e depois consultar SHALL retornar o saldo atualizado corretamente (propriedade round-trip)
4. FOR ALL transferências válidas, o saldo total do sistema (soma de todas as carteiras) SHALL permanecer constante (propriedade de invariante — conservação de saldo)
5. FOR ALL reversões de transação seguidas de nova reversão da mesma transação, a segunda reversão SHALL ser rejeitada (propriedade de idempotência)

### Requisito 10: Observabilidade

**User Story:** Como um desenvolvedor, eu quero monitorar o comportamento da aplicação, para que eu possa identificar problemas e analisar o desempenho.

#### Critérios de Aceitação

1. THE Sistema SHALL registrar logs estruturados para todas as operações financeiras (depósitos, transferências, reversões)
2. THE Sistema SHALL registrar logs de erro com contexto suficiente para diagnóstico (stack trace, dados da requisição, identificador do usuário)
3. WHEN uma operação financeira falha, THE Sistema SHALL registrar o evento com nível de severidade adequado
