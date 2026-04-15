# 🎟️ High-Concurrency Ticket Reservation System

A production-grade ticket reservation API built with **Laravel**, **Redis**, and **MySQL**, designed to handle massive traffic spikes (e.g., concert ticket drops) without overselling or data corruption.

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Tech Stack](#tech-stack)
- [API Reference](#api-reference)
- [Concurrency Strategy](#concurrency-strategy)
- [Performance Testing](#performance-testing)

---

## Overview

When thousands of users hit "Buy" at the exact same moment, traditional database-only approaches crumble under contention.  
This system solves that problem with a **two-phase reservation architecture**:

1. **Redis Gate** — An atomic `DECR` on a Redis counter instantly accepts or rejects the request in microseconds.
2. **Queue Persistence** — Accepted requests are dispatched to a background job that writes the reservation to MySQL inside a pessimistic-locked transaction.

The result: **zero overselling**, **sub-millisecond availability checks**, and **graceful degradation** under extreme load.

---

## Architecture

```
┌──────────────┐       ┌──────────────────────────────────────────────────────────────┐
│   Client     │       │                        Laravel API                           │
│  (Browser /  │──────▶│                                                              │
│   k6 / App)  │       │  ┌────────────────┐   ┌──────────────────┐   ┌───────────┐  │
└──────────────┘       │  │ Idempotency    │──▶│ Rate Limiter     │──▶│ Controller│  │
                       │  │ Middleware     │   │ Middleware       │   │           │  │
                       │  └────────────────┘   └──────────────────┘   └─────┬─────┘  │
                       │                                                    │        │
                       │                         ┌──────────────────────────┘        │
                       │                         ▼                                   │
                       │              ┌─────────────────────┐                        │
                       │              │ TicketAvailability   │                        │
                       │              │ Service              │                        │
                       │              │ (Redis DECR/INCR)    │                        │
                       │              └──────────┬──────────┘                        │
                       │                         │                                   │
                       │                    ┌────┴────┐                              │
                       │                    ▼         ▼                              │
                       │              ✅ Accept   ❌ Reject (422)                    │
                       │                    │                                        │
                       │                    ▼                                        │
                       │         ┌────────────────────┐                              │
                       │         │ ProcessReservation  │                              │
                       │         │ (Queued Job)        │                              │
                       │         └─────────┬──────────┘                              │
                       └───────────────────┼──────────────────────────────────────────┘
                                           │
                          ┌────────────────┼─────────────────┐
                          ▼                                  ▼
                   ┌─────────────┐                    ┌─────────────┐
                   │    MySQL    │                    │    Redis     │
                   │ (Persistent │                    │ (Ticket      │
                   │  Storage)   │                    │  Counters)   │
                   └─────────────┘                    └─────────────┘
```

---

## Tech Stack

| Layer            | Technology                     |
| ---------------- | ------------------------------ |
| **Framework**    | Laravel 12 (PHP 8.4)           |
| **Auth**         | Laravel Sanctum (API Tokens)   |
| **Cache / Lock** | Redis (Alpine)                 |
| **Database**     | MySQL 8.0                      |
| **Web Server**   | Nginx (Alpine) + PHP-FPM       |
| **Containers**   | Docker & Docker Compose        |
| **Load Testing** | Grafana k6                     |

---

### Prerequisites

- Docker & Docker Compose
- [k6](https://k6.io/) *(optional, for load testing)*

### 1. Clone & Boot

```bash
git clone <repo-url>
cd CDC

docker-compose up -d --build
```

### 2. Install Dependencies & Migrate

```bash
docker-compose exec app composer install

docker-compose exec app php artisan key:generate

docker-compose exec app php artisan migrate --seed
```

The `--seed` flag populates 10 real-world events and initializes their ticket counters in Redis.

### 3. Start the Queue Worker

```bash
docker-compose exec app php artisan queue:work --tries=2
```

---

## API Reference


#### Reserve Ticket — Request Example

```bash
curl -X POST http://localhost:8080/api/events/1/reserve \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: unique-request-id-123" \
  -d '{"event_id": 1, "user_id": 42}'
```

#### Possible Responses

| Status | Body                                          | Meaning                          |
| ------ | --------------------------------------------- | -------------------------------- |
| `200`  | `{"message": "Your purchase is being processed."}` | Ticket reserved, processing via queue |
| `200`  | `{"message": "your request already handled."}` | Duplicate request (idempotent)   |
| `422`  | `{"message": "Sorry — this event is sold out."}` | No tickets remaining            |
| `422`  | `{"error": "You have already reserved this ticket"}` | Rate limited (cooldown active)  |

---

## Concurrency Strategy

The system employs a **multi-layered defense** to guarantee correctness under extreme concurrency:

### Layer 1 — Idempotency Middleware

```
Idempotency-Key header → Redis SET with 24h TTL
```

Every reservation request **must** include an `Idempotency-Key` header. If the same key is seen again, the original response is returned immediately. This prevents accidental double-purchases from network retries, impatient users, or client bugs.

### Layer 2 — Rate Limiter Middleware

```
Redis SET NX (user + event) → 10-second cooldown
```

A per-user, per-event sliding window prevents a single user from spamming the reservation endpoint. Uses Redis `SET ... NX EX` for an atomic lock with automatic expiry.

### Layer 3 — Atomic Ticket Counter (Redis)

```
Redis DECR tickets:event:{id}
  → remaining >= 0 → ✅ Accept
  → remaining < 0  → INCR (rollback) → ❌ Reject
```

The `TicketAvailabilityService` performs an atomic `DECR` on a Redis key. This is a **single-threaded, O(1) operation** — no matter how many concurrent requests arrive, Redis serializes them and each one gets a consistent, race-free answer.

- **Accept**: Counter decremented, request proceeds to queue.
- **Reject**: Counter rolled back with `INCR`, 422 returned immediately.

### Layer 4 — Queued Persistence (MySQL)

```
ProcessReservation Job → DB::transaction + lockForUpdate()
```

Accepted reservations are dispatched to a **background queue job** that:

1. Opens a database transaction with `lockForUpdate()` on the Event row.
2. Double-checks `available_tickets > 0` (defense-in-depth).
3. Creates the `Reservation` record.
4. Decrements `available_tickets` in MySQL.

If the job **fails** (e.g., DB down), the Redis counter is automatically **rolled back** via the `failed()` method, ensuring the ticket returns to the pool.

### Why This Works

```
Traditional approach:           This system:
───────────────────             ────────────────
SELECT ... FOR UPDATE           Redis DECR (μs)
  ↓ (blocks all others)          ↓ (non-blocking)
INSERT reservation              Queue → async INSERT
  ↓                               ↓
COMMIT                          DB transaction (isolated)
  ↓                               ↓
~200ms per request              ~1ms gate + async write
```

---

## Performance Testing

The project includes a [k6](https://k6.io/) load test script to simulate a traffic spike:

```bash
k6 run purchase-test.js
```

### Test Profile

| Phase    | Duration | Virtual Users |
| -------- | -------- | ------------- |
| Warm-up  | 2s       | 0 → 10        |
| Spike    | 10s      | 10 → 3000     |
| Cooldown | 5s       | 3000 → 0      |

### Thresholds

- **HTTP failures**: < 1%
- **p(95) response time**: < 500ms



---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
