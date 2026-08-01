# Relatório de Uso de Inteligência Artificial (AI-Assisted Development)

Conforme diretrizes do desafio, este documento relata a integração de ferramentas de IA generativa no meu fluxo de trabalho para escalar produtividade.

## Arquitetura (não acoplar)

1. **Upload** → `UploadController` → `UploadFileUseCase` → grava no **Outbox (SQLite)** →
   responde `202`. Nunca chama o RabbitMQ direto.
2. **outbox-relay** → único que publica no RabbitMQ, lendo o Outbox.
3. **worker** → consome a fila, processa em streaming e grava no **Redis**.

Consulta (`GetOrdersUseCase`) lê só do Redis.

## Convenções

- DIP sempre: UseCases dependem de `App/Contracts/`, nunca de implementação concreta.
- `declare(strict_types=1);` em todo arquivo novo.
- Sem framework — requisito do desafio original.
- Streaming obrigatório no arquivo legado (nunca `file_get_contents`/`SplFileObject` nele).
- Parsing é posicional (largura fixa), não CSV.

## Antes de terminar uma tarefa

- `vendor/bin/phpunit` passando.
- Mudou `RabbitMQTopology.php`? Testar manualmente contra um RabbitMQ real (sem teste automatizado disso).
- README precisa continuar batendo com o código.

## Uso de IA neste projeto

1. **Pivotamento arquitetural (mensageria):** debate sobre evoluir de processamento síncrono
   para assíncrono — a IA apoiou a decisão de introduzir o RabbitMQ e refinar a resiliência
   (Outbox, Retries, DLQ).
2. **Dependências e contratos:** validação da estrutura do `composer.json` (ex: `"php": "^8.1"`,
   `"ext-pdo_sqlite"`) pra garantir portabilidade e falha rápida se faltar extensão no host.
3. **Expressões e agregação:** revisão da lógica de fatiamento (`substr`) das linhas
   *fixed-width*, e do algoritmo que agrega cada linha (1 produto por linha) na estrutura
   aninhada do JSON de saída (usuário → pedidos → produtos), persistida no Redis.

A decisão final de arquitetura, a modelagem e a orquestração do código são de responsabilidade
de quem desenvolveu o projeto.