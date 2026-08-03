# Sistema de Integração Logística (ETL Assíncrono)

Uma API REST com arquitetura distribuída, desenvolvida para atuar como um processo ETL
(Extract, Transform, Load). O sistema processa arquivos posicionais de alta volumetria (2GB+)
via streaming, distribui o processamento com mensageria e retorna os dados estruturados e
normalizados em JSON.

## Tecnologias e Padrões

* **Linguagem:** PHP 8.1+ (puro, sem frameworks)
* **Mensageria / Filas:** RabbitMQ (com Retry Policy e Dead Letter Queue)
* **Banco de dados:**
  * **Redis** — armazenamento normalizado dos pedidos (read model de alto volume)
  * **SQLite** — Outbox Pattern (registro de uploads e eventos pendentes)
* **Infraestrutura:** Docker & Docker Compose (ou instalação manual)
* **Arquitetura:** Producer/Consumer, Background Processing, Outbox Pattern, SOLID

---

## Como Executar

### Opção 1: Docker

A infraestrutura foi containerizada: `app`, `worker`, `outbox-relay`, `rabbitmq` e `redis` sobem
juntos, sem necessidade de instalações manuais na máquina host.

1. Clone o repositório e acesse a pasta:
   ```bash
   git clone <seu-repositorio> && cd <nome-da-pasta>
   ```

2. Crie o arquivo `.env` a partir do exemplo:
   ```bash
   cp .env.example .env
   ```

3. Suba os containers:
   ```bash
   docker compose up --build
   ```

4. A API estará disponível em: `http://localhost:8000`

---

### Opção 2: Local (sem Docker)

Funciona sem Docker, mas é necessário instalar os serviços manualmente.

#### Pré-requisitos

| Componente | Versão mínima | Como verificar |
|------------|---------------|----------------|
| PHP | 8.1+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Redis | 6.x+ | `redis-cli ping` |
| RabbitMQ | 3.x+ | `rabbitmqctl status` |
| Extensão `pdo_sqlite` | - | `php -m | grep sqlite` |
| Extensão `predis` | - | instalada via Composer |

#### Passo a passo

1. Clone o repositório:
   ```bash
   git clone <seu-repositorio> && cd <nome-da-pasta>
   ```

2. Instale as dependências PHP:
   ```bash
   composer install
   ```

3. Crie o arquivo `.env` e configure os valores:
   ```bash
   cp .env.example .env
   ```

4. Edite o `.env` com os dados do seu ambiente:
   ```env
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   RABBITMQ_HOST=127.0.0.1
   RABBITMQ_PORT=5672
   RABBITMQ_USER=guest
   RABBITMQ_PASSWORD=guest
   SQLITE_DB_PATH=storage/database.sqlite
   ```

5. Abra **3 terminais** e rode cada comando:

   **Terminal 1 — API (HTTP):**
   ```bash
   php -S localhost:8000 -t public/
   ```

   **Terminal 2 — Worker (Consumer):**
   ```bash
   php bin/worker.php
   ```

   **Terminal 3 — Outbox Relay (Producer):**
   ```bash
   php bin/outbox_relay.php
   ```

6. A API estará disponível em: `http://localhost:8000`

---

## 📡 Endpoints da API

### Upload de arquivo

```bash
POST http://localhost:8000/api/upload
```

Envia um arquivo `.txt` (layout posicional, 95 caracteres por linha).

**Resposta (202 Accepted):**
```json
{
  "success": true,
  "message": "File received successfully and registered for asynchronous processing.",
  "upload_id": 1
}
```

### Consulta por ID do pedido

```bash
GET http://localhost:8000/api/orders?order_id=123
```

### Consulta por intervalo de datas

```bash
GET http://localhost:8000/api/orders?start_date=2021-12-01&end_date=2021-12-31
```

**Resposta (200 OK):**
```json
[
   {
      "user_id": 1,
      "name": "Zarelli",
      "orders": [
         {
            "order_id": 123,
             "total": 1024.48,
            "date": "2021-12-01",
            "products": [
               { "product_id": 111, "value": 512.24 },
               { "product_id": 122, "value": 512.24 }
            ]
         }
      ]
   }
]
```

### Paginação (intervalos grandes)

Para evitar respostas muito grandes, a API suporta paginação nos endpoints que retornam listas.

- Parâmetros: `page` (inteiro, padrão `1`) e `per_page` (inteiro, padrão `50`, máximo `500`).
- Exemplo de requisição:

```bash
GET http://localhost:8000/api/orders?start_date=2021-03-01&end_date=2021-03-31&page=2&per_page=50
```

Comportamento da resposta:
- **Corpo (body):** um array JSON contendo os usuários/pedidos da página (sem wrapper `data`).
- **Cabeçalhos (headers):** metadados de paginação são expostos via cabeçalhos HTTP:
   - `X-Page`: número da página atual
   - `X-Per-Page`: itens por página
   - `X-Total`: total de pedidos que casam com o filtro
   - `X-Total-Pages`: total de páginas

Exemplo com `curl` (mostrando cabeçalhos):

```bash
curl -i -sS -G 'http://localhost:8000/api/orders' \
   --data-urlencode 'start_date=2021-03-01' \
   --data-urlencode 'end_date=2021-03-31' \
   --data-urlencode 'page=2' \
   --data-urlencode 'per_page=50'
```

Resposta (exemplo):

HTTP/1.1 200 OK
X-Page: 2
X-Per-Page: 50
X-Total: 1234
X-Total-Pages: 25
Content-Type: application/json; charset=utf-8

[
   {
      "user_id": 78,
      "name": "Wade Mraz",
      "orders": [ ... ]
   },
   {
      "user_id": 79,
      "name": "Another User",
      "orders": [ ... ]
   }
]

Use `page` e `per_page` para navegar pelos resultados sem causar uso excessivo de memória.

---

## Testando com Insomnia

O Insomnia é um cliente HTTP gratuito para testar APIs. Siga os passos:

### 1. Instale o Insomnia

Baixe em: https://insomnia.rest/download

### 2. Configure as requisições

| Método | URL | Body Type | Descrição |
|--------|-----|-----------|-----------|
| `POST` | `http://localhost:8000/api/upload` | Form Multipart | Envia arquivo .txt |
| `GET` | `http://localhost:8000/api/orders?order_id=123` | - | Busca pedido por ID |
| `GET` | `http://localhost:8000/api/orders?start_date=2021-12-01&end_date=2021-12-31` | - | Busca por data |

### 3. Upload no Insomnia

1. Crie uma requisição `POST`
2. URL: `http://localhost:8000/api/upload`
3. Na aba **Body**, selecione **Form Multipart**
4. Adicione um campo:
   - Name: `file`
   - Type: **File**
   - Value: selecione seu arquivo `.txt`
5. Clique em **Send**

### 4. Consulta no Insomnia

1. Crie uma requisição `GET`
2. URL: `http://localhost:8000/api/orders?order_id=123`
3. Clique em **Send**

---

## Como Funciona

```
Upload API → SQLite (Outbox) → outbox-relay → RabbitMQ → worker → Redis
```

1. **Upload** → API valida, move o arquivo e grava evento no **Outbox (SQLite)** → responde
   `202` na hora, sem esperar o broker.
2. **outbox-relay** → publica o evento pendente no **RabbitMQ**.
3. **worker** → consome a fila, processa o arquivo em **streaming** (memória O(1)) e grava o
   resultado agregado no **Redis**.
4. Falha transitória? → **retry automático** (TTL 10s). Esgotou 3 tentativas? → **DLQ**.

---

## Testes

```bash
composer install
```

### Rodar todos os testes

```bash
composer test
```

### Rodar com saída detalhada (testdox)

```bash
composer test -- --testdox
```
