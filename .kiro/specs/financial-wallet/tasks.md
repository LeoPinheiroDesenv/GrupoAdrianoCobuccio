# Implementation Plan: Financial Wallet

## Overview

Implementação incremental da carteira financeira digital com PHP/Laravel, MySQL e Docker. Cada tarefa constrói sobre a anterior, começando pela infraestrutura Docker, passando pela autenticação, operações financeiras e finalizando com observabilidade e testes de integração.

## Tasks

- [x] 1. Set up Docker environment and Laravel project scaffolding
  - [x] 1.1 Create Dockerfile for PHP/Laravel application with PHP-FPM, Composer, and required extensions
    - Configure PHP 8.2+ with extensions: pdo_mysql, bcmath, redis
    - Install Composer dependencies
    - _Requirements: 8.1, 8.2_
  - [x] 1.2 Create docker-compose.yml with all services (app, nginx, mysql, redis)
    - Configure persistent volume for MySQL data
    - Use environment variables for sensitive configs (DB credentials, APP_KEY, JWT_SECRET)
    - Expose application on accessible port
    - _Requirements: 8.1, 8.3, 8.4, 8.5_
  - [x] 1.3 Initialize Laravel project with required packages
    - Install `tymon/jwt-auth` for JWT authentication
    - Configure database connection, Redis cache, and queue settings
    - _Requirements: 8.3_

- [x] 2. Implement database migrations and Eloquent models
  - [x] 2.1 Create migration for `users` table
    - Fields: id, name, email (unique), cpf (unique), password, timestamps
    - _Requirements: 1.1, 1.2, 1.3_
  - [x] 2.2 Create migration for `wallets` table
    - Fields: id, user_id (FK unique), balance decimal(15,2) default 0, timestamps
    - _Requirements: 1.1_
  - [x] 2.3 Create migration for `transactions` table
    - Fields: id, uuid (unique), wallet_id (FK), target_wallet_id (FK nullable), type (enum), amount decimal(15,2), reversed_transaction_id (FK nullable), is_reversed (boolean default false), timestamps
    - Add indexes on wallet_id, uuid, reversed_transaction_id
    - _Requirements: 4.4, 5.6, 6.1_
  - [x] 2.4 Create Eloquent models (User, Wallet, Transaction) with relationships
    - User hasOne Wallet, Wallet hasMany Transactions
    - Transaction belongs to Wallet, optionally belongs to target Wallet
    - _Requirements: 1.1, 3.1, 3.2_

- [x] 3. Implement user registration and authentication
  - [x] 3.1 Create RegisterController with FormRequest validation
    - Validate name, email (unique), cpf (unique, 11 digits), password (min 8 chars)
    - Hash password with bcrypt, create user + wallet atomically
    - Return user data and JWT token on success
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
  - [x] 3.2 Create AuthController (login, logout)
    - Login: validate credentials, return JWT token; generic error on failure
    - Logout: invalidate current JWT token
    - _Requirements: 2.1, 2.2, 2.5_
  - [x] 3.3 Configure JWT middleware and route protection
    - Apply jwt.auth middleware to protected routes
    - Return 401 on expired/invalid tokens
    - _Requirements: 2.3, 2.4, 7.5_
  - [x] 3.4 Write unit tests for registration and authentication
    - Test successful registration creates user + wallet
    - Test duplicate email/cpf rejection
    - Test login with valid/invalid credentials
    - Test protected route access without token returns 401
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.4_

- [x] 4. Checkpoint - Verify Docker environment and auth flow
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement wallet operations (balance, statement, deposit)
  - [x] 5.1 Create WalletController with balance and statement endpoints
    - GET /api/wallet/balance returns current user balance
    - GET /api/wallet/statement returns transactions ordered by date desc with type, amount, date, status
    - _Requirements: 3.1, 3.2, 3.3_
  - [x] 5.2 Implement WalletService::deposit with atomic transaction
    - Validate amount > 0, reject otherwise with validation error
    - Wrap in DB::transaction: increment wallet balance, create Transaction record (type: deposit, uuid generated)
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  - [x] 5.3 Write property test for deposit round-trip consistency
    - **Property 1: Deposit Round-Trip** — For all valid deposit amounts, depositing and then querying balance SHALL return previous balance + deposit amount
    - **Validates: Requirements 4.1, 9.3**
  - [x] 5.4 Write unit tests for deposit operations
    - Test successful deposit updates balance
    - Test deposit with zero/negative amount is rejected
    - Test deposit transaction record is created correctly
    - _Requirements: 4.1, 4.2, 4.4_

- [x] 6. Implement transfer between users
  - [x] 6.1 Implement WalletService::transfer with atomic transaction
    - Validate: amount > 0, receiver exists, sender != receiver, sender has sufficient balance
    - Wrap in DB::transaction: debit sender, credit receiver, create two Transaction records (transfer_sent, transfer_received)
    - Use SELECT FOR UPDATE or pessimistic locking on wallets to prevent race conditions
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_
  - [x] 6.2 Create TransferController with FormRequest validation
    - Validate receiver_id/email exists, amount is positive
    - Return appropriate error messages for insufficient balance, self-transfer, invalid receiver
    - _Requirements: 5.3, 5.4, 5.5_
  - [x] 6.3 Write property test for balance conservation on transfer
    - **Property 2: Balance Conservation** — For all valid transfers, the total sum of all wallet balances in the system SHALL remain constant
    - **Validates: Requirements 5.2, 5.6, 9.4**
  - [x] 6.4 Write unit tests for transfer operations
    - Test successful transfer debits sender and credits receiver
    - Test insufficient balance rejection
    - Test self-transfer rejection
    - Test transfer to non-existent user rejection
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 7. Implement transaction reversal
  - [x] 7.1 Implement WalletService::reverse with atomic transaction
    - Validate: transaction belongs to user, transaction not already reversed
    - For deposit reversal: subtract amount from user wallet
    - For transfer reversal: debit receiver wallet, credit sender wallet
    - Mark original transaction as is_reversed=true, create new Transaction (type: reversal) with reversed_transaction_id
    - Allow negative balance after reversal
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_
  - [x] 7.2 Create ReversalController with route POST /api/wallet/reverse/{transaction_id}
    - Validate transaction exists and belongs to authenticated user
    - Return error if already reversed
    - _Requirements: 6.5_
  - [x] 7.3 Write property test for reversal idempotency
    - **Property 3: Reversal Idempotency** — For all reversed transactions, attempting a second reversal of the same transaction SHALL be rejected
    - **Validates: Requirements 6.5, 9.5**
  - [x] 7.4 Write unit tests for reversal operations
    - Test deposit reversal subtracts from balance
    - Test transfer reversal restores both wallets
    - Test double reversal is rejected
    - Test reversal allows negative balance
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 8. Checkpoint - Verify all financial operations
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Implement security measures and rate limiting
  - [x] 9.1 Configure rate limiting middleware
    - Apply 5 req/min on login route
    - Apply 60 req/min on financial operation routes
    - Use Redis as rate limit store
    - _Requirements: 7.4_
  - [x] 9.2 Add input validation and sanitization across all controllers
    - Ensure all FormRequests validate and sanitize inputs
    - Verify parameterized queries are used (Eloquent default)
    - _Requirements: 7.1, 7.2_

- [x] 10. Implement observability (structured logging)
  - [x] 10.1 Configure structured JSON logging via Monolog
    - Set LOG_CHANNEL to output JSON-formatted logs
    - Include context: user_id, operation type, amount, transaction_id
    - _Requirements: 10.1, 10.2_
  - [x] 10.2 Add logging to all financial operations and error paths
    - Log successful deposits, transfers, reversals with relevant context
    - Log failures with severity level, stack trace, and request data
    - Log unauthorized access attempts
    - _Requirements: 10.1, 10.2, 10.3, 7.3_

- [x] 11. Write integration tests for end-to-end flows
  - [x] 11.1 Write integration test for complete user lifecycle
    - Register → Login → Deposit → Transfer → Check Balance → Reverse → Verify final state
    - _Requirements: 9.2_
  - [x] 11.2 Write integration test for concurrent transfer safety
    - Test atomic behavior under concurrent requests
    - _Requirements: 5.6, 6.6, 9.2_

- [x] 12. Final checkpoint - Full test suite and Docker verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from Requirements 9.3, 9.4, 9.5
- All financial operations use DB::transaction for atomicity (Requirements 5.6, 6.6, 6.7)
- PHP/Laravel is the implementation language as specified in the design
