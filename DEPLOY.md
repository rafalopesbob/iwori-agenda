# Deploy gratuito — Iwori Agenda

Guia para publicar o Iwori Agenda na nuvem sem custo, usando:

| Peça | Serviço | Plano |
|---|---|---|
| App (web + fila + agendador) | [Render](https://render.com) — web service Docker | Free |
| Banco compatível com MySQL | [TiDB Cloud Starter](https://www.pingcap.com/tidb-cloud-starter/) | Free (5 GiB e 50 milhões de RUs/mês por instância, até 5 instâncias) |
| E-mail transacional | [Brevo](https://www.brevo.com) — SMTP relay | Free (300 e-mails/dia) |
| Fotos de perfil e comprovantes | [Cloudflare R2](https://www.cloudflare.com/developer-platform/products/r2/) — bucket privado | Free (10 GB) |

Render, TiDB Cloud e Brevo não pedem cartão de crédito no cadastro. O Cloudflare pede uma forma de pagamento para ativar o R2, mesmo no plano grátis; só cobra o que passar do limite gratuito.

---

## 1. Diagnóstico do projeto

- **Stack:** PHP 8.3+ com Laravel 13. Front em Blade + Alpine.js + Tailwind CSS 4, compilado pelo Vite. O README cita Livewire, mas ele não está no `composer.json`.
- **Arquitetura:** monólito full-stack com páginas renderizadas no servidor (MVC), sem API REST separada. Já vem dockerizado:
  - `Dockerfile`: imagem de produção em 3 estágios (Node → Composer → PHP 8.4 + Apache).
  - `compose.yaml`: desenvolvimento local com Sail (PHP 8.5 + MySQL 8.4 + Mailpit).
  - `compose.prod.yaml`: produção self-hosted (app + fila + agendador + MySQL).
- **Banco:** **MySQL**. O `.env.example` cai em SQLite por padrão, mas o projeto **depende de MySQL**: a migration `2026_07_08_100001_add_excused_no_show_to_client_sessions_table.php` usa `ALTER TABLE … MODIFY COLUMN … ENUM`, que não existe em PostgreSQL nem em SQLite. Por isso o banco na nuvem precisa falar MySQL, e não Postgres (Render, Neon, Supabase).
  - O Aiven não oferece mais MySQL grátis (o plano Free dele ficou só para PostgreSQL, Kafka, OpenSearch e Valkey). O banco escolhido é o **TiDB Cloud Starter**, compatível com o protocolo e o SQL do MySQL.
  - Testado num TiDB 8.5 local: os 247 testes passam, e as migrations (incluindo a do `ENUM`), as chaves estrangeiras, a fila `database` (`FOR UPDATE SKIP LOCKED`), a comparação de e-mail sem diferenciar maiúsculas e o `demo:reset` funcionam.
- **Fila:** driver `database`. Os jobs enviam e-mails de agendamento e cobrança, cobranças por WhatsApp e sincronização com o Google Calendar. **Precisa de um worker rodando.**
- **Agendador:** `charges:dispatch` roda diariamente às **09:00 de Brasília** (`America/Sao_Paulo`, 12:00 UTC). O app em si roda em UTC (`config/app.php`).
- **Arquivos enviados:** fotos de perfil e comprovantes de pagamento vão para o disco definido em `UPLOADS_DISK`. Os dois são privados e só saem por rotas que conferem o dono. No Render, `r2` (Cloudflare R2), porque o disco do contêiner some a cada reinício; num servidor com disco permanente (VPS com `compose.prod.yaml`), `local` basta.
- **Integrações opcionais:** Google Calendar (OAuth 2.0, callback em `/google/callback`) e WhatsApp Cloud API (Meta).
- **Health check:** `GET /up`, a rota nativa do Laravel.

## 2. O que foi ajustado no repositório

| Arquivo | Mudança |
|---|---|
| `render.yaml` | **Novo.** Blueprint do Render: serviço Docker no plano Free, health check e todas as variáveis de ambiente. |
| `docker/entrypoint.sh` | Apache passa a escutar na porta `$PORT` da plataforma. Copia o certificado CA do MySQL para um caminho legível pelo Apache. Cria o link `public/storage`. Com `RUN_WORKERS=1`, sobe fila e agendador no mesmo contêiner. |
| `bootstrap/app.php` | `trustProxies(at: '*')`: atrás do load balancer, o Laravel detecta HTTPS (ativa o HSTS) e lê o IP do cliente do `X-Forwarded-For`. Com `'*'` esse IP pode ser forjado pelo próprio cliente, então o throttle de login limita também por e-mail (ver `AppServiceProvider`). |
| `.gitignore` | Ignora qualquer `.env.*` (menos os `.example`) e certificados `*.pem`. |
| `.dockerignore` | Exclui da imagem `*.pem` (o Render copia secret files para o contexto do build), o link local `public/storage` (quebrava o `docker build`), `render.yaml` e `DEPLOY.md`. |
| `.env.example` | Documenta a variável `MYSQL_ATTR_SSL_CA` para banco remoto com TLS. |
| `README.md` | Corrige a imagem auxiliar do Composer: `laravelsail/php85-composer` não existe, o certo é `php84-composer`. |
| `config/filesystems.php` | Disco `r2` (Cloudflare R2, privado) e a chave `uploads` (`UPLOADS_DISK`), usada pela foto de perfil e pelos comprovantes. A foto passa a sair pela rota autenticada `/profile/photo`, então nenhum arquivo precisa de URL pública. |

Nada disso muda o ambiente local: `PORT`, `RUN_WORKERS` e `MYSQL_ATTR_SSL_CA` só existem no Render. O `compose.prod.yaml` continua igual, com fila e agendador em serviços próprios.

## 3. Variáveis de ambiente

Já definidas no `render.yaml` (não precisa mexer):

| Variável | Valor |
|---|---|
| `APP_NAME` | `Agenda Iwori` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stderr` / `info` |
| `RUN_MIGRATIONS` / `RUN_WORKERS` | `1` / `1` |
| `DB_CONNECTION` / `DB_PORT` | `mysql` / `4000` (porta do TiDB Cloud) |
| `DB_DATABASE` | `iwori_agenda` (demo: `iwori_demo`) |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` (CAs públicas do sistema; o TiDB Cloud exige TLS) |
| `SESSION_DRIVER` / `SESSION_ENCRYPT` / `SESSION_SECURE_COOKIE` | `database` / `true` / `true` |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `FILESYSTEM_DISK` | `database` / `database` / `local` |
| `UPLOADS_DISK` / `R2_BUCKET` | `r2` / `iwori-uploads` |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` | `smtp` / `smtp-relay.brevo.com` / `2525` |
| `MAIL_FROM_NAME` | `Agenda Iwori` |

**Você preenche no Render** (o Blueprint pede na criação):

| Variável | De onde vem |
|---|---|
| `APP_KEY` | Gerada por você (passo 4.1). **Segredo.** |
| `APP_URL` | URL do serviço, ex.: `https://iwori-agenda.onrender.com` |
| `DB_HOST` | TiDB Cloud → instância → **Connect** → Host |
| `DB_USERNAME` | TiDB Cloud → **Connect** → User (vem com o prefixo da instância, ex.: `2abc123.root`) |
| `DB_PASSWORD` | TiDB Cloud → **Connect** → *Generate Password*. **Segredo.** |
| `MAIL_USERNAME` | Brevo → *SMTP & API* → aba SMTP → Login |
| `MAIL_PASSWORD` | Brevo → chave SMTP gerada. **Segredo.** |
| `MAIL_FROM_ADDRESS` | E-mail remetente verificado na Brevo |
| `R2_ENDPOINT` | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` (passo 4.4) |
| `R2_ACCESS_KEY_ID` | Cloudflare → token da API do R2 → Access Key ID |
| `R2_SECRET_ACCESS_KEY` | Cloudflare → token da API do R2 → Secret Access Key. **Segredo.** |

O serviço da demo pede o mesmo conjunto, com `DB_HOST`, `DB_USERNAME` e `DB_PASSWORD` da **instância da demo** (seção 8).

**Opcionais** (adicione em *Environment* só se for usar):

| Variável | Uso |
|---|---|
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Sincronização com o Google Calendar |
| `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_ID`, `WHATSAPP_TEMPLATE_CHARGE` | Cobrança por WhatsApp (template padrão `cobranca_iwori`) |
| `WHATSAPP_API_VERSION` | Versão da Graph API da Meta (padrão `v25.0`). Troque quando a Meta anunciar o fim da versão em uso. |

> ⚠️ **O `APP_KEY` de produção nunca pode mudar.** As anotações de clientes, sessões e cobranças e os tokens do Google são gravados criptografados com ele. Trocar a chave deixa esses dados ilegíveis. Guarde-a num gerenciador de senhas e não reutilize a chave local.

## 4. Passo a passo

### 4.1 Gerar o `APP_KEY` de produção

Com o Docker rodando (não precisa de PHP instalado):

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copie a saída inteira, incluindo o prefixo `base64:`.

### 4.2 Criar os bancos no TiDB Cloud

São duas instâncias grátis: uma para a produção e outra para a demo. Assim a demo, que é pública, nunca enxerga os dados reais.

1. Crie a conta em <https://tidbcloud.com> (não pede cartão).
2. **Create Instance → Starter**, provedor **AWS**, região **N. Virginia (us-east-1)**, a mesma do Render. Nome: `iwori-agenda`. Deixe o limite de gasto (*spending limit*) em **0**: a instância fica no plano grátis.
3. Repita para a demo, com o nome `iwori-agenda-demo`.
4. Em cada instância, abra o **SQL Editor** e crie o banco:
   - produção: `CREATE DATABASE iwori_agenda;`
   - demo: `CREATE DATABASE iwori_demo;`
5. Em cada instância, clique em **Connect** (*Public Endpoint*) e anote **Host** e **User**. Depois clique em **Generate Password** e guarde a senha num gerenciador de senhas: ela aparece uma única vez. A porta é sempre `4000`, e o TLS já está configurado no `render.yaml`.

Se escolher outra região no TiDB Cloud, troque `region: virginia` no `render.yaml` pela região do Render mais próxima (`oregon`, `ohio`, `virginia`, `frankfurt` ou `singapore`) **antes** de criar os serviços no Render.

### 4.3 Configurar o e-mail na Brevo

O plano Free do Render bloqueia as portas SMTP 25, 465 e 587 (desde setembro de 2025). Por isso o Gmail na porta 587 não funciona lá, e usamos a Brevo na porta 2525.

1. Crie a conta em <https://www.brevo.com>.
2. **Senders, Domains & Dedicated IPs → Senders → Add a sender**, e verifique o e-mail remetente. Verificar um domínio próprio melhora a entrega.
3. **SMTP & API → aba SMTP → Generate a new SMTP key.** Anote o **Login** (`MAIL_USERNAME`) e a **chave** (`MAIL_PASSWORD`).
4. Confira na mesma página se a porta 2525 aparece entre as portas aceitas.

### 4.4 Criar o bucket no Cloudflare R2

1. No painel do Cloudflare, abra **R2 Object Storage** e ative o R2. Ele pede uma forma de pagamento, mas só cobra o que passar do plano gratuito (10 GB).
2. **Create bucket**, nome `iwori-uploads` (o mesmo do `render.yaml`). Deixe o acesso público **desligado**: os arquivos passam pelo app, que confere o dono antes de entregar.
3. Copie o **Account ID** (aparece na página do R2). O `R2_ENDPOINT` é `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`.
4. **Manage API tokens → Create API token**: permissão **Object Read & Write**, aplicada **só ao bucket `iwori-uploads`**. Anote o **Access Key ID** e o **Secret Access Key**: o segredo aparece uma única vez.

Sem o R2, coloque `UPLOADS_DISK=local` no Render. O app funciona, mas fotos e comprovantes somem a cada deploy, reinício ou hibernação: serve só para testar.

### 4.5 Criar o serviço no Render (Blueprint)

1. Entre em <https://dashboard.render.com> com sua conta do GitHub e autorize o acesso ao repositório `rafalopesbob/iwori-agenda`.
2. **New → Blueprint →** selecione o repositório. O Render lê o `render.yaml` e mostra os serviços `iwori-agenda` e `iwori-agenda-demo`.
3. Preencha as variáveis pedidas (tabela "Você preenche no Render" acima; para a demo, seção 8). Em `APP_URL`, use `https://iwori-agenda.onrender.com`. Se o nome estiver ocupado, o Render mostra a URL real depois, e aí você corrige.
4. Clique em **Apply**. Nos logs, espere ver as migrations rodando e o Apache subindo.
5. Abra `https://<sua-url>.onrender.com/up` (deve responder 200). Depois, `/register` para criar sua conta.

**Sem Blueprint (configuração manual):** New → Web Service → repositório → **Language: Docker** → Instance type **Free** → depois preencha:

| Campo | Valor |
|---|---|
| Dockerfile Path | `./Dockerfile` |
| Docker Build Context Directory | `.` |
| Docker Command | *(vazio, usa o `CMD` do Dockerfile: `apache2-foreground`)* |
| Health Check Path | `/up` |
| Environment | todas as variáveis da seção 3 |

> **Build/Start Command:** o Render não tem runtime PHP nativo, então o build e o start vêm do `Dockerfile` e não há comando para colar. Se um dia usar uma plataforma com PHP nativo, os equivalentes são:
>
> - Build: `composer install --no-dev --optimize-autoloader --no-interaction && npm ci && npm run build`
> - Start: `php artisan migrate --force && php artisan storage:link && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=$PORT`
>
> Nesse caso, fila e agendador precisam de processos próprios (`php artisan queue:work` e `php artisan schedule:work`).

### 4.6 Integrações (opcional)

- **Google Agenda:** veja o passo a passo completo em [4.6.1](#461-google-agenda).
- **WhatsApp:** adicione `WHATSAPP_TOKEN` e `WHATSAPP_PHONE_ID` (o template padrão é `cobranca_iwori`). Só recebem por WhatsApp os clientes com celular válido e autorização marcada no cadastro. Se a Meta falhar, a mensagem é refeita sozinha até 3 vezes, sem reenviar o e-mail.

#### 4.6.1 Google Agenda

O app só **envia** as sessões para a agenda principal da profissional, com o escopo `calendar.events`. Ele não lê a agenda do Google.

**Uma vez só, no [Google Cloud Console](https://console.cloud.google.com)**, com a conta Google de quem administra o sistema:

1. Crie um projeto (ex.: "Agenda Iwori").
2. **APIs e serviços → Biblioteca**: ative a **Google Calendar API**.
3. **Google Auth Platform → Branding** (a antiga "Tela de consentimento OAuth"):
   - público **Externo**;
   - nome do app "Agenda Iwori" e e-mail de suporte.
4. **Data Access → Add or remove scopes**: adicione `https://www.googleapis.com/auth/calendar.events`. Ele aparece como escopo **sensível**.
5. **Clients → Create client → Web application**:
   - em **Authorized redirect URIs**, adicione `http://localhost/google/callback` (ambiente local) e `https://<sua-url>.onrender.com/google/callback` (produção);
   - copie o **Client ID** e o **Client secret** para `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET`, no `.env` local e no Render. O secret é **segredo**.
6. **Audience → Publishing status**: escolha um dos caminhos abaixo.

| Status | Quem consegue conectar | O que a profissional vê | A conexão dura | Serve para |
|---|---|---|---|---|
| *Testing* | Só os e-mails cadastrados em *Test users* (até 100) | Aviso "O Google não verificou este app" | **7 dias**, depois precisa reconectar | Demonstração |
| *In production*, sem verificação | Qualquer conta, até 100 pessoas | O mesmo aviso: ela clica em *Avançado* e continua | Não expira | Poucas profissionais |
| *In production*, verificado | Qualquer conta, sem limite | Nenhum aviso | Não expira | Vender para mais profissionais |

A verificação pede domínio próprio (um `*.onrender.com` não serve), página inicial e política de privacidade publicadas nesse domínio e um vídeo mostrando o uso da agenda. A análise leva de dias a semanas. Depois de aprovada, defina `GOOGLE_APP_VERIFIED=true`: a página de ajuda deixa de mostrar o passo do aviso.

**Variáveis opcionais:**

| Variável | Padrão | Uso |
|---|---|---|
| `GOOGLE_CALENDAR_TIMEZONE` | `America/Sao_Paulo` | Fuso em que as profissionais digitam os horários. O evento vai para o Google com esse fuso. |
| `GOOGLE_APP_VERIFIED` | `false` | `true` depois da verificação do Google. |

**Para a profissional:** o passo a passo de conexão está no próprio app, em `/ajuda/google-agenda` (link **Como funciona** no quadro Google Agenda).

**O que é sincronizado:**
- ao conectar, as sessões agendadas de hoje em diante vão para o Google;
- sessão criada, editada ou arrastada: o evento é criado ou atualizado, sem duplicar;
- sessão cancelada: o evento é removido; desfeito o cancelamento, ele volta;
- ao desconectar, os eventos já criados ficam na agenda do Google.

Tudo passa pela fila, então o worker precisa estar ligado (no Render ele já está previsto).

### 4.7 Acordar o app para a cobrança diária

No plano Free, o serviço **dorme após 15 minutos sem acessos** e, dormindo, o agendador não roda. Para a cobrança das 09:00 de Brasília acontecer, crie um despertador gratuito no [cron-job.org](https://cron-job.org):

- URL: `https://<sua-url>.onrender.com/up`
- Agendamento: todo dia às **08:50 (America/Sao_Paulo)**, ou seja, 11:50 UTC

O app acorda em cerca de 1 minuto e roda `charges:dispatch` às 09:00. A fila envia as cobranças antes de ele dormir de novo.

## 5. Ambiente local x nuvem

O mesmo código lê as variáveis de cada ambiente, sem `if` no código:

| | Local (Sail) | Render |
|---|---|---|
| `DB_HOST` / `DB_PORT` | `mysql` (contêiner) / `3306` | host do TiDB Cloud / `4000` |
| `DB_USERNAME` / `DB_PASSWORD` | do seu `.env` | da instância no TiDB Cloud |
| `MYSQL_ATTR_SSL_CA` | *(não definida, sem TLS)* | `/etc/ssl/certs/ca-certificates.crt` |
| `MAIL_*` | Mailpit (`mailpit:1025`) | Brevo (`smtp-relay.brevo.com:2525`) |
| `UPLOADS_DISK` | `local` (`storage/app/private`) | `r2` (bucket `iwori-uploads`) |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` |

Para testar o banco do TiDB Cloud a partir da sua máquina, sem mexer no `.env` do dia a dia, crie um `.env.tidb` (já ignorado pelo git) com as variáveis da instância e `MYSQL_ATTR_SSL_CA` apontando para um pacote de CAs públicas (no Windows, o `cacert.pem` do [curl](https://curl.se/docs/caextract.html)), e rode os comandos com `--env=tidb`. Exemplo: `php artisan migrate:status --env=tidb`.

Para rodar os testes num TiDB local, igual ao da nuvem: `docker run -d -p 127.0.0.1:4000:4000 pingcap/tidb:v8.5.0`, crie o banco `iwori_agenda_test` e rode `php artisan test` com `DB_HOST=127.0.0.1` e `DB_PORT=4000` no ambiente.

## 6. Checklist de segurança

- [x] `.env`, qualquer `.env.*` (exceto os `.example`) e `*.pem` estão no `.gitignore` (conferido com `git check-ignore`).
- [x] Nenhum segredo está rastreado nem no histórico do git (só os `.env*.example`, sem valores reais).
- [x] `APP_DEBUG=false` e `APP_ENV=production`: URLs forçadas em HTTPS e senhas checadas contra vazamentos (`uncompromised`).
- [x] Cookies de sessão `Secure`, `HttpOnly`, `SameSite=lax` e sessão criptografada.
- [x] Proxies confiáveis configurados (HSTS ativo) e throttle de login por IP **e** por e-mail, que segura força bruta mesmo com IP forjado.
- [x] Confirmação de e-mail obrigatória antes de qualquer envio a clientes (cobranças, confirmação de sessão, cobrança diária). O link sai pela fila: exige SMTP configurado, worker ativo (`RUN_WORKERS`) e `APP_URL` correto. Contas criadas antes desta mudança foram marcadas como confirmadas pela migration `mark_existing_users_as_verified`.
- [x] Conexão com o banco por TLS, validando o certificado do TiDB Cloud com as CAs públicas do sistema.
- [ ] `DEMO_MODE` definido **só** no serviço `iwori-agenda-demo`, que usa uma instância própria do TiDB Cloud (seção 8).
- [ ] `APP_KEY` exclusivo de produção, guardado fora do repositório e **nunca trocado**.
- [ ] Segredos (`APP_KEY`, `DB_PASSWORD`, chave SMTP, `GOOGLE_CLIENT_SECRET`, `WHATSAPP_TOKEN`) só no painel do Render. Nunca em commits, issues ou chats.
- [ ] Na Brevo, use a **chave SMTP**, não a senha da conta.
- [ ] No R2, bucket sem acesso público e token só com **Object Read & Write** no bucket `iwori-uploads`.
- [ ] Opcional: no TiDB Cloud, limite o acesso ao *Public Endpoint* aos IPs de saída do Render (painel do serviço no Render → *Connect → Outbound*).
- [ ] Se algum segredo vazar, gere outro no provedor e atualize no Render. Exceção: o `APP_KEY`, que só deve ser trocado com um plano de recriptografia dos dados.

## 7. Limitações do plano gratuito

- **Disco temporário.** O disco do Render Free some a cada deploy, reinício ou hibernação. Por isso fotos e comprovantes vão para o R2 (`UPLOADS_DISK=r2`). Com `UPLOADS_DISK=local` no Render, eles se perdem. O plano grátis do R2 guarda 10 GB.
- **Hibernação:** depois de 15 minutos sem acesso, a próxima visita leva cerca de 1 minuto para carregar.
- **750 horas/mês** por workspace no Render. Um serviço ligado o mês inteiro usa cerca de 744.
- **Banco:** cada instância grátis do TiDB Cloud tem 5 GiB e 50 milhões de RUs (unidades de processamento) por mês. Se a cota acabar, novas conexões são recusadas até o mês seguinte, a menos que você defina um limite de gasto. O uso aparece em *Usage this month*, na página da instância.
- **Conexões:** o TiDB Cloud derruba conexões paradas há mais de ~5 minutos ou abertas há mais de 30. O Laravel reconecta sozinho na próxima consulta.
- **Memória de 512 MB** para Apache + fila + agendador no mesmo contêiner.
- **Horário da cobrança:** 09:00 de Brasília. Para mudar, ajuste o `dailyAt` em `bootstrap/app.php` e o despertador do cron-job.org (10 minutos antes).

### Solução de problemas

| Sintoma nos logs | Causa e correção |
|---|---|
| `Certificado CA do MySQL não encontrado` | `MYSQL_ATTR_SSL_CA` aponta para um arquivo que não existe na imagem. O valor certo é `/etc/ssl/certs/ca-certificates.crt`. |
| `SQLSTATE[HY000] [2002]` / timeout | `DB_HOST`/`DB_PORT` errados ou IP do Render fora da lista de acesso do TiDB Cloud. |
| `Access denied for user` | `DB_USERNAME` sem o prefixo da instância (ex.: `2abc123.root`), senha errada ou dados da instância trocados entre produção e demo. |
| `Unknown database` | Falta o `CREATE DATABASE` no SQL Editor (passo 4.2). |
| `Connections are denied` / lentidão no fim do mês | Cota grátis de RUs esgotada. Veja *Usage this month* na instância. |
| `Unsupported cipher or incorrect key length` | `APP_KEY` sem o prefixo `base64:` ou incompleto. |
| E-mails não chegam | Remetente não verificado na Brevo, ou porta diferente de 2525. |
| Erro 500 ao enviar foto ou comprovante (`Unable to write file`, `AccessDenied`, `InvalidAccessKeyId`) | `R2_ENDPOINT`, chaves ou nome do bucket errados, ou token sem permissão de escrita no bucket `iwori-uploads`. |

## 8. Ambiente de demonstração para clientes

O `render.yaml` tem um segundo serviço, `iwori-agenda-demo`. Ele roda o mesmo código com `DEMO_MODE=true` e banco próprio:

- **Login com um clique:** a tela de login ganha o botão **Entrar na demonstração**, que abre a conta fictícia da Helena Rocha sem expor senha.
- **Dados sempre atuais:** o `DemoSeeder` recria a conta a cada início do contêiner (cada deploy e cada vez que o serviço acorda) e todo dia às 04:00 de Brasília (`DEMO_RESET_AT`). Agenda, presenças e cobranças ficam coerentes com a data de hoje.
- **Nada sai do sistema:** e-mails vão para o log; WhatsApp e Google Calendar ficam desligados, mesmo que alguém configure as variáveis por engano. Comprovantes enviados na demo ficam no disco do contêiner, nunca no R2.
- **Conta protegida:** cadastro de contas e alterações de perfil (senha, e-mail, foto) ficam bloqueados. Agendar, marcar presença, cobrar e editar templates funcionam e voltam ao estado inicial no próximo reset.
- **Conta compartilhada:** todos os visitantes usam a mesma conta e veem o que os outros mexeram até o próximo reset.

> **Nunca** defina `DEMO_MODE=true` no serviço de produção: ele desliga os envios aos clientes e abre a entrada na conta demo.

### 8.1 Banco da demo

A demo usa a instância `iwori-agenda-demo` do TiDB Cloud, criada no passo 4.2, com o banco `iwori_demo`. Por ser outra instância, com usuário e senha próprios, a demo (pública) não tem como acessar os dados reais.

### 8.2 Variáveis do serviço no Render

O Blueprint cria o `iwori-agenda-demo` junto com a produção (passo 4.5). Nele, preencha:

1. `APP_KEY` **diferente** do da produção: `php artisan key:generate --show`.
2. `APP_URL`, ex.: `https://iwori-agenda-demo.onrender.com`.
3. `DB_HOST`, `DB_USERNAME` e `DB_PASSWORD` da instância **da demo** no TiDB Cloud.

Depois do deploy, abra a URL da demo e clique em **Entrar na demonstração**.

**Horas do plano Free:** as 750 horas/mês são por workspace e passam a ser divididas entre produção e demo. Os dois serviços dormem quando ninguém acessa, então normalmente sobra. Se a demo for muito usada, acompanhe em *Billing* ou crie a demo num workspace separado (cada um tem suas 750 horas): se as horas acabarem, os serviços Free do workspace param até o mês seguinte, incluindo a produção.

### 8.3 Demo na sua máquina

Para apresentar localmente, recrie a conta quando quiser (fora de produção não precisa de `DEMO_MODE`):

```bash
php artisan demo:reset
```

Para ver localmente exatamente como o cliente vê (botão de entrada, faixa e bloqueios), coloque `DEMO_MODE=true` no `.env`.

## Referências

- Render: [Deploy for Free](https://render.com/docs/free) · [Blueprint spec](https://render.com/docs/blueprint-spec) · [Secrets com Docker](https://render.com/docs/docker-secrets) · [Bloqueio de portas SMTP no Free](https://render.com/changelog/free-web-services-will-no-longer-allow-outbound-traffic-to-smtp-ports)
- TiDB Cloud: [Perguntas frequentes do Starter](https://docs.pingcap.com/tidbcloud/serverless-faqs/) · [Limites e cotas](https://docs.pingcap.com/tidbcloud/serverless-limitations/) · [Preços](https://www.pingcap.com/pricing/)
- Brevo: [SMTP gratuito](https://www.brevo.com/free-smtp-server)
- Cloudflare R2: [Preços e plano grátis](https://developers.cloudflare.com/r2/pricing/) · [Tokens da API](https://developers.cloudflare.com/r2/api/tokens/)
