<?php

return [
    // These must point to trusted local executables; uploads fail closed if either is absent.
    'pdf_validator_binary' => env('PDF_VALIDATOR_BINARY'),
    'antivirus_binary' => env('ANTIVIRUS_BINARY'),
];
