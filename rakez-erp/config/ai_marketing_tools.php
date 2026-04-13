<?php

return [
    /*
    | Lead score band thresholds for marketing analytics (rule-based, not CRM truth).
    | Adjust here instead of hardcoding SQL in tools.
    */
    'lead_quality_score_thresholds' => [
        'hot' => 80,
        'warm' => 50,
    ],
];
