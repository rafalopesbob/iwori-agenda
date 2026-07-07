# 🌿 Iwori Agenda

> Gestão inteligente de sessões, presenças e faturamento para profissionais do desenvolvimento humano.

O nome vem de **Ìwòrì**, um dos dezesseis odùs principais do oráculo de Ifá na tradição yorubá — associado ao olhar profundo, ao fogo interior e à transformação. É o espírito do projeto: enxergar cada pessoa de verdade e acompanhar seus processos de mudança.

O **Iwori Agenda** é uma aplicação web construída em modelo MVC moderno, pensada para simplificar a vida de profissionais que gerenciam acompanhamentos contínuos — desde atendimentos clínicos até ciclos de instrução artística e grupos de estudos.

---

## 🎯 Principais Funcionalidades

* 🗓️ **Calendário Dinâmico:** Visualização intuitiva das sessões do mês com marcações rápidas de status (Agendado, Realizado, Falta).
* 👥 **Gestão de Clientes:** Perfis detalhados para pacientes ou alunos, controlando valores de contrato e histórico de comparecimento.
* ✉️ **Notificações Automáticas:** Envio de e-mails transacionais (via fila) para confirmação de agendamentos.
* 💰 **Faturamento Inteligente:** Cálculo automático no fechamento do ciclo mensal, somando apenas as sessões onde houve comparecimento.
* 📲 **Integração Preparada:** Arquitetura orientada a serviços pronta para expansão e envio de lembretes via WhatsApp.

---

## 🛠️ Tecnologias Utilizadas (A Stack)

A aplicação foi desenvolvida focando em performance, legibilidade e nas melhores práticas do mercado:

* **Back-end:** PHP 8.3+ e [Laravel 13](https://laravel.com/) (Arquitetura MVC pura, Filas, Console Commands e Mailables).
* **Front-end:** [Livewire](https://livewire.laravel.com/) (Para reatividade sem a necessidade de APIs pesadas) e [Tailwind CSS](https://tailwindcss.com/) (Estilização via utilitários).
* **Banco de Dados:** MySQL (Manipulado inteiramente pelas *Migrations* e *Eloquent ORM* do Laravel).
* **Prototipagem de UI/UX:** Figma.
* **Infraestrutura Local:** Docker via [Laravel Sail](https://laravel.com/docs/sail).

---

## 🚀 Como Rodar o Projeto (Ambiente Local)

Este projeto utiliza o Laravel Sail, o que significa que você não precisa instalar o PHP ou o MySQL diretamente na sua máquina. O Docker gerencia todos os contêineres necessários.

**1. Clone o repositório:**
```bash
git clone https://github.com/SEU_USUARIO/iwori-agenda.git
cd iwori-agenda
```

**2. Crie o arquivo de ambiente:**
```bash
cp .env.example .env
```

Para usar o MySQL do Sail, ajuste as variáveis de banco no `.env` (a senha é obrigatória — o contêiner não sobe com senha vazia):
```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=iwori_agenda
DB_USERNAME=iwori
DB_PASSWORD=defina_uma_senha_forte
```

**3. Instale as dependências do Composer** (sem PHP na máquina, use o contêiner auxiliar):
```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php85-composer:latest composer install --ignore-platform-reqs
```

**4. Suba os contêineres com o Sail:**
```bash
./vendor/bin/sail up -d
```

**5. Gere a chave da aplicação e rode as migrations:**
```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

**6. Instale as dependências de front-end e inicie o Vite:**
```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

**7. Processe a fila de e-mails** (necessário para as notificações de agendamento):
```bash
./vendor/bin/sail artisan queue:listen
```

Pronto! A aplicação estará disponível em [http://localhost](http://localhost).

> 💡 **Dica:** os e-mails enviados no ambiente local são capturados pelo **Mailpit** — acesse o painel em [http://localhost:8025](http://localhost:8025) para visualizá-los.

> 🔒 **Nota de segurança:** por padrão, as portas da aplicação, do MySQL e do Mailpit ficam vinculadas a `127.0.0.1` — os serviços não são expostos à sua rede local.

---

## 🧪 Rodando os Testes

```bash
./vendor/bin/sail artisan test
```
