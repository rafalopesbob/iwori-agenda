# 🌿 Iwori Agenda

> Gestão inteligente de sessões, presenças e faturamento para profissionais do desenvolvimento humano.

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

* **Back-end:** PHP 8+ e [Laravel 11](https://laravel.com/) (Arquitetura MVC pura, Filas, Console Commands e Mailables).
* **Front-end:** [Livewire](https://livewire.laravel.com/) (Para reatividade sem a necessidade de APIs pesadas) e [Tailwind CSS](https://tailwindcss.com/) (Estilização via utilitários).
* **Banco de Dados:** MySQL (Manipulado inteiramente pelas *Migrations* e *Eloquent ORM* do Laravel).
* **Prototipagem de UI/UX:** Figma.
* **Infraestrutura Local:** Docker via [Laravel Sail](https://laravel.com/docs/sail).

---

## 🚀 Como Rodar o Projeto (Ambiente Local)

Este projeto utiliza o Laravel Sail, o que significa que você não precisa instalar o PHP ou o MySQL diretamente na sua máquina. O Docker gerencia todos os contêineres necessários.

**1. Clone o repositório:**
```bash
git clone [https://github.com/SEU_USUARIO/iwori-agenda.git](https://github.com/SEU_USUARIO/iwori-agenda.git)
cd iwori-agenda
