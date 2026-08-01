# Habilidades e Padrões Aplicados

## 1. Padrões de Arquitetura e Mensageria

- **Containerização:** Docker Compose orquestrando 5 serviços (`app`, `worker`, `outbox-relay`,
  `rabbitmq`, `redis`), todos compartilhando o mesmo bind mount do código-fonte — garantindo que
  o arquivo enviado via upload e o banco de controle (SQLite) fiquem visíveis para todos os
  containers que precisam deles.
- **Producer/Consumer com RabbitMQ:** `outbox-relay` publica, `worker` consome — desacoplados
  entre si e da requisição HTTP.
- **Outbox Pattern:** separa a transação de persistência local (SQLite) da publicação de eventos
  no broker, garantindo que nenhum evento se perca mesmo se o RabbitMQ estiver indisponível no
  momento do upload.
- **Retry Policy + Dead Letter Exchange/Queue:** fila de retry com TTL (bounce automático via
  recursos nativos do RabbitMQ) e fila terminal de DLQ para isolar mensagens que esgotaram as
  tentativas — validado contra um broker real durante o desenvolvimento.

## 2. Clean Code & PHP Moderno (8.1)

- `declare(strict_types=1);` em toda a base de código.
- **Constructor Property Promotion** e propriedades `readonly` (ex: `App\Http\Response`) para
  imutabilidade de objetos de transporte de dados.
- **Generators (`yield`)** para streaming de arquivos grandes com memória O(1).

## 3. SOLID & Design Patterns

- **Single Responsibility Principle:** Controllers validam e delegam; UseCases concentram regra
  de negócio; Repositories concentram acesso a dados; o parser posicional é uma classe própria,
  isolada de I/O.
- **Dependency Inversion Principle**, aplicado de forma real (não apenas declarada) em dois
  pontos independentes:
  - `App\Contracts\OrderRepositoryInterface` — os UseCases de pedido dependem dela, não de
    `RedisOrderRepository` diretamente. Trocar Redis por outro banco significa criar uma nova
    classe que implemente a interface.
  - `App\Contracts\OutboxRepositoryInterface` — mesma lógica para o Outbox. A implementação
    concreta (`SqliteOutboxRepository`) usa apenas SQL ANSI padrão via PDO, então trocar SQLite
    por Postgres/MySQL no futuro não exige alterar nenhuma regra de negócio.

## 4. Testes Automatizados

- **Unitários** cobrindo: o motor de recorte posicional (`FixedWidthLineParserTest`) — inclusive
  linhas com tamanho errado, campos numéricos corrompidos e datas de calendário inválidas; o
  streaming e a resiliência a linhas corrompidas do `ProcessFileUseCase`; a agregação e os
  índices de busca do `RedisOrderRepository`; o ciclo transacional do
  `SqliteOutboxRepository`; e a camada HTTP dos Controllers (via mocks das interfaces).
- **Validação manual de integração** contra Redis, SQLite e RabbitMQ reais durante o
  desenvolvimento (não apenas assumida) — incluindo o cenário completo de falha transitória →
  retry automático → esgotamento → DLQ terminal.