# Deploy gratuito — Iwori Agenda

Guia para publicar o Iwori Agenda na nuvem sem custo, usando:

| Peça | Serviço | Plano |
|---|---|---|
| App (web + fila + agendador) | [Render](https://render.com) — web service Docker | Free |
| Banco MySQL 8 | [Aiven for MySQL](https://aiven.io/mysql) | Free (1 GB) |
| E-mail transacional | [Brevo](https://www.brevo.com) — SMTP relay | Free (300 e-mails/dia) |

Nenhum dos três pede cartão de crédito no cadastro.

---

## 1. Diagnóstico do projeto

- **Stack:** PHP 8.3+ com Laravel 13. Front em Blade + Alpine.js + Tailwind CSS 4, compilado pelo Vite. O README cita Livewire, mas ele não está no `composer.json`.
- **Arquitetura:** monólito full-stack com páginas renderizadas no servidor (MVC), sem API REST separada. Já vem dockerizado:
  - `Dockerfile`: imagem de produção em 3 estágios (Node → Composer → PHP 8.4 + Apache).
  - `compose.yaml`: desenvolvimento local com Sail (PHP 8.5 + MySQL 8.4 + Mailpit).
  - `compose.prod.yaml`: produção self-hosted (app + fila + agendador + MySQL).
- **Banco:** **MySQL**. O `.env.example` cai em SQLite por padrão, mas o projeto **depende de MySQL**: a migration `2026_07_08_100001_add_excused_no_show_to_client_sessions_table.php` usa `ALTER TABLE … MODIFY COLUMN … ENUM`, que não existe em PostgreSQL nem em SQLite. Por isso o banco na nuvem é MySQL (Aiven), e não o Postgres gratuito do Render, Neon ou Supabase.
- **Fila:** driver `database`. Os jobs enviam e-mails de agendamento e cobrança, cobranças por WhatsApp e sincronização com o Google Calendar. **Precisa de um worker rodando.**
- **Agendador:** `charges:dispatch` roda diariamente às **09:00 de Brasília** (`America/Sao_Paulo`, 12:00 UTC). O app em si roda em UTC (`config/app.php`).
- **Arquivos enviados:** fotos de perfil ficam no disco `public` e comprovantes de pagamento no disco `local`, ambos no filesystem do contêiner (veja as [limitações](#7-limitações-do-plano-gratuito)).
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
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE` / `DB_USERNAME` | `defaultdb` / `avnadmin` (padrão do Aiven) |
| `MYSQL_ATTR_SSL_CA` | `/etc/secrets/aiven-ca.pem` |
| `SESSION_DRIVER` / `SESSION_ENCRYPT` / `SESSION_SECURE_COOKIE` | `database` / `true` / `true` |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `FILESYSTEM_DISK` | `database` / `database` / `local` |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` | `smtp` / `smtp-relay.brevo.com` / `2525` |
| `MAIL_FROM_NAME` | `Agenda Iwori` |

**Você preenche no Render** (o Blueprint pede na criação):

| Variável | De onde vem |
|---|---|
| `APP_KEY` | Gerada por você (passo 4.1). **Segredo.** |
| `APP_URL` | URL do serviço, ex.: `https://iwori-agenda.onrender.com` |
| `DB_HOST` | Aiven → serviço MySQL → *Overview* → Host |
| `DB_PORT` | Aiven → *Overview* → Port |
| `DB_PASSWORD` | Aiven → *Overview* → Password. **Segredo.** |
| `MAIL_USERNAME` | Brevo → *SMTP & API* → aba SMTP → Login |
| `MAIL_PASSWORD` | Brevo → chave SMTP gerada. **Segredo.** |
| `MAIL_FROM_ADDRESS` | E-mail remetente verificado na Brevo |

**Secret file** (Render → *Environment* → *Secret Files*): `aiven-ca.pem`, com o conteúdo do certificado CA baixado do Aiven.

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

### 4.2 Criar o banco no Aiven

1. Crie a conta em <https://console.aiven.io/signup>.
2. **Create service → MySQL → plano Free.** Anote a região oferecida (ex.: Virginia, nos EUA).
3. Quando o serviço ficar *Running*, abra **Overview → Connection information** e copie **Host**, **Port** e **Password**. Usuário `avnadmin` e banco `defaultdb` já vêm no `render.yaml`.
4. Na mesma tela, clique em **CA certificate → Download** e salve o `ca.pem`. Não coloque esse arquivo no repositório: o `.gitignore` já ignora `*.pem`.

Se a região do Aiven não for a leste dos EUA, troque `region: virginia` no `render.yaml` pela região do Render mais próxima (`oregon`, `ohio`, `virginia`, `frankfurt` ou `singapore`) **antes** de criar o serviço no Render.

### 4.3 Configurar o e-mail na Brevo

O plano Free do Render bloqueia as portas SMTP 25, 465 e 587 (desde setembro de 2025). Por isso o Gmail na porta 587 não funciona lá, e usamos a Brevo na porta 2525.

1. Crie a conta em <https://www.brevo.com>.
2. **Senders, Domains & Dedicated IPs → Senders → Add a sender**, e verifique o e-mail remetente. Verificar um domínio próprio melhora a entrega.
3. **SMTP & API → aba SMTP → Generate a new SMTP key.** Anote o **Login** (`MAIL_USERNAME`) e a **chave** (`MAIL_PASSWORD`).
4. Confira na mesma página se a porta 2525 aparece entre as portas aceitas.

### 4.4 Enviar as alterações para o GitHub

```bash
git add render.yaml DEPLOY.md docker/entrypoint.sh bootstrap/app.php .gitignore .dockerignore .env.example README.md
```

```bash
git commit -m "Prepara deploy gratuito no Render com MySQL no Aiven"
```

```bash
git push origin main
```

### 4.5 Criar o serviço no Render (Blueprint)

1. Entre em <https://dashboard.render.com> com sua conta do GitHub e autorize o acesso ao repositório `rafalopesbob/iwori-agenda`.
2. **New → Blueprint →** selecione o repositório. O Render lê o `render.yaml` e mostra o serviço `iwori-agenda`.
3. Preencha as variáveis pedidas (tabela "Você preenche no Render" acima). Em `APP_URL`, use `https://iwori-agenda.onrender.com`. Se o nome estiver ocupado, o Render mostra a URL real depois, e aí você corrige.
4. Clique em **Apply**. O primeiro deploy vai falhar com *"Certificado CA do MySQL não encontrado"*. É esperado: o certificado ainda não foi enviado.
5. Abra o serviço → **Environment → Secret Files → Add Secret File**. Nome: `aiven-ca.pem`. Conteúdo: cole o `ca.pem` do Aiven. Salve.
6. **Manual Deploy → Deploy latest commit.** Nos logs, espere ver as migrations rodando e o Apache subindo.
7. Abra `https://<sua-url>.onrender.com/up` (deve responder 200). Depois, `/register` para criar sua conta.

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

- **Google Calendar:** no Google Cloud Console → *Credentials* → seu OAuth client → **Authorized redirect URIs**, adicione `https://<sua-url>.onrender.com/google/callback`. Depois, coloque `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` no Render.
- **WhatsApp:** adicione `WHATSAPP_TOKEN` e `WHATSAPP_PHONE_ID` (o template padrão é `cobranca_iwori`). Só recebem por WhatsApp os clientes com celular válido e autorização marcada no cadastro. Se a Meta falhar, a mensagem é refeita sozinha até 3 vezes, sem reenviar o e-mail.

### 4.7 Acordar o app para a cobrança diária

No plano Free, o serviço **dorme após 15 minutos sem acessos** e, dormindo, o agendador não roda. Para a cobrança das 09:00 de Brasília acontecer, crie um despertador gratuito no [cron-job.org](https://cron-job.org):

- URL: `https://<sua-url>.onrender.com/up`
- Agendamento: todo dia às **08:50 (America/Sao_Paulo)**, ou seja, 11:50 UTC

O app acorda em cerca de 1 minuto e roda `charges:dispatch` às 09:00. A fila envia as cobranças antes de ele dormir de novo.

## 5. Ambiente local x nuvem

O mesmo código lê as variáveis de cada ambiente, sem `if` no código:

| | Local (Sail) | Render |
|---|---|---|
| `DB_HOST` | `mysql` (contêiner) | host do Aiven |
| `DB_PASSWORD` | gerada no seu `.env` | do Aiven |
| `MYSQL_ATTR_SSL_CA` | *(não definida, sem TLS)* | `/etc/secrets/aiven-ca.pem` |
| `MAIL_*` | Mailpit (`mailpit:1025`) | Brevo (`smtp-relay.brevo.com:2525`) |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` |

Para testar o banco do Aiven a partir da sua máquina, sem mexer no `.env` do dia a dia, crie um `.env.aiven` (já ignorado pelo git) com as variáveis do Aiven e `MYSQL_ATTR_SSL_CA` apontando para o `ca.pem`, e rode os comandos com `--env=aiven`. Exemplo: `php artisan migrate:status --env=aiven`.

## 6. Checklist de segurança

- [x] `.env`, qualquer `.env.*` (exceto os `.example`) e `*.pem` estão no `.gitignore` (conferido com `git check-ignore`).
- [x] Nenhum segredo está rastreado nem no histórico do git (só os `.env*.example`, sem valores reais).
- [x] `APP_DEBUG=false` e `APP_ENV=production`: URLs forçadas em HTTPS e senhas checadas contra vazamentos (`uncompromised`).
- [x] Cookies de sessão `Secure`, `HttpOnly`, `SameSite=lax` e sessão criptografada.
- [x] Proxies confiáveis configurados (HSTS ativo) e throttle de login por IP **e** por e-mail, que segura força bruta mesmo com IP forjado.
- [x] Confirmação de e-mail obrigatória antes de qualquer envio a clientes (cobranças, confirmação de sessão, cobrança diária). O link sai pela fila: exige SMTP configurado, worker ativo (`RUN_WORKERS`) e `APP_URL` correto. Contas criadas antes desta mudança foram marcadas como confirmadas pela migration `mark_existing_users_as_verified`.
- [x] Conexão com o banco por TLS, validando o CA do Aiven.
- [ ] `DEMO_MODE` definido **só** no serviço `iwori-agenda-demo`, que usa database e usuário próprios (seção 8).
- [ ] `APP_KEY` exclusivo de produção, guardado fora do repositório e **nunca trocado**.
- [ ] Segredos (`APP_KEY`, `DB_PASSWORD`, chave SMTP, `GOOGLE_CLIENT_SECRET`, `WHATSAPP_TOKEN`) só no painel do Render. Nunca em commits, issues ou chats.
- [ ] Na Brevo, use a **chave SMTP**, não a senha da conta.
- [ ] Opcional: no Aiven, restrinja *Allowed IP addresses* aos IPs de saída do Render (painel do serviço → *Connect → Outbound*).
- [ ] Se algum segredo vazar, gere outro no provedor e atualize no Render. Exceção: o `APP_KEY`, que só deve ser trocado com um plano de recriptografia dos dados.

## 7. Limitações do plano gratuito

- **Arquivos enviados se perdem.** O disco do Render Free é efêmero: fotos de perfil e **comprovantes de pagamento** somem a cada deploy, reinício ou hibernação. Para guardá-los de verdade, é preciso um storage S3-compatível (ex.: Cloudflare R2 ou Backblaze B2), com o pacote `league/flysystem-aws-s3-v3` e os controllers usando um disco configurável.
- **Hibernação:** depois de 15 minutos sem acesso, a próxima visita leva cerca de 1 minuto para carregar.
- **750 horas/mês** por workspace no Render. Um serviço ligado o mês inteiro usa cerca de 744.
- **Banco de 1 GB** no Aiven Free. O Aiven pode desligar serviços sem uso por muito tempo.
- **Memória de 512 MB** para Apache + fila + agendador no mesmo contêiner.
- **Horário da cobrança:** 09:00 de Brasília. Para mudar, ajuste o `dailyAt` em `bootstrap/app.php` e o despertador do cron-job.org (10 minutos antes).

### Solução de problemas

| Sintoma nos logs | Causa e correção |
|---|---|
| `Certificado CA do MySQL não encontrado` | Falta o secret file `aiven-ca.pem` (passo 4.5.5). |
| `SQLSTATE[HY000] [2002]` / timeout | `DB_HOST`/`DB_PORT` errados ou IP do Render fora da allowlist do Aiven. |
| `Unable to create or change a table without a primary key` | Desative `sql_require_primary_key` em Aiven → *Advanced configuration*. Todas as tabelas atuais têm PK, então só deve aparecer com migrations novas. |
| `Unsupported cipher or incorrect key length` | `APP_KEY` sem o prefixo `base64:` ou incompleto. |
| E-mails não chegam | Remetente não verificado na Brevo, ou porta diferente de 2525. |

## 8. Ambiente de demonstração para clientes

O `render.yaml` tem um segundo serviço, `iwori-agenda-demo`. Ele roda o mesmo código com `DEMO_MODE=true` e banco próprio:

- **Login com um clique:** a tela de login ganha o botão **Entrar na demonstração**, que abre a conta fictícia da Helena Rocha sem expor senha.
- **Dados sempre atuais:** o `DemoSeeder` recria a conta a cada início do contêiner (cada deploy e cada vez que o serviço acorda) e todo dia às 04:00 de Brasília (`DEMO_RESET_AT`). Agenda, presenças e cobranças ficam coerentes com a data de hoje.
- **Nada sai do sistema:** e-mails vão para o log; WhatsApp e Google Calendar ficam desligados, mesmo que alguém configure as variáveis por engano.
- **Conta protegida:** cadastro de contas e alterações de perfil (senha, e-mail, foto) ficam bloqueados. Agendar, marcar presença, cobrar e editar templates funcionam e voltam ao estado inicial no próximo reset.
- **Conta compartilhada:** todos os visitantes usam a mesma conta e veem o que os outros mexeram até o próximo reset.

> **Nunca** defina `DEMO_MODE=true` no serviço de produção: ele desliga os envios aos clientes e abre a entrada na conta demo.

### 8.1 Banco da demo no Aiven

Use um database e um usuário próprios, no mesmo serviço MySQL do Aiven, para que a demo (pública) não tenha acesso aos dados reais.

1. No console do Aiven → seu serviço MySQL → **Databases → Create database**: `iwori_demo`.
2. **Users → Create user**: `iwori_demo`. Anote a senha gerada.
3. Conectado como `avnadmin` (MySQL Workbench, HeidiSQL etc., com o `ca.pem`), limite o usuário ao banco da demo:

```sql
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'iwori_demo'@'%';
GRANT ALL PRIVILEGES ON iwori_demo.* TO 'iwori_demo'@'%';
```

### 8.2 Criar o serviço no Render

1. Gere um `APP_KEY` **diferente** do da produção: `php artisan key:generate --show`.
2. No Render, **Blueprints → seu blueprint → Sync**. O serviço `iwori-agenda-demo` aparece como novo.
3. Preencha `APP_KEY`, `APP_URL` (ex.: `https://iwori-agenda-demo.onrender.com`), `DB_HOST`, `DB_PORT` (os mesmos da produção) e `DB_PASSWORD` (do usuário `iwori_demo`).
4. Adicione o secret file `aiven-ca.pem` também neste serviço (igual ao passo 4.5.5) e faça **Manual Deploy**.
5. Abra a URL da demo e clique em **Entrar na demonstração**.

**Horas do plano Free:** as 750 horas/mês são por workspace e passam a ser divididas entre produção e demo. Os dois serviços dormem quando ninguém acessa, então normalmente sobra. Se a demo for muito usada, acompanhe em *Billing* ou crie a demo num workspace separado (cada um tem suas 750 horas): se as horas acabarem, os serviços Free do workspace param até o mês seguinte, incluindo a produção.

### 8.3 Demo na sua máquina

Para apresentar localmente, recrie a conta quando quiser (fora de produção não precisa de `DEMO_MODE`):

```bash
php artisan demo:reset
```

Para ver localmente exatamente como o cliente vê (botão de entrada, faixa e bloqueios), coloque `DEMO_MODE=true` no `.env`.

## Referências

- Render: [Deploy for Free](https://render.com/docs/free) · [Blueprint spec](https://render.com/docs/blueprint-spec) · [Secrets com Docker](https://render.com/docs/docker-secrets) · [Bloqueio de portas SMTP no Free](https://render.com/changelog/free-web-services-will-no-longer-allow-outbound-traffic-to-smtp-ports)
- Aiven: [Free plan](https://aiven.io/docs/platform/concepts/free-plan) · [MySQL free tier](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier)
- Brevo: [SMTP gratuito](https://www.brevo.com/free-smtp-server)
