<?php

return [
    'driver' => env('LLM_DRIVER', 'fake'),
    'provider' => env('LLM_PROVIDER', 'gemini'),
    'model' => env('LLM_MODEL', 'gemini-2.5-flash'),
    // Token/Reasoning controls
    'max_output_tokens' => env('LLM_MAX_OUTPUT_TOKENS', 800),
    // For Gemini, set to 0 to disable thinking budget (prevents token overuse)
    'thinking_budget' => env('LLM_THINKING_BUDGET', 0),
];
