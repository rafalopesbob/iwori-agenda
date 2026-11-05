<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente de demonstração
    |--------------------------------------------------------------------------
    |
    | Liga o modo demonstração: um ambiente público, com banco próprio, onde
    | possíveis clientes navegam pela conta fictícia do DemoSeeder.
    |
    | Com DEMO_MODE=true:
    | - o DemoSeeder pode rodar mesmo com APP_ENV=production;
    | - a conta demo é recriada todo dia (demo:reset) e a cada início do contêiner;
    | - a tela de login ganha o botão "Entrar na demonstração";
    | - nada sai do sistema: e-mail vai para o log, WhatsApp e Google desligados;
    | - cadastro e alterações de conta (senha, e-mail, foto) ficam bloqueados.
    |
    | NUNCA ligue em produção de verdade: o modo desliga os envios aos clientes.
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    // Horário (Brasília) da recriação diária da conta demo.
    'reset_at' => env('DEMO_RESET_AT', '04:00'),

];
