# Sistema de Integração Logística (ETL Assíncrono)

Uma API REST com arquitetura distribuída, desenvolvida para atuar como um processo ETL
(Extract, Transform, Load). O sistema processa arquivos posicionais de alta volumetria (2GB+)
via streaming, distribui o processamento com mensageria e retorna os dados estruturados e
normalizados em JSON.

## 🛠️ Tecnologias e Padrões

* **Linguagem:** PHP 8.1+ (puro, sem frameworks)
* **Mensageria / Filas:** RabbitMQ (com Retry Policy e Dead Letter Queue)
* **Banco de dados:**
  * **Redis** — armazenamento normalizado dos pedidos (read model de alto volume, o resultado do desafio)
  * **SQLite** — Outbox Pattern (registro de uploads e eventos pendentes, baixo volume)
* **Infraestrutura:** Docker & Docker Compose
* **Arquitetura:** Producer/Consumer, Background Processing, Outbox Pattern, SOLID

---

## 🚀 Como Executar o Projeto

A infraestrutura foi containerizada: `app`, `worker`, `outbox-relay`, `rabbitmq` e `redis` sobem
juntos, sem necessidade de instalações manuais na máquina host.

1. Clone o repositório e acesse a pasta:
   ```bash
   git clone <seu-repositorio> && cd <nome-da-pasta>
   ```

2. Suba os containers:
   ```bash
   docker compose up --build
   ```

3. Envie um arquivo (layout posicional, 95 caracteres por linha):
   ```bash
   curl -X POST http://localhost:8000/api/upload -F "file=@pedidos.txt"
   ```

4. Consulte os pedidos processados:
   ```bash
   curl "http://localhost:8000/api/orders?order_id=123"
   curl "http://localhost:8000/api/orders?start_date=2021-12-01&end_date=2021-12-31"
   ```

---

## 🧠 Como Funciona

1. **Upload** → API valida, move o arquivo e grava evento no **Outbox (SQLite)** → responde
   `202` na hora, sem esperar o broker.
2. **outbox-relay** → publica o evento pendente no **RabbitMQ**.
3. **worker** → consome a fila, processa o arquivo em **streaming** (memória O(1)) e grava o
   resultado agregado no **Redis**.
4. Falha transitória? → **retry automático** (TTL 10s). Esgotou 3 tentativas? → **DLQ**.

---

## ✅ Testes

```bash
composer install
vendor/bin/phpunit tests --testdox
```