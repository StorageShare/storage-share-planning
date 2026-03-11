<?php

return [
    // Maximum script execution time (in seconds) to apply for HTTP requests.
    // You can override this via ENV: EXECUTION_TIME_LIMIT
    'max_execution_time' => env('EXECUTION_TIME_LIMIT', 300),
];
