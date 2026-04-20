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


### 3. Start the Queue Worker

```bash
docker-compose exec app php artisan queue:work
```

---

## API Reference


#### Reserve Ticket — Request Example

```bash
curl -X POST http://localhost:8080/api/v2/events/1/reserve \
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

## Concurrency Strategy: Why This Works

The system utilizes a **four-layer defense** to handle massive traffic spikes while ensuring absolute data integrity. By moving the "heavy lifting" away from the database, we prevent the system from slowing down under pressure.

### Layer 1: The Request Filter
Before a request even reaches the business logic, we check its unique signature. If a user clicks the button multiple times—due to a bad connection or impatience—the system recognizes the signature and only processes the first attempt.
> **Implementation:** `App\Http\Middleware\IdempotencyCheck`

### Layer 2: The Fair-Play Guard
To prevent a single user or a malicious bot from taking all the tickets in a few milliseconds, we enforce a brief cooldown period. This ensures that the tickets are distributed fairly among as many real people as possible.
> **Implementation:** `App\Http\Middleware\ReservationRateLimiter`

### Layer 3: The Ultra-Fast Gatekeeper
This is the heart of the system. Instead of checking ticket availability in a slow database, we use a high-speed, memory-based gatekeeper.
- **The Logic:** It acts like a digital turnstile that only allows a specific number of people through. 
- **The Benefit:** It processes requests in sequence so quickly that thousands of users can be checked in less than a second.
> **Implementation:** `App\Http\Services\TicketAvailabilityService`

### Layer 4: The Background Accountant
Once the Gatekeeper confirms a ticket is available, the user receives an instant "Success" message. The slow process of officially recording the sale in the main database is handed off to a background worker.
- If the background recording fails for any reason, the ticket is automatically returned to the pool.
> **Implementation:** `App\Jobs\ProcessReservation`

---

### Efficiency Comparison

| Step | Traditional Database Method | This High-Concurrency Method |
| :--- | :--- | :--- |
| **Availability Check** | Locks the database row (Slow/Blocks others) | Memory-speed turnstile (Instant/Non-blocking) |
| **User Experience** | User waits for the database to finish writing | User gets an immediate confirmation |
| **System Health** | High risk of crashing during spikes | Remains fast and stable under heavy pressure |

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
# 🚀 Performance Comparison: V1 (Naive) vs. V2 (Enhanced)

Below is a detailed comparison between the standard synchronous implementation and the optimized version using Redis atomicity and background processing.

### 📊 Metric Comparison

| Metric | 🐢 V1: Native (Naive) | 🚀 V2: Enhanced (Redis + Queues) | Improvement |
| :--- | :--- | :--- | :--- |
| **Throughput (Req/sec)** | **56.03 req/s** | **699.31 req/s** | **+1,148%** Faster |
| **Total Requests Processed** | 2,914 | 20,440 | **x7.0** Capacity |
| **Peak Virtual Users (VUs)** | 7,000 | 7,000 | Same Load |
| **P(95) Response Time** | **41.23 seconds** | **11.46 seconds** | **-72.2%** Latency |
| **Avg. Response Time** | 25.76 seconds | 4.57 seconds | **-82.2%** Latency |
| **Data Throughput (Recv)** | 1.0 MB | 7.3 MB | **x7.3** Data Volume |
| **Server Errors (500s/0)** | 0.00% | 0.00% | Both Stable |
| **Status Check (200/422)** | 100% | 100% | Both Reliable |

---


### Updated Strategic Summary

> **V2 is the clear winner**, specifically because it eliminates **Database Row Contention**.

#### 🐢 The V1 (Naive) Problem:
*   **Bottleneck:** **Row-level Locking.** By decrementing a column inside a database transaction, you force thousands of concurrent users to wait for a single row-lock. 
*   **Sequential Processing:** The database is forced to process reservations one-by-one for that event, effectively making your high-concurrency system behave like a single-lane road.

#### 🚀 The V2 (Enhanced) Advantage:
*   **Memory-Speed Counters:** Redis handles the decrement in RAM without heavy row locks. It can handle tens of thousands of decrements per second.
*   **Decoupled Writing:** By using a Background Job the web request doesn't have to wait for the slow Disk I/O of the database.
*   **Result:** You get the same level of safety (no overselling) but with **10x higher throughput**.

**Conclusion:** V2 proves that moving the "Counter" logic from a Locked Database Row to an Atomic Redis Key is the secret to true high-concurrency scaling.