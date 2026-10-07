<?php

return [

    // Devem apontar para executáveis locais confiáveis; o envio de PDF falha fechado se algum faltar.
    'pdf_validator_binary' => env('PDF_VALIDATOR_BINARY'),
    'antivirus_binary' => env('ANTIVIRUS_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Autenticação em dois fatores (app autenticador / TOTP)
    |--------------------------------------------------------------------------
    |
    | Quando exigida, quem tem um dos papéis abaixo só consegue usar o sistema depois de ativar o segundo fator
    | (é levado direto à tela de ativação). Em produção a exigência vem LIGADA por padrão; em desenvolvimento e
    | nos testes vem desligada para não atrapalhar. Qualquer usuário pode ativar por conta própria no Perfil.
    |
    */

    'two_factor_required' => (bool) env('TWO_FACTOR_REQUIRED', env('APP_ENV') === 'production'),

    'two_factor_roles' => ['admin', 'master'],

    // Nome mostrado no app autenticador.
    'two_factor_issuer' => env('TWO_FACTOR_ISSUER', 'Trilha+'),

    // Quantos códigos de recuperação são gerados de cada vez.
    'recovery_codes' => 8,
];
