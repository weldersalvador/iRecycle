# iRecycle

Plataforma web que incentiva e facilita a venda de resíduos recicláveis por cidadãos a empresas de reciclagem licenciadas, em apoio à Lei Zero do Lixo de Juiz de Fora (Lei nº 15.100/2025).

O vendedor anuncia o material (categoria, descrição, quantidade, foto, lance mínimo e prazo). As empresas aprovadas disputam a compra por leilão. Ao fim do prazo, o sistema fecha a negociação, gera um **código de descarte** e notifica as partes. O vendedor entrega o material em um ponto de descarte credenciado, e o operador do ponto confirma o recebimento usando o código, concluindo a transação.

## Perfis de usuário

| Perfil | O que faz |
|---|---|
| **Vendedor** | Anuncia materiais, acompanha lances, recebe o código de descarte, consulta histórico e pontos de descarte |
| **Empresa compradora** | Depois de aprovada, consulta leilões abertos, dá lances, registra o pagamento (simulado) e consulta compras e lances |
| **Operador do ponto** | Confere o material com o código de descarte e confirma a entrega |
| **Administrador** | Aprova empresas, gerencia categorias, pontos e operadores, modera anúncios e usuários |

## Tecnologias

- **Back-end:** PHP 8.3+ e Laravel 13 (API JSON em `/api`, arquitetura em camadas no padrão MVC)
- **Banco de dados:** MySQL
- **Front-end:** HTML, CSS e JavaScript (sem framework), em `public/frontend`
- **Autenticação:** token por sessão, expirando após 30 minutos de inatividade
- **Gerência de configuração:** Git e GitHub
- **Gestão do projeto:** Scrum com Kanban (Trello)

## Pré-requisitos

- [PHP 8.3 ou superior](https://www.php.net/downloads) com as extensões `openssl`, `curl`, `mbstring`, `fileinfo`, `pdo_mysql` e `zip` habilitadas
- [Composer](https://getcomposer.org/)
- [MySQL](https://www.mysql.com/) 8 (ou MariaDB equivalente, como o do XAMPP)
- [Git](https://git-scm.com/)

No Windows, o [Laravel Herd](https://herd.laravel.com/windows) instala PHP e Composer já configurados. O Herd gratuito não inclui MySQL, então use o XAMPP, o DBngin ou o MySQL Installer.

## Instalação

```bash
# 1. Clonar o repositório
git clone https://github.com/weldersalvador/iRecycle.git
cd iRecycle

# 2. Instalar as dependências
composer install

# 3. Criar o arquivo de ambiente e a chave da aplicação
cp .env.example .env          # no Windows (cmd/PowerShell): copy .env.example .env
php artisan key:generate
```

### Configurar o banco de dados

Crie o banco no MySQL:

```sql
CREATE DATABASE irecycle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edite o `.env` com os dados do seu MySQL:

```env
APP_NAME=iRecycle
APP_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=irecycle
DB_USERNAME=root
DB_PASSWORD=
```

Crie as tabelas, os dados de exemplo e o link para as fotos dos anúncios:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

> `migrate:fresh` **apaga todas as tabelas** antes de recriá-las. Use apenas em desenvolvimento.

## Executando

Em um terminal, inicie o servidor:

```bash
php artisan serve
```

Em outro terminal, deixe rodando o agendador que encerra os leilões vencidos a cada minuto:

```bash
php artisan schedule:work
```

Acesse **http://127.0.0.1:8000/frontend/index.html**.

Com o Laravel Herd, se a pasta do projeto estiver dentro da pasta do Herd, o site abre em `http://irecycle.test/frontend/index.html`, sem precisar do `php artisan serve`.

Para encerrar os leilões vencidos manualmente, sem o agendador:

```bash
php artisan leiloes:encerrar
```

## Contas de teste

Criadas pelo seeder. **Use apenas em desenvolvimento.**

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | admin@irecycle.com | admin123 |
| Vendedor | vendedor@irecycle.com | 123456 |
| Empresa (aprovada) | empresa@irecycle.com | 123456 |
| Operador | operador@irecycle.com | 123456 |
| Empresa (pendente de aprovação) | pendente@irecycle.com | 123456 |

O seeder também cria três anúncios de exemplo. O anúncio "Latinhas de Alumínio (DEMO)" fecha 3 minutos após o seed, para demonstrar o encerramento do leilão.

## Fluxo principal

1. A empresa se cadastra e **aguarda a aprovação** do administrador.
2. O vendedor publica um anúncio com lance mínimo e prazo de encerramento.
3. Empresas aprovadas dão lances. Cada lance precisa superar o anterior.
4. Ao fim do prazo, o sistema fecha o leilão, define o vencedor, **gera o código de descarte** (`REC-XXXXXX`), registra o pagamento como pendente e envia notificações.
5. O vendedor entrega o material em um ponto de descarte e informa o código.
6. O operador digita o código, e a transação é concluída.
7. A empresa registra o pagamento (simulado, sem integração com meios reais).

## Endpoints da API

Todas as rotas ficam sob `/api`. Exceto `cadastro` e `login`, exigem o cabeçalho `Authorization: Bearer <token>`.

| Método | Rota | Perfil | Requisito |
|---|---|---|---|
| POST | `/cadastro` | público | RF1 |
| POST | `/login` | público | RF3 |
| POST | `/logout`, GET `/me` | todos | RF3 |
| GET | `/pontos`, `/categorias` | todos | RF2 |
| GET | `/notificacoes` | todos | RF11 |
| GET | `/vendedor/resumo` | vendedor | RF6 |
| POST | `/vendedor/anuncios` | vendedor | RF4 |
| POST | `/vendedor/anuncios/{id}/cancelar` | vendedor | RF5 |
| GET | `/vendedor/anuncios/{id}/lances` | vendedor | RF6 |
| GET | `/empresa/leiloes` | empresa | RF7 |
| POST | `/empresa/leiloes/{id}/lances` | empresa | RF8 |
| GET | `/empresa/lances`, `/empresa/compras` | empresa | RF6 |
| POST | `/empresa/compras/{id}/pagar` | empresa | RF9 |
| POST | `/operador/entregas/confirmar` | operador | RF12 |
| GET, POST | `/admin/empresas` e `/admin/empresas/{id}/aprovar` ou `/recusar` | admin | RF13 |
| GET, POST, PUT | `/admin/categorias` | admin | RF14 |
| GET, POST, PUT | `/admin/operadores` | admin | RF15 |
| GET, POST | `/admin/anuncios`, `/admin/usuarios` (remover, bloquear, desbloquear) | admin | RF16 |
| GET, POST, PUT | `/admin/pontos` | admin | RF17 |

O fechamento do leilão e a geração do código (RF10) são feitos pelo sistema, no comando `leiloes:encerrar`.

## Estrutura do projeto

```
iRecycle/
├── app/
│   ├── Console/Commands/EncerrarLeiloes.php   # Fechamento automático dos leilões
│   ├── Http/
│   │   ├── Controllers/Api/                   # Auth, Vendedor, Empresa, Operador, Admin, Publico
│   │   └── Middleware/                        # AutenticaToken e ExigePerfil (controle por perfil)
│   ├── Models/                                # User, Empresa, Anuncio, Lance, Transacao, Pagamento...
│   └── Services/LeilaoService.php             # Regras de lance e de fechamento do leilão
├── database/
│   ├── migrations/                            # Estrutura do banco
│   └── seeders/DatabaseSeeder.php             # Dados e contas de teste
├── public/frontend/                           # Interface (index.html, style.css, api.js)
├── routes/
│   ├── api.php                                # Rotas da API
│   └── console.php                            # Agendamento do encerramento de leilões
└── docs/                                      # Requisitos, diagramas e arquitetura
```

## Decisões de projeto

- **Lances consistentes (RNF12):** cada lance roda dentro de uma transação de banco, com bloqueio de linha no anúncio, para que dois lances simultâneos não gerem resultado incorreto.
- **Encerramento automático (RNF13):** o comando `leiloes:encerrar` roda a cada minuto pelo agendador. As telas de leilões também encerram os vencidos ao serem abertas.
- **Privacidade (RNF15):** os lances recebidos pelo vendedor aparecem sem identificar a empresa. Os dados de contato só são compartilhados entre as partes da transação.
- **Auditoria (RNF14):** ações críticas (login, lances, aprovação de empresas, geração de código, confirmação de entrega, moderação) são registradas em `storage/logs/laravel.log`.
- **Modelo de dados:** o leilão é representado dentro de `anuncios` (campos `maior_lance` e `data_encerramento`), e o código de descarte e a entrega ficam em `transacoes`.

## Equipe e processo

Projeto desenvolvido com Scrum e Kanban, em 4 sprints de 1 semana.

| Papel | Membro |
|---|---|
| Product Owner | Vitor Assunção |
| Scrum Master | Welder |
| Desenvolvedor | Vitor Hugo |

Fluxo de branches:

- `main`: código estável, atualizado somente por pull request
- `develop`: integração da sprint atual
- `feature/<nome>`: uma branch por funcionalidade

## Documentação

Os documentos do projeto ficam na pasta `docs/`:

- Documento de requisitos
- Diagrama de casos de uso, de atividades e de classes
- Representação da arquitetura de software

## Licença

Projeto acadêmico. Defina aqui a licença, se houver.
