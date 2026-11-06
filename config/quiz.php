<?php

return [

    /*
    | Password for the lecturer/admin area (/admin). Set ADMIN_PASSWORD in .env.
    */
    'admin_password' => env('ADMIN_PASSWORD', ''),

    /*
    | Seconds of grace after the timer hits zero during which a submission
    | is still accepted as-is (covers slow networks during auto-submit).
    */
    'grace_seconds' => (int) env('QUIZ_GRACE_SECONDS', 60),

    /*
    | AI proctoring on the candidate's own device (nothing is recorded).
    | Each time talking or a head turn is detected, talk_penalty or
    | head_turn_penalty marks are deducted; the exam carries on. Repeats of
    | the same violation within cooldown_seconds count once.
    */
    'proctoring' => [
        'talk_penalty' => 1,
        'head_turn_penalty' => 1,
        'cooldown_seconds' => 6,
    ],

    /*
    | Live monitoring. Candidates on one monitor page, and how long after the
    | last heartbeat a candidate still counts as online.
    */
    'monitor_per_page' => (int) env('QUIZ_MONITOR_PER_PAGE', 50),
    'online_seconds' => 15,

    /*
    | WebRTC ICE servers. On one LAN no server is needed; STUN helps across
    | home routers. Once deployed, candidates on mobile data, campus Wi-Fi or
    | other strict networks can only be reached through a TURN relay.
    | RTC_TURN_URLS takes a comma-separated list, e.g.
    | "turn:host:80,turn:host:443?transport=tcp,turns:host:443?transport=tcp".
    */
    'ice_servers' => array_values(array_filter([
        env('RTC_STUN_URLS', 'stun:stun.l.google.com:19302')
            ? ['urls' => array_map('trim', explode(',', env('RTC_STUN_URLS', 'stun:stun.l.google.com:19302')))]
            : null,
        env('RTC_TURN_URLS', env('RTC_TURN_URL'))
            ? [
                'urls' => array_map('trim', explode(',', env('RTC_TURN_URLS', env('RTC_TURN_URL')))),
                'username' => env('RTC_TURN_USERNAME'),
                'credential' => env('RTC_TURN_CREDENTIAL'),
            ]
            : null,
    ])),

    /*
    | Default values for settings editable on the admin dashboard.
    */
    'defaults' => [
        'quiz_title' => 'Level 400 Quiz',
        'duration_minutes' => 30,
        'questions_per_student' => 0, // 0 = all questions
        'quiz_open' => 0,
        'proctoring' => 1,
        'live_video' => 1, // stream candidates' cameras to the live monitor
    ],

];
