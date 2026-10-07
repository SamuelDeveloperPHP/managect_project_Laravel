<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conta Master da plataforma
    |--------------------------------------------------------------------------
    |
    | Existe uma única identidade Master, com acesso global a todas as empresas. Só o usuário com este e-mail
    | pode ter o papel "master"; qualquer outro usuário "master" é recusado no login e no acesso.
    | Use um e-mail real da sua equipe: o padrão (.local) não recebe mensagens, então a recuperação de senha
    | do Master não funcionaria.
    |
    */

    'master_email' => env('PLATFORM_MASTER_EMAIL', 'admin.master@phalcon.local'),
];
