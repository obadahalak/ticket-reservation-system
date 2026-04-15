import { check, sleep } from 'k6';
import http from 'k6/http';

export const options = {

    stages: [
        { duration: '2s', target: 10 },  // Quick warm up
        { duration: '10s', target: 3000 },
        { duration: '5s', target: 0 },     // Scale down
    ],
    thresholds: {

        http_req_failed: ['rate<0.01'],

        http_req_duration: ['p(95)<500'],
    },
};

const BASE_URL = 'http://localhost:8080/api';

export default function () {
    const url = `${BASE_URL}/events/1/reserve`;


    const payload = JSON.stringify({
        event_id: 1,
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

    check(res, {
        'status is 200 or 422': (r) => r.status === 200 || r.status === 422,
        'transaction is fast': (r) => r.timings.duration < 400,
    });


    sleep(0.1);
}