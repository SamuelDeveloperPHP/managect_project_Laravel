<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LGPD (Lei 13.709/2018)
    |--------------------------------------------------------------------------
    */

    // Quem responde pelo tratamento e por onde o titular fala com a gente (aparece na Política de Privacidade).
    'controller_name' => env('PRIVACY_CONTROLLER_NAME', 'NexoCore Tecnologia'),
    'contact_email' => env('PRIVACY_CONTACT_EMAIL'),
    'dpo_name' => env('PRIVACY_DPO_NAME'),

    // Versão dos textos legais. Ao mudar o texto de forma relevante, troque a versão: todos precisam aceitar de novo.
    'terms_version' => '2026-10',

    // Exige que cada pessoa aceite a versão vigente antes de usar o sistema. Em produção vem LIGADO.
    'terms_required' => (bool) env('PRIVACY_TERMS_REQUIRED', env('APP_ENV') === 'production'),

    // Em produção a publicação só é liberada depois que alguém (advogado/DPO) revisou os textos e confirmou aqui.
    'policy_reviewed' => (bool) env('PRIVACY_POLICY_REVIEWED', false),

    // Retenção dos registros de atividade (auditoria).
    // O Marco Civil da Internet (art. 15) pede guardar os registros de acesso por pelo menos 6 meses.
    'ip_retention_days' => (int) env('PRIVACY_IP_RETENTION_DAYS', 365),
    'audit_retention_days' => (int) env('PRIVACY_AUDIT_RETENTION_DAYS', 730),

    // Quantos registros de atividade entram na exportação dos dados de uma pessoa.
    'export_audit_limit' => 5000,
];
