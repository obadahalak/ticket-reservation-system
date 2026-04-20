import { check, sleep } from 'k6';
import http from 'k6/http';
import { Rate } from 'k6/metrics';

const serverErrors = new Rate('server_errors');

export const options = {

    stages: [
        { duration: '2s', target: 10 },  // Quick warm up
        { duration: '15s', target: 7000 },
        { duration: '5s', target: 0 },     // Scale down
    ],
    thresholds: {

        'checks{name:status_ok}': ['rate>0.99'],
        'http_req_duration': ['p(95)<500'],
        'server_errors': ['rate<0.01'],
    },
};

const BASE_URL = 'http://localhost:8080/api/v1';

export default function () {
    const url = `${BASE_URL}/events/5000/reserve`;


    const payload = JSON.stringify({
        event_id: 5000,
        user_id: __VU
    });


    const params = {
        headers: {
            'Idempotency-Key': 'k6-vu-:' + __VU,
            // 'Authorization': 'Bearer 1|Ic7qrlCEpXswTcsSbn1mnJSqIqlHdEzzui0wSAKk1829da07',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Request-Source': 'K6-Performance-Test',
        },
    };


    const res = http.post(url, payload, params);
    serverErrors.add(res.status >= 500 || res.status === 0);
    check(res, {
        'status_ok': (r) => r.status === 200 || r.status === 422,
        // 'transaction is fast': (r) => r.timings.duration < 1200,
    }, { name: 'status_ok' }); // Third argument assigns the tag


    sleep(0.1);
}