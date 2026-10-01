# Preparação de segurança para produção

Esta lista complementa os controles implementados na aplicação. Ela não substitui revisão de infraestrutura, teste de invasão, avaliação jurídica ou plano de resposta a incidentes.

## Configuração obrigatória

- Defina `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` e uma `APP_KEY` exclusiva e protegida. Não copie `.env` entre ambientes nem publique segredos em repositórios, artefatos ou logs.
- Sirva somente por HTTPS. Configure `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` e cookies restritos ao domínio necessário. O código define esses padrões seguros quando `APP_ENV=production`, mas valores explícitos no `.env` têm precedência.
- Se houver proxy reverso ou balanceador, informe em `TRUSTED_PROXIES` somente seus IPs/CIDRs exatos e bloqueie acesso direto ao origin. Nunca confie em `X-Forwarded-For` de qualquer origem.
- Use Redis autenticado, privado e compartilhado entre as instâncias para cache e rate limiting: `CACHE_STORE=redis` e `RATE_LIMITER_STORE=redis`. O limiter propaga falhas do backend em vez de permitir login sem controle; configure alertas de indisponibilidade e teste esse cenário antes do deploy.
- Troque `root@localhost` por usuário MySQL exclusivo da aplicação, limitado ao schema ManageCT e às operações de runtime necessárias. Use uma credencial separada, restrita e temporária para migrations/deploy; a aplicação não deve ter `DROP`, `ALTER`, `GRANT`, `FILE`, `SHUTDOWN` ou privilégios globais. A credencial root foi observada no ambiente local e ainda não foi rotacionada.
- Mantenha o MySQL e Redis inacessíveis pela internet pública. Permita conexão apenas pela rede privada/origin da aplicação e faça rotação de credenciais após qualquer suspeita de exposição.

## Proteção de borda e disponibilidade

- Coloque a aplicação atrás de WAF/CDN ou proteção equivalente, com limites por IP/rota, mitigação de bots e proteção contra DDoS volumétrico. Limites Laravel protegem a camada HTTP da aplicação; não absorvem saturação de banda ou conexão antes do PHP.
- No proxy/web server, defina tamanho máximo de corpo e upload, timeout de conexão/leitura, limite de conexões concorrentes e limite de taxa para login, recuperação, cadastro e endpoints custosos. Restrinja métodos HTTP aos usados pelas rotas.
- Compartilhe rate limits entre todas as instâncias. Monitore 429/5xx, latência, fila de requisições, CPU, memória, conexões do banco, espaço em disco e taxa de gravação de auditoria; configure alertas e procedimento de mitigação.
- Tenha backups criptografados, segregados das credenciais da aplicação, com restauração testada. Defina RPO/RTO e ensaie recuperação antes do go-live.

## Auditoria e dados

- A auditoria atual é gravada no mesmo MySQL transacional da aplicação. Isso ajuda a associar mudanças a eventos, mas não a torna imutável: quem obtiver privilégios de escrita administrativa no banco pode alterá-la. Para evidência resistente a adulteração, encaminhe cópias para armazenamento central append-only/WORM fora da conta/servidor da aplicação, com retenção aprovada e alertas de lacuna.
- Restrinja leitura/exportação das auditorias, aprove retenção e descarte com responsáveis de negócio/privacidade e valide obrigações contratuais e legais aplicáveis.
- Revise o fluxo de suporte da conta `master`: acesso nominal, menor quantidade de contas, credenciais exclusivas, revisão de privilégios e alertas para uso e mudanças de perfil.

## Verificações antes de liberar

- Rode `php artisan test` e `npm run build`; execute também análise estática, auditoria de dependências e teste de segurança autorizado em ambiente de homologação.
- Confirme manualmente que cadastro público está indisponível em produção; que usuário inativo, empresa inativa e IDs de outro tenant não retornam dados; e que limites continuam ativos com várias instâncias.
- Verifique HTTPS, cookies `Secure`/`HttpOnly`/`SameSite`, cabeçalhos, mensagens genéricas de login/recuperação, inexistência de stack traces e ausência de senha/token/segredo nos logs.
- O 2FA não é exigido nem ativado em desenvolvimento. A ativação para produção é uma etapa futura separada, com método, política de obrigatoriedade, recuperação segura e suporte definidos antes do go-live.

## Controles adicionados no código

- Cinco falhas por e-mail+IP foram substituídas pelo limite legado de seis falhas por e-mail e doze por IP, em janelas de 15 minutos; chaves de e-mail são pseudonimizadas.
- O limite legado por e-mail impõe um cooldown temporário que um atacante pode tentar provocar para atrasar o login da vítima. Ele expira após 15 minutos; recuperação e suporte devem continuar disponíveis e monitorados. Não converta isso em bloqueio permanente automático.
- Login tem limite adicional por IP; recuperação de senha tem limites por IP/e-mail e respostas que não revelam se o endereço está cadastrado; submissões de redefinição e ações sensíveis têm limites próprios.
- Rotas autenticadas têm limite por usuário/IP; operações de escrita continuam protegidas por CSRF do grupo web e verificações servidoras de papel, permissão, estado e tenant.
- Sessões são criptografadas e cookies seguros por padrão em produção; sessões são regeneradas no login e invalidada a sessão corrente no logout.
- Cabeçalhos defensivos, proteção contra cache de páginas de autenticação/dados privados e identificador de correlação por requisição estão habilitados; eventos de auditoria carregam esse identificador.
- Em respostas HTTPS de produção, CSP nonce-aware está habilitada para Vite e `@routes`/Ziggy. Ao incluir integrações externas, revise explicitamente `connect-src`, `img-src`, `font-src` e `style-src`; não adicione `unsafe-inline` como atalho.
